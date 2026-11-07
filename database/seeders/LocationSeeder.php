<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Lantai 1', 'Lantai 2', 'Lantai 3', 'Stuff In', 'Rak Barang Rusak'] as $name) {
            Location::firstOrCreate(['name' => $name]);
        }
    }
}
