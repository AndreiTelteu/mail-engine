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
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'message_id']);
            $table->text('message_id')->nullable()->change();
            $table->text('in_reply_to')->nullable()->change();
        });

        Schema::table('emails', function (Blueprint $table) {
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->rawIndex('user_id, message_id(191)', 'emails_user_id_message_id_index');
            } else {
                $table->index(['user_id', 'message_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $lengthFunction = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR_LENGTH' : 'LENGTH';

        if ($connection->table('emails')
            ->whereRaw("{$lengthFunction}(message_id) > 255 OR {$lengthFunction}(in_reply_to) > 255")
            ->exists()) {
            throw new RuntimeException('Cannot narrow email message headers while values longer than 255 characters exist.');
        }

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'message_id']);
            $table->string('message_id')->nullable()->change();
            $table->string('in_reply_to')->nullable()->change();
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->index(['user_id', 'message_id']);
        });
    }
};
