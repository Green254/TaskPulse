<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // Get all roles
    public function index()
    {
        return response()->json(Role::all());
    }

    // Get all users with roles
    public function usersWithRoles()
    {
        $users = User::with('roles')->get();
        return response()->json($users);
    }

    // Assign a role to user
   public function assignRole(User $user, Request $request)
{
    $roleId = $request->input('role_id');

    // Just in case, validate the role_id
    if (! $roleId) {
        return response()->json(['error' => 'role_id is required'], 422);
    }

    $user->roles()->syncWithoutDetaching([$roleId]);

    return response()->json(['message' => 'Role assigned successfully']);
}

    // Remove a role from user
    public function removeRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->roles()->detach($request->role_id);
        return response()->json(['message' => 'Role removed successfully']);
    }
}
