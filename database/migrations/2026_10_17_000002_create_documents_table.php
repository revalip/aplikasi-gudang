<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Arsip dokumen: tiap cetak (Word/PDF) & export Excel otomatis kesimpan di sini (file di storage/app/private/documents).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();   // Sales Order, Work Order, Delivery Order, Purchase Order, Laporan
            $table->string('reference')->nullable()->index(); // nomor dokumen sumber (SO-2026-00001) / nama laporan
            $table->nullableMorphs('documentable');
            $table->string('format', 10)->index();      // docx | pdf | xlsx
            $table->string('file_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
