<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|string|email|max:255|unique:users,email',
            'department_id' => 'required|integer|exists:departments,id',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $department = Department::query()->findOrFail($validated['department_id']);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'department_id' => $department->id,
            'password' => $validated['password'],
        ]);

        $this->assignRegistrationRoles($user, $department);

        $token = $user->createToken('main')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $user->fresh()->load(['roles', 'department']),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::query()
            ->where('name', $credentials['name'])
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'name' => ['The provided credentials are incorrect.'],
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isCurrentlySuspended()) {
            return response()->json([
                'message' => $this->buildSuspensionMessage($user),
                'suspended_until' => $user->suspended_until?->toIso8601String(),
            ], 423);
        }

        if ($user->is_suspended && $user->suspended_until && $user->suspended_until->isPast()) {
            $user->forceFill([
                'is_suspended' => false,
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();
        }

        $user->load(['roles', 'department']);
        $token = $user->createToken('main')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ])->header('Authorization', 'Bearer ' . $token);
    }

    public function logout(Request $request)
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['roles', 'department']));
    }

    private function assignRegistrationRoles(User $user, Department $department): void
    {
        $roleIds = [];

        $baseRole = Role::query()->firstOrCreate(['name' => 'user']);
        $roleIds[] = $baseRole->id;

        $departmentRoleName = match (strtolower($department->name)) {
            'management' => 'manager',
            'security' => 'watchman',
            'kitchen' => 'chef',
            'staff' => 'staff',
            default => 'user',
        };

        if ($departmentRoleName !== 'user') {
            $departmentRole = Role::query()->firstOrCreate(['name' => $departmentRoleName]);
            $roleIds[] = $departmentRole->id;
        }

        $user->roles()->syncWithoutDetaching($roleIds);
    }

    private function buildSuspensionMessage(User $user): string
    {
        if ($user->suspended_until) {
            return 'Your account is suspended until ' . $user->suspended_until->toDayDateTimeString() . '.';
        }

        return 'Your account is suspended. Contact an administrator.';
    }
}
