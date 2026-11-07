<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ItemType;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Router', 'Switch', 'Adaptor/POE/PSU', 'Access Point', 'Modem',
            'Modul/SFP', 'Part', 'Radio', 'Antena', 'CCTV', 'Converter',
            'Kabel', 'Laptop', 'Memory', 'Controller', 'Box',
        ];

        foreach ($types as $name) {
            ItemType::firstOrCreate(['name' => $name]);
        }
    }
}
