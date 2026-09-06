<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The prior email identity was based on Message-ID, which is neither
     * mailbox-local nor guaranteed to exist. Rebuilding is deliberate: this
     * application has no compatibility requirement for the old mail cache.
     */
    public function up(): void
    {
        Schema::create('mail_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imap_setting_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->unsignedBigInteger('last_synced_uid')->default(0);
            $table->unsignedBigInteger('uid_next')->nullable();
            $table->unsignedInteger('remote_message_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['imap_setting_id', 'path']);
        });

        Schema::dropIfExists('emails');

        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uid_validity');
            $table->unsignedBigInteger('imap_uid');
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('folder');
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->json('to_addresses');
            $table->json('cc_addresses')->nullable();
            $table->text('subject')->nullable();
            $table->dateTime('date');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_current')->nullable();
            $table->longText('body_quoted')->nullable();
            $table->string('preview', 280)->default('');
            $table->json('attachments')->nullable();
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->char('content_hash', 64);
            $table->unsignedSmallInteger('parser_version')->default(1);
            $table->timestamp('indexing_failed_at')->nullable();
            $table->text('indexing_error')->nullable();
            $table->timestamps();

            $table->unique(['mail_folder_id', 'uid_validity', 'imap_uid'], 'emails_folder_uid_identity_unique');
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'folder', 'date'], 'emails_user_folder_date_index');
            $table->index(['user_id', 'message_id']);
        });

        Schema::table('sync_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('pending_folder_jobs')->default(0)->after('total_to_sync');
        });
    }

    public function down(): void
    {
        Schema::table('sync_sessions', function (Blueprint $table) {
            $table->dropColumn('pending_folder_jobs');
        });

        Schema::dropIfExists('emails');
        Schema::dropIfExists('mail_folders');
    }
};
