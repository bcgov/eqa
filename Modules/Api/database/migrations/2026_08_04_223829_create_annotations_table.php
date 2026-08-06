<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/AnnotationBase (sql_schema, table)
     */
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->uuid('annotation_id')->primary();
            $table->integer('object_type_code')->nullable();
            $table->uuid('object_id')->nullable();
            $table->uuid('owning_business_unit')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->boolean('is_document');
            $table->text('note_text')->nullable();
            $table->string('mime_type', 512)->nullable();
            $table->string('lang_id', 4)->nullable();
            $table->text('document_body')->nullable();
            $table->timestamp('created_on', 3)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('file_name', 510)->nullable();
            $table->uuid('created_by')->nullable();
            $table->boolean('is_private')->nullable();
            $table->uuid('modified_by')->nullable();
            $table->timestamp('modified_on', 3)->nullable();
            $table->binary('version_number')->nullable();
            $table->string('step_id', 64)->nullable();
            $table->timestamp('overridden_created_on', 3)->nullable();
            $table->integer('import_sequence_number')->nullable();
            $table->uuid('created_on_behalf_by')->nullable();
            $table->uuid('owner_id');
            $table->uuid('modified_on_behalf_by')->nullable();
            $table->integer('owner_id_type');

            $table->index('object_type_code');
            $table->index('object_id');
            $table->index('owning_business_unit');
            $table->index('created_by');
            $table->index('modified_by');
            $table->index('created_on_behalf_by');
            $table->index('owner_id');
            $table->index('modified_on_behalf_by');
            $table->index('created_on');
            $table->index('modified_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};