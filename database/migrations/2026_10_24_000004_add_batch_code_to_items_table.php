<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Units entered together (one Add Item submit / one PO receipt / one import) share a batch code.
            $table->string('batch_code', 40)->nullable()->index()->after('rack');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['batch_code']);
            $table->dropColumn('batch_code');
        });
    }
};
