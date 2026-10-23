<?php

namespace Database\Seeders;

use App\Models\PermissionGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default permission groups.
     */
    public function run(): void
    {
        PermissionGroup::factory()->administrators()->create();
        PermissionGroup::factory()->planners()->create();
        PermissionGroup::factory()->viewers()->create();
    }
}
