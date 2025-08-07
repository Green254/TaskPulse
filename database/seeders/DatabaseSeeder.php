<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Tasks;
use App\Models\Projects;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Disable FK checks temporarily for clean seeding
        DB::statement('SET session_replication_role = replica;');

        // 1. Roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        // 2. Users
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $regularUsers = User::factory(4)->create();

        // 3. Assign Roles
        $admin->roles()->attach($adminRole->id);
        foreach ($regularUsers as $user) {
            $user->roles()->attach($userRole->id);
        }

        // 4. Projects (2 per user)
        foreach (User::all() as $user) {
            Projects::factory(2)->create([
                'user_id' => $user->id,
            ]);
        }

        // 5. Tasks (3 per user, linked to their projects)
        foreach (User::all() as $user) {
            $projects = $user->projects;

            if ($projects->count() > 0) {
                Tasks::factory(3)->create([
                    'user_id' => $user->id,
                    'project_id' => $projects->random()->id,
                ]);
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET session_replication_role = DEFAULT;');
    }
}
