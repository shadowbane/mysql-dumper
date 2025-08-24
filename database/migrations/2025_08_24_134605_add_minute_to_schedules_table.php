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
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_is_active_hour_index');
            $table->unsignedTinyInteger('minute')->default(0)->after('hour'); // 0-59 minute in UTC
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('minute');
            $table->index(['is_active', 'hour']);
        });
    }
};
