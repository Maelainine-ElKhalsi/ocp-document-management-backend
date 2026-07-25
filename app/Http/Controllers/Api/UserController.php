<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    private function deletePublicUrlFromDisk(?string $publicUrl): void
    {
        if (!$publicUrl) return;

        $prefix = '/storage/';
        if (str_starts_with($publicUrl, $prefix)) {
            $relativePath = substr($publicUrl, strlen($prefix));
            if ($relativePath) {
                Storage::disk('public')->delete($relativePath);
            }
        }
    }

    // GET /api/users
    public function index()
    {
        $users = User::with(['service', 'axes.service'])->get();
        return response()->json($users);
    }

    // POST /api/users
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|min:6',
            'role'       => 'required|in:admin,chef_service,technicien',
            'service_id' => 'nullable|exists:services,id',
            'axe_ids'    => 'sometimes|array',
            'axe_ids.*'  => 'integer|exists:axes,id',
        ]);

        if ($request->role === 'admin') {
            $serviceId = null;
        } elseif ($request->role === 'chef_service') {
            if (!$request->service_id) {
                return response()->json([
                    'message' => 'Un chef de service doit être rattaché à un service.',
                ], 422);
            }
            $serviceId = $request->service_id;
        } else {
            $serviceId = $request->service_id;
        }

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'service_id' => $serviceId,
        ]);

        if ($user->role === 'technicien') {
            $axeIds = $request->input('axe_ids', []);
            $user->axes()->sync($axeIds);
        }

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user'    => $user,
        ], 201);
    }

    // PUT /api/users/{id}
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $currentRole = $user->role;

        $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'email'      => 'sometimes|required|email|unique:users,email,' . $user->id,
            'role'       => 'sometimes|required|in:admin,chef_service,technicien',
            'service_id' => 'nullable|exists:services,id',
            'password'   => 'nullable|min:6',
            'axe_ids'    => 'sometimes|array',
            'axe_ids.*'  => 'integer|exists:axes,id',
        ]);

        $data = $request->only(['name', 'email', 'role', 'service_id']);

        $targetRole = array_key_exists('role', $data) ? $data['role'] : $user->role;
        $targetServiceId = array_key_exists('service_id', $data) ? $data['service_id'] : $user->service_id;

        if ($targetRole === 'admin') {
            $data['service_id'] = null;
        }

        if ($targetRole === 'chef_service' && !$targetServiceId) {
            return response()->json([
                'message' => 'Un chef de service doit être rattaché à un service.',
            ], 422);
        }

        if (array_key_exists('role', $data) && $currentRole === 'admin' && $data['role'] !== 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Impossible de modifier le rôle : il doit rester au moins un administrateur dans le système.',
                ], 422);
            }
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($targetRole === 'technicien') {
            if ($request->has('axe_ids')) {
                $user->axes()->sync($request->input('axe_ids', []));
            }
        } else {
            $user->axes()->detach();
        }

        return response()->json([
            'message' => 'Utilisateur modifié avec succès',
            'user'    => $user->load('service'),
        ]);
    }

    // DELETE /api/users/{id}
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Impossible de supprimer le dernier administrateur du système.',
                ], 422);
            }
        }

        $files = File::where('user_id', $user->id)->get(['file_url', 'previous_file_url']);
        foreach ($files as $file) {
            $this->deletePublicUrlFromDisk($file->file_url);
            $this->deletePublicUrlFromDisk($file->previous_file_url);
        }

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès',
        ]);
    }
    // GET /api/users/search?q=...
    public function search(Request $request)
    {
        $q = (string) $request->q;

        $users = User::where('role', 'technicien')
            ->whereNull('service_id')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            })
            ->select('id', 'name', 'email', 'role', 'service_id')
            ->limit(5)
            ->get();

        return response()->json($users);
    }
}
