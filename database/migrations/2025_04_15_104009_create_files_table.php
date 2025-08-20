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
        Schema::create('files', static function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('filename')->comment('Original filename');
            $table->string('path')->comment('Path in Storage');
            $table->string('disk')->comment('Storage disk');
            $table->string('label')->nullable()->comment('Label for file. For example: avatar, company_logo, etc');
            $table->string('mime_type')->nullable()->comment('MIME type of the file');
            $table->unsignedBigInteger('size_bytes')->comment('File size in bytes');
            $table->string('fileable_type')->nullable()->comment('Model type (nullable)');
            $table->string('fileable_id')->nullable()->comment('Model ID (nullable)');
            $table->boolean('is_public')->default(false)->comment('Whether file is publicly accessible');
            $table->string('hash')->nullable()->comment('File hash for integrity check');
            $table->timestampsTz();
            $table->softDeletesTz();

            // Add index for polymorphic relationship
            $table->index(['fileable_type', 'fileable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
