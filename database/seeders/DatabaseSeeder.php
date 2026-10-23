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

        $this->seedUser($administrators, [
            'name' => 'Michal',
            'surname' => 'Virt',
            'email' => 'michal.virt@gmail.com',
            'phone' => '+420777696436',
        ]);
        $this->seedUser($planners, [
            'name' => 'Ivo',
            'surname' => 'Mechel',
            'email' => 'ivo.mechel@highlite.cz',
            'phone' => '+420602542777',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Jan',
            'surname' => 'Salát',
            'email' => 'jan.salat@example.net',
            'phone' => '+420777123456',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Jan',
            'surname' => 'Hrudka',
            'email' => 'jan.hrudka@example.net',
            'phone' => '+420777123456',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Danie',
            'surname' => 'Čechtický',
            'email' => 'daniel.cechticky@example.net',
            'phone' => '+420777123456',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Matyáš',
            'surname' => 'Nejedlý',
            'email' => 'matyas.nejedly@example.net',
            'phone' => '+420777123456',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Martin',
            'surname' => 'Příhoda',
            'email' => 'martin.prihoda@example.net',
            'phone' => '+420777123456',
        ]);
        $this->seedUser($viewers, [
            'name' => 'Martin',
            'surname' => 'Vála',
            'email' => 'martin.vala@example.net',
            'phone' => '+420777123456',
        ]);
    }

    /**
     * @param  array{name: string, surname: string, email: string, phone: string}  $attributes
     */
    private function seedUser(PermissionGroup $group, array $attributes): void
    {
        if (User::query()->where('email', $attributes['email'])->exists()) {
            return;
        }

        User::factory()->for($group)->create($attributes);
    }
}
