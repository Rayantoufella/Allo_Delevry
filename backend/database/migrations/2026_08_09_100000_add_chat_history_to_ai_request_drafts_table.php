<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_request_drafts', function (Blueprint $table) {
            $table->json('chat_history')->nullable()->after('input_message');
        });
    }

    public function down(): void
    {
        Schema::table('ai_request_drafts', function (Blueprint $table) {
            $table->dropColumn('chat_history');
        });
    }
};
