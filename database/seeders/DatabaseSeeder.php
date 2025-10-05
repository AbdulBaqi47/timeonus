<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\OfficeLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $hq = OfficeLocation::query()->firstOrCreate([
            'code' => 'HQ-KHI',
        ], [
            'name' => 'Headquarters Karachi',
            'timezone' => 'Asia/Karachi',
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'radius_meters' => 200,
            'address' => 'Karachi Central, PK',
            'business_hours' => [
                'monday' => ['09:00', '18:00'],
                'tuesday' => ['09:00', '18:00'],
                'wednesday' => ['09:00', '18:00'],
                'thursday' => ['09:00', '18:00'],
                'friday' => ['09:00', '18:00'],
            ],
        ]);

        $admin = User::query()->firstOrCreate([
            'email' => 'superadmin@timeonus.test',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('ChangeMe123!'),
        ]);

        $admin->assignRole('super_admin');

        $superAdminRoleId = Role::query()->where('slug', 'super_admin')->value('id');

        EmployeeProfile::query()->updateOrCreate([
            'user_id' => $admin->getKey(),
        ], [
            'employee_code' => 'EMP-0001',
            'job_title' => 'Founder',
            'primary_role_id' => $superAdminRoleId,
            'timezone' => 'Asia/Karachi',
            'default_office_location_id' => $hq->getKey(),
            'expected_start_time' => '09:00:00',
            'expected_end_time' => '18:00:00',
        ]);
    }
}