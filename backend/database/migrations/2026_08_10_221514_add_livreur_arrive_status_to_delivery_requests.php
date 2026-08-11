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
        Schema::table('delivery_requests', function (Blueprint $table) {
            // Nouveau statut "livreur_arrive" (client) : le livreur est arrive
            // a l'adresse de livraison, avant la remise ("livree").
            $table->enum('status', [
                'en_attente',
                'prix_propose',
                'confirmee',
                'colis_recupere',
                'en_livraison',
                'livreur_arrive',
                'livree',
                'refusee',
                'echec',
                'annulee',
            ])->default('en_attente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_requests', function (Blueprint $table) {
            $table->enum('status', [
                'en_attente',
                'prix_propose',
                'confirmee',
                'colis_recupere',
                'en_livraison',
                'livree',
                'refusee',
                'echec',
                'annulee',
            ])->default('en_attente')->change();
        });
    }
};
