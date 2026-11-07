<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['location_id', 'name']);
        });

        // Backfill racks from existing items (Warehouse -> Rack)
        DB::table('items')->whereNotNull('rack')->where('rack', '!=', '')
            ->select('location_id', 'rack')->distinct()->get()
            ->each(fn ($r) => DB::table('racks')->insertOrIgnore([
                'location_id' => $r->location_id, 'name' => trim($r->rack),
                'created_at' => now(), 'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
