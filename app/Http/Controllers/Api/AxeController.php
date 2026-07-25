<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Axe;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AxeController extends Controller
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

    // GET /api/axes
    public function index(Request $request)
    {
        $user = $request->user();

        $axes = Axe::with('service')
            ->withCount('dossiers')
            ->when($user->role === 'chef_service', fn($q) => $q->where('service_id', $user->service_id))
            ->when($user->role === 'technicien', fn($q) => $q->whereHas('users', fn($qu) => $qu->where('users.id', $user->id)))
            ->when($request->service_id, fn($q) => $q->where('service_id', $request->service_id))
            ->get();

        return response()->json($axes);
    }
    // POST /api/axes
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'service_id'  => $user->role === 'admin' ? 'required|exists:services,id' : 'nullable|exists:services,id',
        ]);

        $serviceId = $user->role === 'admin' ? $request->service_id : $user->service_id;

        $axe = Axe::create([
            'name' => $request->name,
            'description' => $request->description,
            'service_id' => $serviceId,
        ]);

        return response()->json([
            'message' => 'Axe créé avec succès',
            'axe'     => $axe,
        ], 201);
    }

    // GET /api/axes/{id}
    public function show($id)
    {
        $axe = Axe::with(['service', 'dossiers'])->findOrFail($id);

        $user = request()->user();
        if ($user->role === 'chef_service' && (int) $axe->service_id !== (int) $user->service_id) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        if ($user->role === 'technicien') {
            $allowed = $axe->users()->where('users.id', $user->id)->exists();
            if (!$allowed) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        if ($user->role !== 'admin' && $user->role !== 'chef_service' && $user->role !== 'technicien') {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        return response()->json($axe);
    }

    // PUT /api/axes/{id}
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $axe = Axe::findOrFail($id);

        if ($user->role !== 'admin' && $axe->service_id !== $user->service_id) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'service_id'  => $user->role === 'admin' ? 'sometimes|exists:services,id' : 'prohibited',
        ]);

        $axe->update($request->only('name', 'description', 'service_id'));

        return response()->json([
            'message' => 'Axe modifié avec succès',
            'axe'     => $axe,
        ]);
    }

    // DELETE /api/axes/{id}
    public function destroy($id)
    {
        $user = request()->user();
        $axe = Axe::findOrFail($id);

        if ($user->role !== 'admin' && $axe->service_id !== $user->service_id) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $files = File::whereHas('dossier', fn($q) => $q->where('axe_id', $axe->id))
            ->get(['file_url', 'previous_file_url']);
        foreach ($files as $file) {
            $this->deletePublicUrlFromDisk($file->file_url);
            $this->deletePublicUrlFromDisk($file->previous_file_url);
        }

        $axe->delete();

        return response()->json([
            'message' => 'Axe supprimé avec succès',
        ]);
    }
}
