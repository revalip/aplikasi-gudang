<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();          // snapshot: tetap kebaca walau user dihapus / login gagal
            $table->string('event', 40)->index();             // login, logout, created, updated, deleted, status_changed, print, export, import, scan ...
            $table->string('subject_type', 60)->nullable();   // nama model (Sales Order, Barang, ...)
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();      // SO-2026-00001, MJG-RTR-0001, ...
            $table->string('description');
            $table->json('properties')->nullable();           // {old:{}, new:{}} atau data tambahan
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
