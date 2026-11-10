<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// created_at / updated_at filled by the database, so query-builder writes are stamped too (not only Eloquent).
return new class extends Migration
{
    /** App tables created without timestamps. */
    private array $missing = ['institutions', 'applications', 'campuses', 'institution_users', 'dbas', 'invoices', 'role_user'];

    /** Tables that already had timestamps; they also get the updated_at trigger. */
    private array $existing = ['users', 'roles', 'email_templates', 'email_settings', 'sent_emails', 'payment_transactions'];

    public function up(): void
    {
        foreach ($this->missing as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            $columns = array_values(array_filter(['created_at', 'updated_at'], fn (string $c) => ! Schema::hasColumn($name, $c)));
            if ($columns === []) {
                continue;
            }

            // Added without a default first so existing (migrated) rows stay NULL rather than getting today's date.
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->timestamp($column)->nullable();
                }
            });
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->timestamp($column)->nullable()->useCurrent()->change();
                }
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Keeps an updated_at the statement set explicitly; otherwise stamps the update time.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION set_updated_at() RETURNS trigger AS $$
            BEGIN
                IF NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at THEN
                    NEW.updated_at := CURRENT_TIMESTAMP;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        foreach ([...$this->missing, ...$this->existing] as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'updated_at')) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}_set_updated_at ON \"{$name}\"");
                DB::unprepared("CREATE TRIGGER {$name}_set_updated_at BEFORE UPDATE ON \"{$name}\" FOR EACH ROW EXECUTE PROCEDURE set_updated_at()");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ([...$this->missing, ...$this->existing] as $name) {
                if (Schema::hasTable($name)) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$name}_set_updated_at ON \"{$name}\"");
                }
            }
            DB::unprepared('DROP FUNCTION IF EXISTS set_updated_at()');
        }

        foreach ($this->missing as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            $columns = array_values(array_filter(['created_at', 'updated_at'], fn (string $c) => Schema::hasColumn($name, $c)));
            if ($columns !== []) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
