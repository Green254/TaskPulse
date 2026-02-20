<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows admin to suspend and reactivate a user, and suspended users cannot login', function () {
    $management = Department::query()->create(['name' => 'Management']);
    $staffDepartment = Department::query()->create(['name' => 'Staff']);
    $adminRole = Role::query()->create(['name' => 'admin']);
    $userRole = Role::query()->create(['name' => 'user']);

    $admin = User::query()->create([
        'name' => 'admin.user',
        'email' => 'admin.user@example.com',
        'department_id' => $management->id,
        'password' => 'password123',
    ]);
    $admin->roles()->sync([$adminRole->id]);

    $staff = User::query()->create([
        'name' => 'staff.user',
        'email' => 'staff.user@example.com',
        'department_id' => $staffDepartment->id,
        'password' => 'password123',
    ]);
    $staff->roles()->sync([$userRole->id]);

    Sanctum::actingAs($admin);

    $this->patchJson('/api/admin/users/' . $staff->id . '/suspend', [
        'reason' => 'Repeated policy violations',
        'until' => now()->addDays(3)->toIso8601String(),
    ])->assertOk()
        ->assertJsonPath('user.is_currently_suspended', true);

    $this->postJson('/api/login', [
        'name' => $staff->name,
        'email' => $staff->email,
        'password' => 'password123',
    ])->assertStatus(423);

    $this->patchJson('/api/admin/users/' . $staff->id . '/reactivate')
        ->assertOk()
        ->assertJsonPath('user.is_currently_suspended', false);

    $this->postJson('/api/login', [
        'name' => $staff->name,
        'email' => $staff->email,
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('allows admin to delete users but blocks self-delete', function () {
    $management = Department::query()->create(['name' => 'Management']);
    $staffDepartment = Department::query()->create(['name' => 'Staff']);
    $adminRole = Role::query()->create(['name' => 'admin']);
    $userRole = Role::query()->create(['name' => 'user']);

    $admin = User::query()->create([
        'name' => 'admin.delete',
        'email' => 'admin.delete@example.com',
        'department_id' => $management->id,
        'password' => 'password123',
    ]);
    $admin->roles()->sync([$adminRole->id]);

    $target = User::query()->create([
        'name' => 'target.delete',
        'email' => 'target.delete@example.com',
        'department_id' => $staffDepartment->id,
        'password' => 'password123',
    ]);
    $target->roles()->sync([$userRole->id]);

    Sanctum::actingAs($admin);

    $this->deleteJson('/api/admin/users/' . $target->id)
        ->assertOk()
        ->assertJsonPath('message', 'User deleted successfully.');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);

    $this->deleteJson('/api/admin/users/' . $admin->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user']);
});

it('forbids non-admin users from admin user-management endpoints', function () {
    $management = Department::query()->create(['name' => 'Management']);
    $staffDepartment = Department::query()->create(['name' => 'Staff']);
    $managerRole = Role::query()->create(['name' => 'manager']);
    $userRole = Role::query()->create(['name' => 'user']);

    $manager = User::query()->create([
        'name' => 'manager.user',
        'email' => 'manager.user@example.com',
        'department_id' => $management->id,
        'password' => 'password123',
    ]);
    $manager->roles()->sync([$managerRole->id, $userRole->id]);

    $staff = User::query()->create([
        'name' => 'staff.blocked',
        'email' => 'staff.blocked@example.com',
        'department_id' => $staffDepartment->id,
        'password' => 'password123',
    ]);
    $staff->roles()->sync([$userRole->id]);

    Sanctum::actingAs($manager);

    $this->patchJson('/api/admin/users/' . $staff->id . '/suspend')
        ->assertStatus(403);
});

