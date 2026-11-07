<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk demo. Tidak dipanggil DatabaseSeeder; jalankan manual:
 * php artisan db:seed --class=DemoItemSeeder
 */
class DemoItemSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);

        $catalog = [
            ['RB951Ui-2nD', 'Router', 'Mikrotik', 'RTR'],
            ['hAP ac2', 'Router', 'Mikrotik', 'RTR'],
            ['CRS326-24G-2S+', 'Switch', 'Mikrotik', 'SWT'],
            ['TL-SG1024D', 'Switch', 'TP-Link', 'SWT'],
            ['U6-Lite', 'Access Point', 'Ubiquiti', 'AP'],
            ['EAP225', 'Access Point', 'TP-Link', 'AP'],
            ['S+RJ10', 'Modul/SFP', 'Mikrotik', 'SFP'],
            ['SFP 1.25G SM', 'Modul/SFP', 'Generic', 'SFP'],
            ['LHG 5 ac', 'Radio', 'Mikrotik', 'RAD'],
            ['LiteBeam 5AC Gen2', 'Radio', 'Ubiquiti', 'RAD'],
            ['DS-2CE16D0T', 'CCTV', 'Hikvision', 'CAM'],
            ['POE 24V 1A', 'Adaptor/POE/PSU', 'Ubiquiti', 'PSU'],
            ['UTP Cat6 305m', 'Kabel', 'Belden', 'KBL'],
            ['ThinkPad E14', 'Laptop', 'Lenovo', 'LPT'],
            ['HG8245H5', 'Modem', 'Huawei', 'MDM'],
        ];

        $locations = Location::pluck('id', 'name');
        $types = ItemType::pluck('id', 'name');
        $weights = ['Lantai 1' => 34, 'Lantai 2' => 24, 'Lantai 3' => 20, 'Stuff In' => 17, 'Rak Barang Rusak' => 5];
        $counter = [];

        for ($i = 0; $i < 160; $i++) {
            [$name, $type, $brand, $code] = $catalog[mt_rand(0, count($catalog) - 1)];
            $location = $this->pick($weights);

            $status = match (true) {
                $location === 'Rak Barang Rusak' => 'Broken',
                $location === 'Stuff In' => $this->pick(['Has not been tested' => 5, 'In Stock' => 3, 'Instalation' => 2]),
                default => $this->pick(['In Stock' => 70, 'Borrow' => 10, 'Instalation' => 8, 'Broken' => 3, 'Sold Out' => 5, 'ATK' => 4]),
            };

            $stuffIn = now()->subDays(mt_rand(0, 175));
            $stuffOut = in_array($status, ['Sold Out', 'Instalation']) ? $stuffIn->copy()->addDays(mt_rand(3, 40)) : null;
            if ($stuffOut && $stuffOut->isFuture()) {
                $stuffOut = null;
            }

            $counter[$code] = ($counter[$code] ?? 0) + 1;

            Item::create([
                'item_id' => sprintf('MJG-%s-%04d', $code, $counter[$code]),
                'item_name' => $name,
                'part_number' => $name,
                'type_id' => $types[$type],
                'serial_number' => strtoupper(bin2hex(random_bytes(5))),
                'merek' => $brand,
                'status' => $status,
                'condition' => $status === 'Broken' ? $this->pick(['Port damage suspected' => 1, 'Total failure suspected' => 1, 'Damaged' => 1]) : $this->pick(['New' => 3, 'Normal' => 5, 'Used' => 2]),
                'stuff_in' => $stuffIn->toDateString(),
                'stuff_out' => $stuffOut?->toDateString(),
                'no_wo' => $status === 'Instalation' ? 'WO-' . mt_rand(1000, 9999) : null,
                'no_po_spr' => 'PO-' . mt_rand(10000, 99999),
                'warranty' => $this->pick(['12' => 5, '1' => 2, '0' => 3]),
                'location_id' => $locations[$location],
            ]);
        }
    }

    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
