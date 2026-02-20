<?php

use App\Models\Projects;
use App\Models\Role;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication for task routes', function () {
    $this->getJson('/api/tasks')->assertStatus(401);
});

it('allows an authenticated user to perform task crud on owned project', function () {
    $user = User::factory()->create();
    $project = Projects::factory()->create([
        'user_id' => $user->id,
        'created_by' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $createResponse = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'Ship API',
        'description' => 'Finish task CRUD',
        'status' => 'pending',
    ])->assertCreated();

    $taskId = $createResponse->json('id');

    $this->getJson('/api/tasks/'.$taskId)
        ->assertOk()
        ->assertJsonPath('title', 'Ship API');

    $this->putJson('/api/tasks/'.$taskId, [
        'status' => 'in_progress',
    ])->assertOk()
      ->assertJsonPath('status', 'in_progress');

    $this->deleteJson('/api/tasks/'.$taskId)
        ->assertOk()
        ->assertJsonPath('message', 'Task deleted successfully');

    $this->assertDatabaseMissing('tasks', ['id' => $taskId]);
});

it('forbids non-admin user from accessing unrelated task', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $project = Projects::factory()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
    ]);

    $task = Tasks::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'user_id' => $owner->id,
        'assigned_to' => $owner->id,
    ]);

    Sanctum::actingAs($otherUser);

    $this->getJson('/api/tasks/'.$task->id)->assertStatus(403);
});

it('allows admin user to access unrelated task', function () {
    $admin = User::factory()->create();
    $owner = User::factory()->create();
    $role = Role::create(['name' => 'admin']);
    $admin->roles()->attach($role->id);

    $project = Projects::factory()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
    ]);

    $task = Tasks::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'user_id' => $owner->id,
        'assigned_to' => $owner->id,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/tasks/'.$task->id)
        ->assertOk()
        ->assertJsonPath('id', $task->id);
});
