<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columns for the payment / compliance order / appeal workflow (flowchart DRAFT V1.1 red boxes).
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [column => type] */
    private array $columns = [
        'invoices' => [
            'fee_type' => 'string',
            'due_date' => 'date',
        ],
        'applications' => [
            'compliance_order' => 'boolean',
            'compliance_order_notes' => 'text',
            'changes_requested' => 'text',
            'changes_due_date' => 'date',
            'appeal_deadline' => 'date',
            'appeal_date' => 'date',
            'appeal_decision' => 'string',
            'appeal_decision_date' => 'date',
        ],
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column => $type) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->{$type}($column)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $existing = array_values(array_filter(array_keys($columns), fn (string $c) => Schema::hasColumn($tableName, $c)));
            if ($existing !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
