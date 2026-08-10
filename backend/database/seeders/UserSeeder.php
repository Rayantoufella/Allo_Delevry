<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Comptes démo déterministes : le mot de passe est FORCÉ à chaque seed
        // (updateOrCreate), sinon un ancien hash survit à firstOrCreate et le
        // login démo renvoie 401 « Invalid credentials » (vu en QA réelle).
        $demoDriver = User::updateOrCreate(
            ['email' => 'driver@example.com'],
            [
                'name' => 'Livreur Demo',
                'role' => User::ROLE_DRIVER,
                'password' => 'password',
            ],
        );

        // Le client démo est rattaché au livreur démo (RG scoping client/livreur) :
        // l'email n'est unique que par driver_key, donc client@example.com peut aussi
        // exister chez d'autres livreurs sans conflit.
        User::updateOrCreate(
            ['email' => 'client@example.com', 'driver_id' => $demoDriver->id],
            [
                'name' => 'Client Demo',
                'role' => User::ROLE_CLIENT,
                'password' => 'password',
            ],
        );

        if (User::count() > 2) {
            return;
        }

        User::factory()
            ->count(10)
            ->client()
            ->create();

        User::factory()
            ->count(10)
            ->driver()
            ->create();
    }
}
