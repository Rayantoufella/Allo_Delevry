<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rattache chaque compte client à un livreur précis : un client s'inscrit
     * toujours dans le contexte d'un livreur (lien public / QR code), et un
     * même e-mail peut donc appartenir à deux livreurs différents.
     *
     * MySQL considère les NULL comme distincts dans un index unique : un
     * simple unique(email, driver_id) laisserait deux livreurs (tous deux
     * driver_id NULL) partager le même e-mail. On matérialise donc une
     * colonne générée driver_key = COALESCE(driver_id, 0) et on pose
     * l'unique sur (email, driver_key).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        $this->backfillClientDriverId();

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        // Colonne VIRTUELLE et non stockée : ajouter une colonne générée stockée
        // impose à MySQL de reconstruire la table, ce qu'il refuse ici à cause de
        // la clé étrangère auto-référente driver_id -> users.id (erreur 1215).
        // Une colonne virtuelle s'ajoute sans reconstruction et supporte
        // parfaitement un index unique secondaire sous InnoDB.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_key')->virtualAs('COALESCE(driver_id, 0)');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['email', 'driver_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'driver_key']);
            $table->dropColumn('driver_key');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
        });
    }

    /**
     * Rattache chaque client existant au livreur de sa première demande de
     * livraison. Un client sans aucune demande reste sans livreur (driver_id
     * NULL) : ce cas ne bloque pas la migration, mais la base de dev peut
     * être reconstruite via `migrate:fresh --seed` pour repartir propre.
     */
    private function backfillClientDriverId(): void
    {
        if (! Schema::hasTable('delivery_requests')) {
            return;
        }

        DB::table('users')
            ->where('role', 'client')
            ->whereNull('driver_id')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user) {
                $driverId = DB::table('delivery_requests')
                    ->where('client_id', $user->id)
                    ->orderBy('created_at')
                    ->value('driver_id');

                if ($driverId !== null) {
                    DB::table('users')->where('id', $user->id)->update(['driver_id' => $driverId]);
                }
            });
    }
};
