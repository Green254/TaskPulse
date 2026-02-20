<?php

use App\Models\Department;
use App\Models\ManagerSubordinate;
use App\Models\Projects;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('enforces unique names and requires department on registration', function () {
    $department = Department::query()->create(['name' => 'Staff']);

    $this->postJson('/api/register', [
        'name' => 'unique-name-user',
        'email' => 'first@example.com',
        'department_id' => $department->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $this->postJson('/api/register', [
        'name' => 'unique-name-user',
        'email' => 'second@example.com',
        'department_id' => $department->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/register', [
        'name' => 'another-unique-name',
        'email' => 'third@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['department_id']);
});

it('requires matching name email and password to login', function () {
    $department = Department::query()->create(['name' => 'Management']);
    $user = User::query()->create([
        'name' => 'manager.login',
        'email' => 'manager.login@example.com',
        'department_id' => $department->id,
        'password' => 'password123',
    ]);

    $role = Role::query()->create(['name' => 'manager']);
    $user->roles()->attach($role->id);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/login', [
        'name' => 'wrong.name',
        'email' => $user->email,
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);

    $this->postJson('/api/login', [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('returns only mapped subordinates for managers and enforces assignment boundaries', function () {
    $managementDepartment = Department::query()->create(['name' => 'Management']);
    $staffDepartment = Department::query()->create(['name' => 'Staff']);

    $managerRole = Role::query()->create(['name' => 'manager']);
    $userRole = Role::query()->create(['name' => 'user']);

    $manager = User::query()->create([
        'name' => 'manager.one',
        'email' => 'manager.one@example.com',
        'department_id' => $managementDepartment->id,
        'password' => 'password123',
    ]);
    $manager->roles()->sync([$managerRole->id, $userRole->id]);

    $mappedStaff = User::query()->create([
        'name' => 'mapped.staff',
        'email' => 'mapped.staff@example.com',
        'department_id' => $staffDepartment->id,
        'password' => 'password123',
    ]);
    $mappedStaff->roles()->sync([$userRole->id]);

    $unmappedStaff = User::query()->create([
        'name' => 'unmapped.staff',
        'email' => 'unmapped.staff@example.com',
        'department_id' => $staffDepartment->id,
        'password' => 'password123',
    ]);
    $unmappedStaff->roles()->sync([$userRole->id]);

    ManagerSubordinate::query()->create([
        'manager_id' => $manager->id,
        'subordinate_id' => $mappedStaff->id,
    ]);

    $project = Projects::factory()->create([
        'user_id' => $manager->id,
        'created_by' => $manager->id,
    ]);

    Sanctum::actingAs($manager);

    $teamResponse = $this->getJson('/api/team/users')->assertOk();
    $visibleIds = collect($teamResponse->json())->pluck('id')->all();

    expect($visibleIds)->toContain($manager->id);
    expect($visibleIds)->toContain($mappedStaff->id);
    expect($visibleIds)->not->toContain($unmappedStaff->id);

    $subordinateResponse = $this->getJson('/api/team/subordinates')->assertOk();
    expect(collect($subordinateResponse->json())->pluck('id')->all())->toContain($mappedStaff->id);

    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'Allowed assignment',
        'status' => 'pending',
        'assigned_to' => $mappedStaff->id,
    ])->assertCreated();

    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'Blocked assignment',
        'status' => 'pending',
        'assigned_to' => $unmappedStaff->id,
    ])->assertStatus(403);
});

