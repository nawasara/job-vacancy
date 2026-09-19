<?php

namespace Nawasara\JobVacancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // List + detail job vacancies (read-only from the upstream service)
            'job.vacancy.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::firstOrCreate([
            'name'       => 'job-vacancy',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);
    }
}