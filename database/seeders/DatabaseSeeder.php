<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\ManagerSubordinate;
use App\Models\Projects;
use App\Models\Role;
use App\Models\Announcement;
use App\Models\SystemTheme;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET session_replication_role = replica;');

        $managementDepartment = Department::query()->firstOrCreate(['name' => 'Management']);
        $securityDepartment = Department::query()->firstOrCreate(['name' => 'Security']);
        $kitchenDepartment = Department::query()->firstOrCreate(['name' => 'Kitchen']);
        $staffDepartment = Department::query()->firstOrCreate(['name' => 'Staff']);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $managerRole = Role::query()->firstOrCreate(['name' => 'manager']);
        $userRole = Role::query()->firstOrCreate(['name' => 'user']);
        $watchmanRole = Role::query()->firstOrCreate(['name' => 'watchman']);
        $chefRole = Role::query()->firstOrCreate(['name' => 'chef']);
        $staffRole = Role::query()->firstOrCreate(['name' => 'staff']);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password123',
                'department_id' => $managementDepartment->id,
            ]
        );

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => 'password123',
                'department_id' => $managementDepartment->id,
            ]
        );

        $watchman = User::query()->updateOrCreate(
            ['email' => 'watchman@example.com'],
            [
                'name' => 'Watchman User',
                'password' => 'password123',
                'department_id' => $securityDepartment->id,
            ]
        );

        $chef = User::query()->updateOrCreate(
            ['email' => 'chef@example.com'],
            [
                'name' => 'Chef User',
                'password' => 'password123',
                'department_id' => $kitchenDepartment->id,
            ]
        );

        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'password' => 'password123',
                'department_id' => $staffDepartment->id,
            ]
        );

        $admin->roles()->sync([$adminRole->id]);
        $manager->roles()->sync([$managerRole->id, $userRole->id]);
        $watchman->roles()->sync([$watchmanRole->id, $userRole->id]);
        $chef->roles()->sync([$chefRole->id, $userRole->id]);
        $staff->roles()->sync([$staffRole->id, $userRole->id]);

        $users = collect([$admin, $manager, $watchman, $chef, $staff]);

        foreach ($users as $user) {
            Projects::query()->firstOrCreate(
                ['name' => 'Personal Workspace', 'user_id' => $user->id],
                ['description' => 'Default seeded project', 'created_by' => $user->id]
            );
        }

        ManagerSubordinate::query()->firstOrCreate([
            'manager_id' => $manager->id,
            'subordinate_id' => $watchman->id,
        ]);

        ManagerSubordinate::query()->firstOrCreate([
            'manager_id' => $manager->id,
            'subordinate_id' => $chef->id,
        ]);

        ManagerSubordinate::query()->firstOrCreate([
            'manager_id' => $manager->id,
            'subordinate_id' => $staff->id,
        ]);

        $managerProject = Projects::query()->where('user_id', $manager->id)->first();
        $watchmanProject = Projects::query()->where('user_id', $watchman->id)->first();
        $chefProject = Projects::query()->where('user_id', $chef->id)->first();

        Tasks::query()->updateOrCreate(
            ['title' => 'Night gate check', 'created_by' => $manager->id],
            [
                'project_id' => $managerProject->id,
                'description' => 'Manager-assigned night shift checklist',
                'status' => 'pending',
                'due_date' => now()->addDay(),
                'user_id' => $manager->id,
                'assigned_to' => $watchman->id,
                'updated_by' => $manager->id,
            ]
        );

        Tasks::query()->updateOrCreate(
            ['title' => 'Prepare lunch menu', 'created_by' => $manager->id],
            [
                'project_id' => $managerProject->id,
                'description' => 'Manager-assigned kitchen prep',
                'status' => 'in_progress',
                'due_date' => now()->addDays(2),
                'user_id' => $manager->id,
                'assigned_to' => $chef->id,
                'updated_by' => $manager->id,
            ]
        );

        Tasks::query()->updateOrCreate(
            ['title' => 'Submit security report', 'created_by' => $watchman->id],
            [
                'project_id' => $watchmanProject->id,
                'description' => 'Self-managed daily report',
                'status' => 'pending',
                'due_date' => now()->addDay(),
                'user_id' => $watchman->id,
                'assigned_to' => $watchman->id,
                'updated_by' => $watchman->id,
            ]
        );

        Tasks::query()->updateOrCreate(
            ['title' => 'Inventory check', 'created_by' => $chef->id],
            [
                'project_id' => $chefProject->id,
                'description' => 'Self-managed stock audit',
                'status' => 'pending',
                'due_date' => now()->addDay(),
                'user_id' => $chef->id,
                'assigned_to' => $chef->id,
                'updated_by' => $chef->id,
            ]
        );

        Announcement::query()->updateOrCreate(
            ['title' => 'Weekly Safety Briefing'],
            [
                'message' => 'All departments should complete the Friday safety checklist before signoff.',
                'type' => 'warning',
                'target_scope' => 'all',
                'is_pinned' => true,
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        SystemTheme::query()->updateOrCreate(
            ['name' => 'Execution Week'],
            [
                'tagline' => 'Precision in every task',
                'banner_message' => 'Focus on completion quality and on-time delivery this week.',
                'primary_color' => '#0f172a',
                'accent_color' => '#2563eb',
                'surface_color' => '#ffffff',
                'is_active' => true,
                'starts_at' => now()->startOfWeek(),
                'ends_at' => now()->endOfWeek(),
                'created_by' => $admin->id,
            ]
        );

        DB::statement('SET session_replication_role = DEFAULT;');
    }
}
