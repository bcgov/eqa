<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADM.dbo.payment (sql_schema table EQADM/payment)
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->integer('payment_type');
            $table->decimal('payment_amount', 19, 2);
            $table->string('payment_date', 30);
            $table->string('transaction_ref_number', 40)->nullable();
            $table->string('auth_code', 50)->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};