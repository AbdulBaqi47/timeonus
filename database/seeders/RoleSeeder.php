<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access', 'is_system' => true],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Manage company settings and reports', 'is_system' => true],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Oversees departments and approvals', 'is_system' => true],
            ['name' => 'Team Lead', 'slug' => 'team_lead', 'description' => 'Handles team escalations and help sessions', 'is_system' => true],
            ['name' => 'Team Member', 'slug' => 'team', 'description' => 'Standard employee role', 'is_system' => true],
            ['name' => 'HR', 'slug' => 'hr', 'description' => 'Manages attendance and salary adjustments', 'is_system' => true],
            ['name' => 'Finance', 'slug' => 'finance', 'description' => 'Reviews salary runs and payouts', 'is_system' => false],
            ['name' => 'Support', 'slug' => 'support', 'description' => 'Responds to help desk escalations', 'is_system' => false],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                $role,
            );
        }
    }
}
