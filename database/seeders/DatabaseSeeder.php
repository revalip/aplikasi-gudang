<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(LocationSeeder::class);
        $this->call(ItemTypeSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(SupplierSeeder::class);

        User::updateOrCreate(
            ['email' => 'admin@mjg.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'superadmin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'ucupgank@mail.com'],
            [
                'name' => 'Ucup',
                'password' => bcrypt('ucup1234'),
                'role' => 'superadmin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'tester@mail.com'],
            [
                'name' => 'Tester',
                'password' => bcrypt('tester123'),
                'role' => 'superadmin',
            ]
        );
    }
}
