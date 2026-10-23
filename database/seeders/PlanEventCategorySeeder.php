<?php

namespace Database\Seeders;

use App\Models\PlanEventCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanEventCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default plan event categories.
     */
    public function run(): void
    {
        $categories = [
            ['label' => 'Koncert', 'background_color' => '#1565c0', 'font_color' => '#ffffff'],
            ['label' => 'Zkouška', 'background_color' => '#2e7d32', 'font_color' => '#ffffff'],
            ['label' => 'Instalace', 'background_color' => '#ef6c00', 'font_color' => '#000000'],
        ];

        foreach ($categories as $category) {
            PlanEventCategory::firstOrCreate(['label' => $category['label']], $category);
        }
    }
}
