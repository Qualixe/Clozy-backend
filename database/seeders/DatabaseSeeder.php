<?php

namespace Database\Seeders;

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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Dashboard staff accounts. updateOrCreate keeps this seeder safe to
        // re-run without failing on duplicate emails.
        User::updateOrCreate(
            ['email' => 'admin@clozy.com'],
            ['name' => 'Admin', 'password' => 'password123', 'role' => 'admin']
        );

        User::updateOrCreate(
            ['email' => 'editor@clozy.com'],
            ['name' => 'Test Editor', 'password' => 'password123', 'role' => 'editor']
        );

        $this->call(ProductSeeder::class);
        $this->call(HeroSlideSeeder::class);
    }
}
