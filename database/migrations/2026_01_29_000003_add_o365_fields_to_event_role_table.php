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
        Schema::table('event_role', function (Blueprint $table) {
            $table->string('o365_event_id')->nullable()->after('caldav_event_etag');
            $table->string('o365_event_change_key')->nullable()->after('o365_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_role', function (Blueprint $table) {
            $table->dropColumn([
                'o365_event_id',
                'o365_event_change_key',
            ]);
        });
    }
};
