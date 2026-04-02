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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message_id');
            $table->string('folder');
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->json('to_addresses');
            $table->json('cc_addresses')->nullable();
            $table->text('subject')->nullable();
            $table->dateTime('date');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('indexing_failed_at')->nullable();
            $table->text('indexing_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'folder']);
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
