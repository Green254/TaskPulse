<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ManagerSubordinate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $authUser = $request->user()->loadMissing(['roles', 'department']);

        if ($authUser->hasRole('admin')) {
            $users = User::query()->with(['roles', 'department'])->orderBy('name')->get();
            return response()->json($users);
        }

        if ($authUser->hasRole('manager')) {
            $subordinateIds = ManagerSubordinate::query()
                ->where('manager_id', $authUser->id)
                ->pluck('subordinate_id');

            $users = User::query()
                ->with(['roles', 'department'])
                ->where(function ($query) use ($authUser, $subordinateIds) {
                    $query->where('id', $authUser->id);
                    if ($subordinateIds->isNotEmpty()) {
                        $query->orWhereIn('id', $subordinateIds);
                    }
                })
                ->orderBy('name')
                ->get();

            return response()->json($users);
        }

        $users = User::query()
            ->with(['roles', 'department'])
            ->where('id', $authUser->id)
            ->get();

        return response()->json($users);
    }

    public function subordinates(Request $request): JsonResponse
    {
        $authUser = $request->user()->loadMissing('roles');

        if ($authUser->hasRole('admin')) {
            $validated = $request->validate([
                'manager_id' => 'nullable|integer|exists:users,id',
            ]);

            $query = ManagerSubordinate::query()
                ->with([
                    'manager.roles',
                    'manager.department',
                    'subordinate.roles',
                    'subordinate.department',
                ]);

            if (isset($validated['manager_id'])) {
                $query->where('manager_id', $validated['manager_id']);
            }

            return response()->json($query->orderBy('manager_id')->get());
        }

        if (!$authUser->hasRole('manager')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $subordinates = User::query()
            ->with(['roles', 'department'])
            ->whereIn('id', function ($query) use ($authUser) {
                $query->select('subordinate_id')
                    ->from('manager_subordinates')
                    ->where('manager_id', $authUser->id);
            })
            ->orderBy('name')
            ->get();

        return response()->json($subordinates);
    }

    public function assignSubordinate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subordinate_id' => 'required|integer|exists:users,id',
            'manager_id' => 'nullable|integer|exists:users,id',
        ]);

        $authUser = $request->user()->loadMissing('roles');
        $manager = $this->resolveManagerForMutation($authUser, $validated['manager_id'] ?? null);

        if ($manager->id === $validated['subordinate_id']) {
            return response()->json(['message' => 'Manager cannot be their own subordinate.'], 422);
        }

        $subordinate = User::query()->with('roles')->findOrFail($validated['subordinate_id']);
        if ($subordinate->hasRole('admin') || $subordinate->hasRole('manager')) {
            return response()->json(['message' => 'Only staff users can be assigned as subordinates.'], 422);
        }

        ManagerSubordinate::query()->firstOrCreate([
            'manager_id' => $manager->id,
            'subordinate_id' => $subordinate->id,
        ]);

        return response()->json(['message' => 'Subordinate assigned successfully.']);
    }

    public function removeSubordinate(Request $request, User $subordinate): JsonResponse
    {
        $validated = $request->validate([
            'manager_id' => 'nullable|integer|exists:users,id',
        ]);

        $authUser = $request->user()->loadMissing('roles');
        $manager = $this->resolveManagerForMutation($authUser, $validated['manager_id'] ?? null);

        ManagerSubordinate::query()
            ->where('manager_id', $manager->id)
            ->where('subordinate_id', $subordinate->id)
            ->delete();

        return response()->json(['message' => 'Subordinate removed successfully.']);
    }

    private function resolveManagerForMutation(User $authUser, ?int $managerId): User
    {
        if ($authUser->hasRole('admin')) {
            if (!$managerId) {
                abort(422, 'manager_id is required for admin actions.');
            }

            $manager = User::query()->with('roles')->findOrFail($managerId);
            if (!$manager->hasRole('manager') && !$manager->hasRole('admin')) {
                abort(422, 'manager_id must belong to a manager or admin user.');
            }

            return $manager;
        }

        if (!$authUser->hasRole('manager')) {
            abort(403, 'Forbidden');
        }

        if ($managerId && $managerId !== $authUser->id) {
            abort(403, 'Managers can only manage their own subordinate mappings.');
        }

        return $authUser;
    }
}
