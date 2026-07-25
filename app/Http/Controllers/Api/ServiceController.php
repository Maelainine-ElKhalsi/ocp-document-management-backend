<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\User;

class ServiceController extends Controller
{
    // GET /api/services
    public function index()
    {
        $services = Service::withCount(['axes', 'users'])
            ->with(['users' => function ($q) {
                $q->where('role', 'chef_service')->select('id', 'name', 'email', 'service_id');
            }])
            ->get()
            ->map(function ($service) {
                $service->chef = $service->users->first();
                unset($service->users);
                return $service;
            });

        return response()->json($services);
    }

    // POST /api/services
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'chef_id'     => 'nullable|exists:users,id',
        ]);

        $service = Service::create($request->only('name', 'description'));

        // عين chef لـ service
        if ($request->chef_id) {
            $chef = User::findOrFail($request->chef_id);
            if ($chef->role === 'admin') {
                return response()->json([
                    'message' => "Impossible d'assigner un administrateur comme chef de service.",
                ], 422);
            }

            $updates = ['service_id' => $service->id];
            if ($chef->role === 'technicien') {
                $updates['role'] = 'chef_service';
            }

            $chef->update($updates);
        }

        return response()->json([
            'message' => 'Service créé avec succès',
            'service' => $service,
        ], 201);
    }

    // GET /api/services/{id}
    public function show(Request $request, $id)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($authUser->role === 'chef_service') {
            if (!$authUser->service_id || (int) $authUser->service_id !== (int) $id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        } elseif ($authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $service = Service::query()
            ->withCount([
                'axes',
                'users',
                'users as techniciens_count' => function ($q) {
                    $q->where('role', 'technicien');
                },
            ])
            ->with([
                'axes' => function ($q) {
                    $q->withCount('dossiers');
                },
                'users' => function ($q) {
                    $q->select('id', 'name', 'email', 'role', 'service_id');
                },
            ])
            ->findOrFail($id);

        return response()->json($service);
    }

    // PUT /api/services/{id}
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'chef_id'     => 'nullable|exists:users,id',
        ]);

        $service->update($request->only('name', 'description'));

        if ($request->has('chef_id')) {
            User::where('service_id', $service->id)
                ->where('role', 'chef_service')
                ->update([
                    'role'       => 'technicien',
                    'service_id' => null,
                ]);

            if ($request->chef_id) {
                $chef = User::findOrFail($request->chef_id);
                if ($chef->role === 'admin') {
                    return response()->json([
                        'message' => "Impossible d'assigner un administrateur comme chef de service.",
                    ], 422);
                }

                $updates = ['service_id' => $service->id];
                if ($chef->role === 'technicien') {
                    $updates['role'] = 'chef_service';
                }

                $chef->update($updates);
            }
        }

        return response()->json([
            'message' => 'Service modifié avec succès',
            'service' => $service,
        ]);
    }

    // DELETE /api/services/{id}
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Service supprimé avec succès',
        ]);
    }
}
