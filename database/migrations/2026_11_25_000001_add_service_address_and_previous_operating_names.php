<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Application form: the institution confirms its address for service (saved on the application) and
// lists the operating names it used in the last 5 years. Previous operating names belong to the
// institution and to the application that declared them (one-to-many from each).
return new class extends Migration
{
    private const SERVICE_COLUMNS = [
        'service_street1', 'service_street2', 'service_city', 'service_province', 'service_postal_code', 'service_country',
    ];

    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'service_street1')) {
            Schema::table('applications', function (Blueprint $table): void {
                foreach (self::SERVICE_COLUMNS as $column) {
                    $table->string($column)->nullable();
                }
                $table->boolean('service_address_confirmed')->default(false);
            });
        }

        if (! Schema::hasTable('previous_operating_names')) {
            Schema::create('previous_operating_names', function (Blueprint $table): void {
                $table->id();
                $table->uuid('institution_crm_id')->index();
                $table->uuid('application_crm_id')->nullable()->index();
                $table->string('name');
                $table->date('used_from');
                $table->date('used_to')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('previous_operating_names');

        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'service_street1')) {
            Schema::table('applications', function (Blueprint $table): void {
                $table->dropColumn([...self::SERVICE_COLUMNS, 'service_address_confirmed']);
            });
        }
    }
};
