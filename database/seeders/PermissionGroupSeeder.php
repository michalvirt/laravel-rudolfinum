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
        foreach ([
            PermissionGroup::factory()->administrators()->raw(),
            PermissionGroup::factory()->planners()->raw(),
            PermissionGroup::factory()->viewers()->raw(),
        ] as $attributes) {
            PermissionGroup::query()->firstOrCreate(
                ['name' => $attributes['name']],
                $attributes,
            );
        }
    }
}
