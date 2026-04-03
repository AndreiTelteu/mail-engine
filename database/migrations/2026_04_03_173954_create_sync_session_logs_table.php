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
        Schema::create('sync_session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_session_id')->constrained()->cascadeOnDelete();
            $table->string('to_address')->default('');
            $table->string('subject', 500)->default('');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_session_logs');
    }
};
