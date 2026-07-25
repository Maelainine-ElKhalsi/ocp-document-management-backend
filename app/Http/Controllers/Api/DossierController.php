<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dossier;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DossierController extends Controller
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

    // GET /api/dossiers
    public function index(Request $request)
    {
        $user = $request->user();

        $dossiers = Dossier::with(['axe', 'user'])
            ->withCount('files')
            ->when($user->role === 'technicien', fn($q) => $q->whereHas('axe.users', fn($qu) => $qu->where('users.id', $user->id)))
            ->when($user->role === 'chef_service', fn($q) => $q->whereHas('axe', fn($qa) => $qa->where('service_id', $user->service_id)))
            ->when($request->axe_id, fn($q) => $q->where('axe_id', $request->axe_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->get();

        return response()->json($dossiers);
    }

    // POST /api/dossiers
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'axe_id'      => 'required|exists:axes,id',
        ]);

        if ($user->role === 'chef_service') {
            $axeServiceId = \App\Models\Axe::where('id', $request->axe_id)->value('service_id');
            if ((int) $axeServiceId !== (int) $user->service_id) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        if ($user->role === 'technicien') {
            $allowed = \App\Models\Axe::whereKey($request->axe_id)
                ->whereHas('users', fn($qu) => $qu->where('users.id', $user->id))
                ->exists();

            if (!$allowed) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $dossier = Dossier::create([
            'name'        => $request->name,
            'description' => $request->description,
            'axe_id'      => $request->axe_id,
            'user_id'     => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Dossier créé avec succès',
            'dossier' => $dossier,
        ], 201);
    }

    // GET /api/dossiers/{id}
    public function show($id)
    {
        $dossier = Dossier::with(['axe', 'user', 'files'])->findOrFail($id);

        $user = request()->user();

        if ($user->role === 'chef_service') {
            $axeServiceId = \App\Models\Axe::where('id', $dossier->axe_id)->value('service_id');
            if ((int) $axeServiceId !== (int) $user->service_id) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        if ($user->role === 'technicien') {
            $allowed = $dossier->axe?->users()->where('users.id', $user->id)->exists();
            if (!$allowed) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        return response()->json($dossier);
    }

    // PUT /api/dossiers/{id}
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $dossier = Dossier::findOrFail($id);

        if ($user->role === 'chef_service') {
            $axeServiceId = \App\Models\Axe::where('id', $dossier->axe_id)->value('service_id');
            if ((int) $axeServiceId !== (int) $user->service_id) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        if ($user->role === 'technicien') {
            $dossier->loadMissing('axe.users');
            $allowed = $dossier->axe?->users()->where('users.id', $user->id)->exists();
            if (!$allowed) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $dossier->update($request->only('name', 'description'));

        return response()->json([
            'message' => 'Dossier modifié avec succès',
            'dossier' => $dossier,
        ]);
    }

    // DELETE /api/dossiers/{id}
    public function destroy($id)
    {
        $user = request()->user();
        $dossier = Dossier::findOrFail($id);

        if ($user->role === 'chef_service') {
            $axeServiceId = \App\Models\Axe::where('id', $dossier->axe_id)->value('service_id');
            if ((int) $axeServiceId !== (int) $user->service_id) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        if ($user->role === 'technicien') {
            $dossier->loadMissing('axe.users');
            $allowed = $dossier->axe?->users()->where('users.id', $user->id)->exists();
            if (!$allowed) {
                return response()->json([
                    'message' => 'Accès refusé',
                ], 403);
            }
        }

        $files = File::where('dossier_id', $dossier->id)->get(['file_url', 'previous_file_url']);
        foreach ($files as $file) {
            $this->deletePublicUrlFromDisk($file->file_url);
            $this->deletePublicUrlFromDisk($file->previous_file_url);
        }

        $dossier->delete();

        return response()->json([
            'message' => 'Dossier supprimé avec succès',
        ]);
    }
}
