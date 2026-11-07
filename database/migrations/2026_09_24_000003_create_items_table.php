<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique();
            $table->string('item_name');
            $table->foreignId('type_id')->constrained('item_types'); // ganti dari string 'type' -> relasi ke item_types
            $table->string('serial_number')->nullable();
            $table->string('merek')->nullable();
            $table->string('status');
            $table->string('condition');
            $table->date('stuff_in')->nullable();
            $table->date('stuff_out')->nullable();
            $table->string('no_wo')->nullable();
            $table->string('no_po_spr')->nullable();
            $table->string('warranty')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('location_id')->constrained('locations');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
