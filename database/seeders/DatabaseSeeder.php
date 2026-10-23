<?php

namespace Database\Seeders;

use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionGroupSeeder::class);
        $this->call(PlanEventCategorySeeder::class);

        $administrators = PermissionGroup::where('name', 'Administrators')->firstOrFail();
        $planners = PermissionGroup::where('name', 'Planners')->firstOrFail();
        $viewers = PermissionGroup::where('name', 'Viewers')->firstOrFail();

        User::factory()->for($administrators)->create([
            'name' => 'Michal',
            'surname' => 'Virt',
            'email' => 'michal.virt@gmail.com',
            'phone' => '+420777696436',
        ]);
        User::factory()->for($planners)->create([
            'name' => 'Ivo',
            'surname' => 'Mechel',
            'email' => 'ivo.mechel@highlite.cz',
            'phone' => '+420602542777',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Jan',
            'surname' => 'Salát',
            'email' => 'jan.salat@example.net',
            'phone' => '+420777123456',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Jan',
            'surname' => 'Hrudka',
            'email' => 'jan.hrudka@example.net',
            'phone' => '+420777123456',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Danie',
            'surname' => 'Čechtický',
            'email' => 'daniel.cechticky@example.net',
            'phone' => '+420777123456',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Matyáš',
            'surname' => 'Nejedlý',
            'email' => 'matyas.nejedly@example.net',
            'phone' => '+420777123456',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Martin',
            'surname' => 'Příhoda',
            'email' => 'martin.prihoda@example.net',
            'phone' => '+420777123456',
        ]);
        User::factory()->for($viewers)->create([
            'name' => 'Martin',
            'surname' => 'Vála',
            'email' => 'martin.vala@example.net',
            'phone' => '+420777123456',
        ]);
    }
}
