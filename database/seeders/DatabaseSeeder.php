<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Dashboard users are created with: php artisan admin:create
     */
    public function run(): void
    {
        $this->call(CompetitionSeeder::class);
    }
}
