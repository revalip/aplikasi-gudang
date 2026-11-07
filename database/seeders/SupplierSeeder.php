<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'PT Mikrotik Indonesia Distributor', 'phone' => '021-5550101'],
            ['name' => 'Ubiquiti Store Jakarta', 'phone' => '021-5550202'],
            ['name' => 'Ruijie Partner Indonesia', 'phone' => '021-5550303'],
            ['name' => 'Hikvision Authorized Dealer', 'phone' => '021-5550404'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['name' => $s['name']], $s);
        }
    }
}
