<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Role;
use App\Models\SystemTheme;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const MANAGED_ROLES = [
        'admin',
        'manager',
        'staff',
        'watchman',
        'chef',
        'user',
    ];

    public function summary(): JsonResponse
    {
        $totalUsers = User::query()->count();
        $suspendedUsers = User::query()->currentlySuspended()->count();

        return response()->json([
            'total_users' => $totalUsers,
            'active_users' => max(0, $totalUsers - $suspendedUsers),
            'suspended_users' => $suspendedUsers,
            'department_count' => Department::query()->count(),
            'manager_count' => $this->countUsersWithRole('manager'),
            'staff_count' => $this->countUsersWithAnyRole(['staff', 'watchman', 'chef', 'user']),
            'announcement_count' => Announcement::query()->activeNow()->count(),
            'active_theme' => SystemTheme::query()->activeNow()->latest('updated_at')->first(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(self::MANAGED_ROLES)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::in(['all', 'active', 'suspended'])],
        ]);

        $query = User::query()
            ->with(['roles', 'department'])
            ->orderBy('name');

        if (!empty($validated['search'])) {
            $search = mb_strtolower(trim($validated['search']));
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        if (!empty($validated['role'])) {
            $role = $validated['role'];
            $query->whereHas('roles', function ($builder) use ($role) {
                $builder->where('name', $role);
            });
        }

        if (!empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }

        if (($validated['status'] ?? 'all') === 'suspended') {
            $query->currentlySuspended();
        } elseif (($validated['status'] ?? 'all') === 'active') {
            $query->where(function ($builder) {
                $builder->where('is_suspended', false)
                    ->orWhere(function ($suspendedBuilder) {
                        $suspendedBuilder->where('is_suspended', true)
                            ->whereNotNull('suspended_until')
                            ->where('suspended_until', '<=', now());
                    });
            });
        }

        $users = $query->get()->map(function (User $user) {
            return $this->serializeUser($user);
        });

        return response()->json($users);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', Rule::in(self::MANAGED_ROLES)],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'department_id' => $validated['department_id'],
        ]);

        $this->syncUserRoles($user, $validated['role'] ?? 'user');

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $this->serializeUser($user->fresh()->load(['roles', 'department'])),
        ], 201);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
        ]);

        if ($request->user()->id === $user->id && $validated['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['You cannot remove your own admin access.'],
            ]);
        }

        if (
            $user->hasRole('admin') &&
            $validated['role'] !== 'admin' &&
            !$user->isCurrentlySuspended() &&
            $this->activeAdminCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => ['At least one active admin must remain in the system.'],
            ]);
        }

        $this->syncUserRoles($user, $validated['role']);

        return response()->json([
            'message' => 'User hierarchy updated.',
            'user' => $this->serializeUser($user->fresh()->load(['roles', 'department'])),
        ]);
    }

    public function suspendUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);

        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot suspend your own account.'],
            ]);
        }

        if ($user->hasRole('admin') && !$user->isCurrentlySuspended() && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['At least one active admin must remain in the system.'],
            ]);
        }

        $user->forceFill([
            'is_suspended' => true,
            'suspended_until' => $validated['until'] ?? null,
            'suspension_reason' => $validated['reason'] ?? null,
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'User suspended successfully.',
            'user' => $this->serializeUser($user->fresh()->load(['roles', 'department'])),
        ]);
    }

    public function reactivateUser(User $user): JsonResponse
    {
        $user->forceFill([
            'is_suspended' => false,
            'suspended_until' => null,
            'suspension_reason' => null,
        ])->save();

        return response()->json([
            'message' => 'User reactivated successfully.',
            'user' => $this->serializeUser($user->fresh()->load(['roles', 'department'])),
        ]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        if ($user->hasRole('admin') && !$user->isCurrentlySuspended() && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['At least one active admin must remain in the system.'],
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function announcements(): JsonResponse
    {
        $announcements = Announcement::query()
            ->with(['creator:id,name,email', 'department:id,name'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($announcements);
    }

    public function createAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'message' => ['required', 'string', 'max:3000'],
            'type' => ['required', Rule::in(['info', 'warning', 'critical', 'celebration'])],
            'target_scope' => ['required', Rule::in(['all', 'role', 'department'])],
            'target_role' => ['nullable', 'string', 'max:100'],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $normalized = $this->normalizeAnnouncementTargets($validated);

        $announcement = Announcement::query()->create([
            ...$normalized,
            'created_by' => $request->user()->id,
        ])->load(['creator:id,name,email', 'department:id,name']);

        return response()->json([
            'message' => 'Announcement posted.',
            'announcement' => $announcement,
        ], 201);
    }

    public function deleteAnnouncement(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function themes(): JsonResponse
    {
        $themes = SystemTheme::query()
            ->with('creator:id,name,email')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($themes);
    }

    public function createTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'banner_message' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'surface_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'meta' => ['nullable', 'array'],
        ]);

        if (($validated['is_active'] ?? false) === true) {
            SystemTheme::query()->update(['is_active' => false]);
        }

        $theme = SystemTheme::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ])->load('creator:id,name,email');

        return response()->json([
            'message' => 'Theme created successfully.',
            'theme' => $theme,
        ], 201);
    }

    public function activateTheme(SystemTheme $theme): JsonResponse
    {
        SystemTheme::query()->whereKeyNot($theme->id)->update(['is_active' => false]);

        $theme->forceFill([
            'is_active' => true,
            'starts_at' => $theme->starts_at ?? now(),
        ])->save();

        return response()->json([
            'message' => 'Theme activated successfully.',
            'theme' => $theme->fresh()->load('creator:id,name,email'),
        ]);
    }

    public function activeAnnouncements(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles']);
        $roleNames = $user->roles->pluck('name')->all();

        $announcements = Announcement::query()
            ->with('department:id,name')
            ->activeNow()
            ->where(function ($builder) use ($user, $roleNames) {
                $builder->where('target_scope', 'all')
                    ->orWhere(function ($roleBuilder) use ($roleNames) {
                        $roleBuilder->where('target_scope', 'role');
                        if (count($roleNames) === 0) {
                            $roleBuilder->whereRaw('1 = 0');
                        } else {
                            $roleBuilder->whereIn('target_role', $roleNames);
                        }
                    })
                    ->orWhere(function ($departmentBuilder) use ($user) {
                        $departmentBuilder->where('target_scope', 'department')
                            ->where('target_department_id', $user->department_id);
                    });
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json($announcements);
    }

    public function activeTheme(): JsonResponse
    {
        $theme = SystemTheme::query()
            ->activeNow()
            ->latest('updated_at')
            ->first();

        return response()->json($theme);
    }

    private function normalizeAnnouncementTargets(array $validated): array
    {
        $scope = $validated['target_scope'];

        if ($scope === 'role' && empty($validated['target_role'])) {
            throw ValidationException::withMessages([
                'target_role' => ['target_role is required when target_scope is role.'],
            ]);
        }

        if ($scope === 'department' && empty($validated['target_department_id'])) {
            throw ValidationException::withMessages([
                'target_department_id' => ['target_department_id is required when target_scope is department.'],
            ]);
        }

        if ($scope !== 'role') {
            $validated['target_role'] = null;
        }

        if ($scope !== 'department') {
            $validated['target_department_id'] = null;
        }

        return $validated;
    }

    private function syncUserRoles(User $user, string $primaryRole): void
    {
        $roleNames = ['user'];
        if ($primaryRole === 'admin') {
            $roleNames = ['admin'];
        } elseif ($primaryRole !== 'user') {
            $roleNames[] = $primaryRole;
        }

        $roleIds = collect($roleNames)
            ->map(function (string $roleName) {
                return Role::query()->firstOrCreate(['name' => $roleName])->id;
            })
            ->all();

        $user->roles()->sync($roleIds);
    }

    private function activeAdminCount(): int
    {
        $now = now();

        return User::query()
            ->whereHas('roles', function ($builder) {
                $builder->where('name', 'admin');
            })
            ->where(function ($builder) use ($now) {
                $builder->where('is_suspended', false)
                    ->orWhere(function ($suspendedBuilder) use ($now) {
                        $suspendedBuilder->where('is_suspended', true)
                            ->whereNotNull('suspended_until')
                            ->where('suspended_until', '<=', $now);
                    });
            })
            ->count();
    }

    private function countUsersWithRole(string $role): int
    {
        return User::query()
            ->whereHas('roles', function ($builder) use ($role) {
                $builder->where('name', $role);
            })
            ->count();
    }

    private function countUsersWithAnyRole(array $roles): int
    {
        return User::query()
            ->whereHas('roles', function ($builder) use ($roles) {
                $builder->whereIn('name', $roles);
            })
            ->count();
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department_id' => $user->department_id,
            'department' => $user->department,
            'roles' => $user->roles,
            'is_suspended' => (bool) $user->is_suspended,
            'is_currently_suspended' => $user->isCurrentlySuspended(),
            'suspended_until' => $user->suspended_until?->toIso8601String(),
            'suspension_reason' => $user->suspension_reason,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}

