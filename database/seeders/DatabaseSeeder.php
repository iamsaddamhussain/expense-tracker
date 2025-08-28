<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $testUser = User::firstOrCreate([
            'email' => 'test@mail.com',
        ], [
            'name' => 'Test User',
            'email' => 'test@mail.com',
            'password' => bcrypt('12345678'),
        ]);

        $factoryUsers = User::factory(5)->create();
        $users = $factoryUsers->push($testUser);

        $categories = collect(['Groceries', 'Transport', 'Rent', 'Entertainment', 'Bills', 'Shopping', 'Health'])
            ->map(fn($name) => Category::firstOrCreate(['name' => $name]));

        Expense::factory(50)
            ->recycle($users)
            ->recycle($categories)
            ->create();
    }
}
