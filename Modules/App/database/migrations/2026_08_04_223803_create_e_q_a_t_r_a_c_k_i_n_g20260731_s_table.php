<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source lineage: sql_backup / database_backup "EQA_TRACKING-20260731"
     * (EQA_TRACKING-20260731.bak). This backup requires an external restore
     * before column-level introspection is possible:
     *   RESTORE DATABASE [EQA_TRACKING-20260731]
     *     FROM DISK = N'/var/www/html/_sources/db/EQA_TRACKING-20260731.bak'
     * then attach a SQL connection and set MIGRATION_SQL_CONNECTION so the
     * IR extractor can enumerate this database's tables/columns. No
     * ir_nodes were available at generation time, so this migration creates
     * a placeholder table capturing the source lineage only; it MUST be
     * reconciled against the restored schema before being trusted in
     * production.
     */
    public function up(): void
    {
        Schema::create('e_q_a_t_r_a_c_k_i_n_g20260731_s', function (Blueprint $table) {
            $table->id();

            // Placeholder columns pending restore of EQA_TRACKING-20260731.bak
            // and re-generation of this migration from the real IR nodes.
            $table->string('source_connector')->default('sql_backup');
            $table->string('source_kind')->default('database_backup');
            $table->string('source_name')->default('EQA_TRACKING-20260731');
            $table->string('source_ref')->default('EQA_TRACKING-20260731.bak');
            $table->boolean('requires_restore')->default(true);
            $table->jsonb('payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_q_a_t_r_a_c_k_i_n_g20260731_s');
    }
};