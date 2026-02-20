<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ManagerSubordinate;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $query = Tasks::query()->with(['project', 'assignedTo']);

        if (!$user->hasRole('admin')) {
            $query->where(function ($builder) use ($user) {
                $builder->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereHas('project', function ($projectQuery) use ($user) {
                        $projectQuery->where('created_by', $user->id)
                            ->orWhere('user_id', $user->id);
                    });
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (isset($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }

        $tasks = $query->latest()->paginate($validated['per_page'] ?? 15);

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $project = $this->resolveProjectForWrite($user, $validated['project_id'] ?? null);
        $assigneeId = $this->resolveAssigneeForWrite($user, $validated['assigned_to'] ?? null);

        $task = Tasks::query()->create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'due_date' => $validated['due_date'] ?? null,
            'assigned_to' => $assigneeId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'user_id' => $user->id,
        ])->load(['project', 'assignedTo']);

        return response()->json($task, 201);
    }

    public function show(Request $request, Tasks $task): JsonResponse
    {
        $this->authorizeTaskAccess($request->user(), $task);

        return response()->json($task->load(['project', 'assignedTo']));
    }

    public function update(Request $request, Tasks $task): JsonResponse
    {
        $this->authorizeTaskAccess($request->user(), $task);

        $validated = $request->validate([
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        if (array_key_exists('project_id', $validated)) {
            $project = $this->resolveProjectForWrite($user, $validated['project_id']);
            $validated['project_id'] = $project->id;
        }

        if (array_key_exists('assigned_to', $validated)) {
            $validated['assigned_to'] = $this->resolveAssigneeForWrite($user, $validated['assigned_to']);
        }

        $validated['updated_by'] = $user->id;

        $task->update($validated);

        return response()->json($task->fresh()->load(['project', 'assignedTo']));
    }

    public function destroy(Request $request, Tasks $task): JsonResponse
    {
        $this->authorizeTaskAccess($request->user(), $task);

        $task->update(['deleted_by' => $request->user()->id]);
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    private function authorizeProjectAccess($user, Projects $project): void
    {
        if (
            !$user->hasRole('admin') &&
            $project->created_by !== $user->id &&
            $project->user_id !== $user->id
        ) {
            abort(403, 'Forbidden');
        }
    }

    private function authorizeTaskAccess($user, Tasks $task): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $task->loadMissing('project');

        $hasAccess = $task->created_by === $user->id
            || $task->assigned_to === $user->id
            || optional($task->project)->created_by === $user->id
            || optional($task->project)->user_id === $user->id;

        if (!$hasAccess) {
            abort(403, 'Forbidden');
        }
    }

    private function resolveProjectForWrite($user, ?int $projectId): Projects
    {
        if ($projectId) {
            $project = Projects::query()->findOrFail($projectId);
            $this->authorizeProjectAccess($user, $project);

            return $project;
        }

        return Projects::query()->firstOrCreate(
            [
                'name' => 'Personal Workspace',
                'user_id' => $user->id,
            ],
            [
                'description' => 'Auto-created personal project',
                'created_by' => $user->id,
            ]
        );
    }

    private function resolveAssigneeForWrite($user, ?int $assigneeId): ?int
    {
        if ($user->hasRole('admin')) {
            if ($assigneeId) {
                $this->assertAssigneeIsActive($assigneeId);
            }

            return $assigneeId;
        }

        if ($user->hasRole('manager')) {
            $targetId = $assigneeId ?? $user->id;

            if ($targetId === $user->id) {
                return $targetId;
            }

            if (!$this->canManagerAssignUser($user, $targetId)) {
                abort(403, 'Managers can assign tasks only to staff users.');
            }

            $this->assertAssigneeIsActive($targetId);
            return $targetId;
        }

        $targetId = $assigneeId ?? $user->id;
        if ($targetId !== $user->id) {
            abort(403, 'You can only assign tasks to yourself.');
        }

        return $user->id;
    }

    private function assertAssigneeIsActive(int $assigneeId): void
    {
        $assignee = User::query()->findOrFail($assigneeId);
        if ($assignee->isCurrentlySuspended()) {
            abort(422, 'Cannot assign tasks to a suspended user.');
        }
    }

    private function canManagerAssignUser(User $manager, int $assigneeId): bool
    {
        $isMappedToManager = ManagerSubordinate::query()
            ->where('manager_id', $manager->id)
            ->where('subordinate_id', $assigneeId)
            ->exists();

        if (!$isMappedToManager) {
            return false;
        }

        return User::query()
            ->whereKey($assigneeId)
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['admin', 'manager']);
            })
            ->exists();
    }
}
