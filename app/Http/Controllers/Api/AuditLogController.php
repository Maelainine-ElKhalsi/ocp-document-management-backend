<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function latest(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $limit = (int) $request->query('limit', 10);
        if ($limit <= 0) $limit = 10;
        if ($limit > 50) $limit = 50;

        $logs = AuditLog::with(['actor:id,name,email,role'])
            ->with(['service:id,name', 'axe:id,name', 'dossier:id,name'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json($logs);
    }

    public function latestByUser(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Accès refusé',
            ], 403);
        }

        $target = User::findOrFail($id);

        $limit = (int) $request->query('limit', 3);
        if ($limit <= 0) $limit = 3;
        if ($limit > 50) $limit = 50;

        $logs = AuditLog::with(['actor:id,name,email,role'])
            ->with(['service:id,name', 'axe:id,name', 'dossier:id,name'])
            ->where('actor_user_id', $target->id)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
            ],
            'logs' => $logs,
        ]);
    }
}
