<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->timestamp('confirmation_code_expires_at')->nullable()->after('confirmation_code_hash');
            $table->unsignedTinyInteger('confirmation_code_attempts')->default(0)->after('confirmation_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->dropColumn(['confirmation_code_expires_at', 'confirmation_code_attempts']);
        });
    }
};
