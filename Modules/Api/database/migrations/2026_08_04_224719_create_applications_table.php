<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADM/application (sql_schema:table)
     * Database.Schema.Table: EQADM.dbo.application
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->integer('application_id');
            $table->integer('institution_id');
            $table->string('representative_signature', 100)->nullable();
            $table->integer('application_workflow_id')->nullable();
            $table->smallInteger('sabc_designation')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->integer('application_status')->nullable();
            $table->smallInteger('renewal_due')->nullable();
            $table->smallInteger('renewed')->nullable();
            $table->smallInteger('logo_terms_checked')->nullable();
            $table->smallInteger('logo_terms_checked2')->nullable();
            $table->smallInteger('logo_terms_checked3')->nullable();
            $table->string('other_logos', 2000)->nullable();
            $table->string('affiliations', 500)->nullable();
            $table->string('brand_signature', 100)->nullable();
            $table->string('approved_date', 30)->nullable();
            $table->string('approved_by', 80)->nullable();
            $table->string('legacy_created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->integer('created_id')->nullable();
            $table->string('submitted_at', 20)->nullable();
            $table->string('submitted_by', 80)->nullable();
            $table->string('legacy_updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->smallInteger('multiple_year')->nullable();
            $table->string('valid_year', 80)->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('application_workflow_id');
            $table->index('application_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};