<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La génération du code de remise a été supprimée (remplacée par les
     * boutons de statut côté livreur) : les colonnes de code deviennent
     * inutiles et sont retirées de la base.
     */
    public function up(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_code_hash',
                'confirmation_code_expires_at',
                'confirmation_code_attempts',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->string('confirmation_code_hash')->nullable();
            $table->timestamp('confirmation_code_expires_at')->nullable();
            $table->unsignedTinyInteger('confirmation_code_attempts')->default(0);
        });
    }
};
