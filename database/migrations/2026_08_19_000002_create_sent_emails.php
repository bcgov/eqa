<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit log of every notification email the system actually dispatched, so the
// ministry can see (per institution) what was sent, to whom, and the exact
// subject/body. Populated by EmailNotificationService on each send. Additive +
// idempotent and NOT owned by the DynamicsDataMigrator, so history survives data
// reloads.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sent_emails')) {
            return;
        }

        Schema::create('sent_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('institution_crm_id')->nullable()->index();
            $table->string('template_key')->nullable();
            $table->string('template_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            // The address the message was actually delivered to.
            $table->string('recipient')->nullable();
            // The originally intended recipient when the send was diverted by the
            // testing-email redirect (null when it went to the real recipient).
            $table->string('intended_recipient')->nullable();
            $table->string('status')->default('sent'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
