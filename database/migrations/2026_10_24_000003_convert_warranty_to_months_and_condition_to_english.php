<?php

use App\Support\Warranty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $conditions = [
        'Indikasi port rusak' => 'Port damage suspected',
        'Indikasi led power' => 'Power LED issue suspected',
        'Indikasi mati total' => 'Total failure suspected',
        'Indikasi Sparepart Rusak' => 'Spare part damage suspected',
        'Baru' => 'New',
        'Bekas' => 'Used',
        'Rusak' => 'Damaged',
    ];

    public function up(): void
    {
        foreach ($this->conditions as $old => $new) {
            DB::table('items')->where('condition', $old)->update(['condition' => $new]);
        }

        foreach (['items', 'inventories', 'purchase_order_items'] as $table) {
            DB::table($table)->whereNotNull('warranty')->select('id', 'warranty')->orderBy('id')->get()->each(function ($row) use ($table) {
                $months = Warranty::normalize($row->warranty);
                if ($months !== null && $months !== $row->warranty) {
                    DB::table($table)->where('id', $row->id)->update(['warranty' => $months]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->conditions as $old => $new) {
            DB::table('items')->where('condition', $new)->update(['condition' => $old]);
        }
    }
};
