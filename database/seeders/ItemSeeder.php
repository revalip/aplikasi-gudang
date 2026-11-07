<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    // Pool barang per type: [merek => [model, ...]]
    protected array $pool = [
        'Router' => [
            'Mikrotik' => ['RB951Ui-2nD', 'hAP ac2', 'RB750Gr3', 'RB1100AHx4', 'CCR1009-7G-1C-1S+'],
            'Ruijie' => ['RG-EG105G-P', 'RG-EG210G-P', 'RG-EW300'],
        ],
        'Switch' => [
            'Mikrotik' => ['CRS326-24G-2S+', 'CSS326-24G-2S+'],
            'Ruijie' => ['RG-S1920-24GT2SFP', 'RG-NBS3100-24GT4SFP'],
        ],
        'Access Point' => [
            'Ubiquiti' => ['U6-Lite', 'U6-Pro', 'UAP-AC-Pro', 'UAP-AC-LR'],
            'Ruijie' => ['RG-AP720-L', 'RG-AP630(IDA)'],
        ],
        'Modem' => [
            'Huawei' => ['HG8245H'],
            'ZTE' => ['F609'],
        ],
        'Modul/SFP' => [
            'Mikrotik' => ['S+RJ10', 'S-3553LC20D'],
            'Generic' => ['SFP-10G-SR'],
        ],
        'Radio' => [
            'Mikrotik' => ['LHG 5 ac', 'LHG XL 5 ac', 'SXTsq 5 ac'],
        ],
        'Antena' => [
            'Ubiquiti' => ['airMAX Sector 90'],
            'TP-Link' => ['TL-ANT2415D'],
        ],
        'CCTV' => [
            'Hikvision' => ['DS-2CE16D0T', 'DS-2CD1023G0'],
            'Dahua' => ['HAC-HFW1200'],
        ],
        'Converter' => [
            'TP-Link' => ['MC200CM'],
        ],
        'Kabel' => [
            'Belden' => ['UTP Cat6 305m'],
            'Generic' => ['Fiber Optic SM 2 Core'],
        ],
        'Laptop' => [
            'Lenovo' => ['ThinkPad E14'],
            'Dell' => ['Latitude 5420'],
        ],
        'Memory' => [
            'Kingston' => ['8GB DDR4', '16GB DDR4'],
        ],
        'Controller' => [
            'Ubiquiti' => ['UniFi Cloud Key Gen2'],
        ],
        'Box' => [
            'Generic' => ['ODP 8 Core', 'ODC 12 Core'],
        ],
        'Part' => [
            'Generic' => ['Pigtail SC-APC', 'Konektor RJ45'],
        ],
        'Adaptor/POE/PSU' => [
            'Mikrotik' => ['24V PoE Adapter'],
            'Ubiquiti' => ['PoE Injector 24V'],
        ],
    ];

    public function run(): void
    {
        $prefixes = config('item_options.id_prefixes'); // Prefix Item ID per type (MJG-RTR-xxxx dst)

        // Jumlah barang per lokasi (contoh angka realistis 95-150 per lokasi)
        $counts = [
            'Lantai 1' => 97,
            'Lantai 2' => 100,
            'Lantai 3' => 115,
            'Stuff In' => 95,
            'Rak Barang Rusak' => 148,
        ];

        $types = ItemType::pluck('id', 'name'); // ['Router' => 1, 'Switch' => 2, ...]
        $counter = []; // penomoran urut per prefix, biar Item ID ga bentrok

        foreach ($counts as $locationName => $total) {
            $location = Location::where('name', $locationName)->first();
            if (! $location) {
                continue;
            }

            for ($i = 0; $i < $total; $i++) {
                $typeName = array_rand($this->pool);
                $typeId = $types[$typeName] ?? null;
                if (! $typeId) {
                    continue;
                }

                $brand = array_rand($this->pool[$typeName]);
                $model = $this->pool[$typeName][$brand][array_rand($this->pool[$typeName][$brand])];

                $prefix = $prefixes[$typeName] ?? 'ITM';
                $counter[$prefix] = ($counter[$prefix] ?? 0) + 1;
                $itemId = sprintf('MJG-%s-%04d', $prefix, $counter[$prefix]);

                [$status, $condition] = $this->statusForLocation($locationName);

                Item::create([
                    'item_id' => $itemId,
                    'item_name' => $model,
                    'part_number' => $model,
                    'type_id' => $typeId,
                    'serial_number' => $this->fakeSerial(),
                    'merek' => $brand,
                    'status' => $status,
                    'condition' => $condition,
                    'stuff_in' => now()->subDays(rand(1, 200))->format('Y-m-d'),
                    'stuff_out' => null,
                    'no_wo' => rand(0, 1) ? 'WO-' . rand(1000, 9999) : null,
                    'no_po_spr' => rand(0, 1) ? 'PO-' . rand(1000, 9999) : null,
                    'warranty' => fake()->randomElement(['1', '12', '0']),
                    'notes' => null,
                    'location_id' => $location->id,
                ]);
            }
        }
    }

    protected function statusForLocation(string $locationName): array
    {
        return match ($locationName) {
            'Rak Barang Rusak' => [
                fake()->randomElement(['Broken', 'Retur device to supplier', 'Re-purchase needed']),
                fake()->randomElement(['Port damage suspected', 'Power LED issue suspected', 'Total failure suspected', 'Damaged']),
            ],
            'Stuff In' => [
                fake()->randomElement(['Has not been tested', 'In Stock', 'ATK']),
                fake()->randomElement(['New', 'Normal']),
            ],
            default => [
                fake()->randomElement(['In Stock', 'In Stock', 'In Stock', 'Borrow', 'Instalation', 'ATK']),
                fake()->randomElement(['Normal', 'Normal', 'New', 'Used']),
            ],
        };
    }

    protected function fakeSerial(): string
    {
        // Format mirip MAC address, sesuai pola di screenshot (mis. HG709F2K1A)
        return strtoupper(fake()->bothify('??########??'));
    }
}
