<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dossier;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    private function logFileAction(?Request $request, string $action, File $file, ?Dossier $dossier, array $meta = []): void
    {
        try {
            $actor = $request?->user();
            $dossier?->loadMissing('axe');

            $axeId = $dossier?->axe_id;
            $serviceId = $dossier?->axe?->service_id;

            AuditLog::create([
                'action' => $action,
                'actor_user_id' => $actor?->id,
                'entity_type' => 'file',
                'entity_id' => $file->id,
                'service_id' => $serviceId,
                'axe_id' => $axeId,
                'dossier_id' => $dossier?->id,
                'meta' => array_merge([
                    'file_name' => $file->name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                ], $meta),
            ]);
        } catch (\Throwable $e) {
            // Intentionally swallow audit log errors
        }
    }

    private function canAccessDossier(Request $request, Dossier $dossier): bool
    {
        $user = $request->user();
        if (!$user) return false;
        if ($user->role === 'admin') return true;

        if ($user->role === 'technicien') {
            $dossier->loadMissing('axe.users');
            return $dossier->axe?->users()->where('users.id', $user->id)->exists() ?? false;
        }

        if ($user->role === 'chef_service') {
            $axeServiceId = $dossier->axe ? $dossier->axe->service_id : null;
            return (int) $axeServiceId === (int) $user->service_id;
        }

        return false;
    }

    private function canAccessFile(Request $request, File $file): bool
    {
        $file->loadMissing(['dossier.axe']);
        return $file->dossier ? $this->canAccessDossier($request, $file->dossier) : false;
    }

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

    private function publicUrlToDiskPath(?string $publicUrl): ?string
    {
        if (!$publicUrl) return null;

        $prefix = '/storage/';
        if (!str_starts_with($publicUrl, $prefix)) return null;

        $relativePath = substr($publicUrl, strlen($prefix));
        if (!$relativePath) return null;

        return $relativePath;
    }

    // GET /api/files
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        $files = File::with(['dossier', 'user'])
            ->when($request->dossier_id, fn($q) => $q->where('dossier_id', $request->dossier_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($user->role === 'technicien', fn($q) => $q->whereHas('dossier.axe.users', fn($qu) => $qu->where('users.id', $user->id)))
            ->when($user->role === 'chef_service', fn($q) => $q->whereHas('dossier.axe', fn($qa) => $qa->where('service_id', $user->service_id)))
            ->latest()
            ->get();

        return response()->json($files);
    }

    // POST /api/files
    public function store(Request $request)
    {
        $request->validate([
            'file'       => 'required|file|max:20480',
            'dossier_id' => 'required|exists:dossiers,id',
        ]);

        $dossier = Dossier::with('axe')->findOrFail($request->dossier_id);
        if (!$this->canAccessDossier($request, $dossier)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('files', 'public');

        $file = File::create([
            'name'       => $uploadedFile->getClientOriginalName(),
            'file_url'   => Storage::url($path),
            'file_type'  => $uploadedFile->getClientMimeType(),
            'file_size'  => $uploadedFile->getSize(),
            'dossier_id' => $request->dossier_id,
            'user_id'    => $request->user()->id,
        ]);

        $this->logFileAction($request, 'file.upload', $file, $dossier, [
            'new_file_url' => $file->file_url,
        ]);

        return response()->json([
            'message' => 'Fichier uploadé avec succès',
            'file'    => $file,
        ], 201);
    }

    // GET /api/files/{id}
    public function show($id)
    {
        $file = File::with(['dossier.axe', 'user'])->findOrFail($id);
        if (!$this->canAccessFile(request(), $file)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }
        return response()->json($file);
    }

    // GET /api/files/{id}/view
    public function view($id)
    {
        $file = File::findOrFail($id);

        if (!$this->canAccessFile(request(), $file)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $diskPath = $this->publicUrlToDiskPath($file->file_url);
        if (!$diskPath || !Storage::disk('public')->exists($diskPath)) {
            return response()->json([
                'message' => 'Fichier introuvable',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($diskPath);
        $mime = $file->file_type ?: Storage::disk('public')->mimeType($diskPath);

        return response()->file($absolutePath, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $file->name . '"',
        ]);
    }

    // GET /api/files/{id}/download
    public function download($id)
    {
        $file = File::findOrFail($id);

        if (!$this->canAccessFile(request(), $file)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $diskPath = $this->publicUrlToDiskPath($file->file_url);
        if (!$diskPath || !Storage::disk('public')->exists($diskPath)) {
            return response()->json([
                'message' => 'Fichier introuvable',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($diskPath);
        $mime = $file->file_type ?: Storage::disk('public')->mimeType($diskPath);

        return response()->download($absolutePath, $file->name, [
            'Content-Type' => $mime ?: 'application/octet-stream',
        ]);
    }

    // PUT /api/files/{id}
    public function update(Request $request, $id)
    {
        $file = File::findOrFail($id);

        $file->loadMissing('dossier.axe');
        $dossier = $file->dossier;

        if (!$this->canAccessFile($request, $file)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('files', 'public');

        $this->deletePublicUrlFromDisk($file->previous_file_url);

        $previousUrl = $file->file_url;
        $previousName = $file->name;
        $previousType = $file->file_type;
        $previousSize = $file->file_size;

        $file->update([
            'name'      => $uploadedFile->getClientOriginalName(),
            'file_url'  => Storage::url($path),
            'previous_file_url' => $previousUrl,
            'file_type' => $uploadedFile->getClientMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'user_id'   => $request->user()->id,
        ]);

        $this->logFileAction($request, 'file.update_version', $file, $dossier, [
            'previous_file_url' => $previousUrl,
            'new_file_url' => $file->file_url,
            'previous_name' => $previousName,
            'new_name' => $file->name,
            'previous_type' => $previousType,
            'new_type' => $file->file_type,
            'previous_size' => $previousSize,
            'new_size' => $file->file_size,
        ]);

        return response()->json([
            'message' => 'Fichier modifié avec succès',
            'file'    => $file,
        ]);
    }

    // DELETE /api/files/{id}
    public function destroy($id)
    {
        $file = File::findOrFail($id);

        $file->loadMissing('dossier.axe');
        $dossier = $file->dossier;

        if (!$this->canAccessFile(request(), $file)) {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $this->deletePublicUrlFromDisk($file->file_url);
        $this->deletePublicUrlFromDisk($file->previous_file_url);

        $this->logFileAction(request(), 'file.delete', $file, $dossier, [
            'deleted_file_url' => $file->file_url,
            'deleted_previous_file_url' => $file->previous_file_url,
        ]);

        $file->delete();

        return response()->json([
            'message' => 'Fichier supprimé avec succès',
        ]);
    }
}
