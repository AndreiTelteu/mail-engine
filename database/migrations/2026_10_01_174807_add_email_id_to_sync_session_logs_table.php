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
        Schema::table('sync_session_logs', function (Blueprint $table) {
            $table->foreignId('email_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['sync_session_id', 'email_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_session_logs', function (Blueprint $table) {
            $table->dropIndex(['sync_session_id', 'email_id']);
            $table->dropConstrainedForeignId('email_id');
        });
    }
};
