<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_log_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_log_id')->constrained()->onDelete('cascade');
            $table->string('status'); // Use string to match BackupStatusEnum values
            $table->json('metadata')->nullable(); // Additional context for this status change
            $table->timestamps();

            $table->index(['backup_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_log_timelines');
    }
};