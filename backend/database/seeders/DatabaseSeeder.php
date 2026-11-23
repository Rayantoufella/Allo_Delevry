<?php

namespace Database\Seeders;

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
        // Jeu de démonstration fictif et cohérent (voir README).
        // Les autres seeders (données Faker en volume) restent appelables :
        // php artisan db:seed --class=UserSeeder, etc.
        $this->call(DemoSeeder::class);
    }
}
