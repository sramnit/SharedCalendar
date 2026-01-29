<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('o365_calendar_id')->nullable()->after('caldav_last_sync_at');
            $table->string('o365_calendar_name')->nullable()->after('o365_calendar_id');
            $table->string('o365_webhook_subscription_id')->nullable()->after('o365_calendar_name');
            $table->timestamp('o365_webhook_expires_at')->nullable()->after('o365_webhook_subscription_id');
            $table->enum('o365_sync_direction', ['to', 'from', 'both'])->nullable()->after('o365_webhook_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'o365_calendar_id',
                'o365_calendar_name',
                'o365_webhook_subscription_id',
                'o365_webhook_expires_at',
                'o365_sync_direction',
            ]);
        });
    }
};
