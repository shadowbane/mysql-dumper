<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_data_sources', function (Blueprint $table) {
            $table->foreignUlid('schedule_id')
                ->constrained('schedules', 'id')
                ->cascadeOnDelete();
            $table->foreignUlid('data_source_id')
                ->constrained('data_sources', 'id')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['schedule_id', 'data_source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_data_sources');
    }
};
