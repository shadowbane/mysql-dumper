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
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('data_source_id')
                ->constrained('data_sources', 'id')
                ->restrictOnDelete();
            $table->char('status', 10)
                ->comment("available: 'pending', 'running', 'completed', 'failed'");
            $table->char('type', 10)
                ->comment("type of backup: 'manual', 'automated'");
            $table->string('disk')
                ->comment('disk (filesystem) of the output');
            $table->string('filename')
                ->comment('filename of the output');
            $table->text('file_path')
                ->comment('path of the output');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['data_source_id', 'status']);
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
