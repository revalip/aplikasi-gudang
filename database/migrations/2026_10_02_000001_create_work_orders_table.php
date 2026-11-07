<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number')->unique();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->date('wo_date');
            $table->string('ref_number')->nullable();     // no. surat referensi dari customer (STA/PO/...)
            $table->string('ref_company')->nullable();
            $table->string('work_type', 50)->default('DELIVERY');
            $table->date('po_date')->nullable();
            $table->date('rfs_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('business_type', 50)->default('NEW-PO');
            $table->string('subject');

            $table->string('cust_name')->nullable();
            $table->string('cust_position')->nullable();
            $table->string('cust_phone')->nullable();
            $table->string('cust_email')->nullable();
            $table->text('cust_address')->nullable();

            $table->string('user_name')->nullable();
            $table->string('user_position')->nullable();
            $table->string('user_phone')->nullable();
            $table->string('user_email')->nullable();
            $table->text('user_address')->nullable();

            $table->string('sales_name')->nullable();
            $table->string('sales_title', 50)->default('SALES');
            $table->string('approver_name')->nullable();
            $table->string('approver_title', 50)->default('OPERATIONAL MANAGER');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('type_name');
            $table->string('part_number')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('sow_explanation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
    }
};
