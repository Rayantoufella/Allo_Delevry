<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ClientRegisterRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Inscription d'un livreur. Le rôle "driver" est forcé côté serveur ;
     * l'inscription cliente globale n'existe plus sur cette route.
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = User::ROLE_DRIVER;
        $data['driver_id'] = null;

        $user = User::create($data);

        return $this->success(
            data: ['user' => $user, 'token' => $user->createToken('api')->plainTextToken],
            message: 'Compte livreur créé avec succès',
            status: 201,
        );
    }

    /**
     * Connexion d'un livreur. Le rôle "driver" discrimine déjà ; on n'exige
     * plus driver_id NULL : un compte livreur créé ou consolidé avec un
     * driver_id résiduel (données legacy) ne doit pas être bloqué.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)
            ->where('role', User::ROLE_DRIVER)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error(message: 'Invalid credentials', status: 401);
        }

        return $this->success(data: [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    /**
     * Inscription d'un client dans le contexte d'un livreur précis
     * (lien public / QR code du livreur). Le rôle "client" et le
     * rattachement au livreur sont déduits du slug, jamais du corps de la
     * requête.
     */
    public function registerForDriver(string $slug, ClientRegisterRequest $request)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = User::ROLE_CLIENT;
        $data['driver_id'] = $profile->user_id;

        $user = User::create($data);

        return $this->success(
            data: ['user' => $user, 'token' => $user->createToken('api')->plainTextToken],
            message: 'Compte client créé avec succès',
            status: 201,
        );
    }

    /**
     * Connexion d'un client rattaché au livreur du slug. Un client du
     * livreur A ne peut pas se connecter via l'URL du livreur B, même avec
     * le bon mot de passe.
     */
    public function loginForDriver(string $slug, LoginRequest $request)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $user = User::where('email', $request->email)
            ->where('role', User::ROLE_CLIENT)
            ->where('driver_id', $profile->user_id)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error(message: 'Invalid credentials', status: 401);
        }

        return $this->success(data: [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function me(Request $request)
    {
        $this->authorize('view', $request->user());

        $user = $request->user();

        return $this->success(data: [
            'user' => $user,
            'driver' => $this->driverContext($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Logged out successfully');
    }

    /**
     * Contexte livreur exposé à un client (marque, logo) pour affichage
     * frontend. Null pour un livreur ou un client sans livreur rattaché.
     *
     * @return array{slug: string, brand_name: string, logo_path: ?string}|null
     */
    private function driverContext(User $user): ?array
    {
        if (! $user->isClient() || ! $user->driver_id) {
            return null;
        }

        // Requête directe plutôt que $user->driver?->driverProfile : traverser la
        // relation la charge sur le modèle renvoyé, qui sérialise alors tout le
        // compte du livreur (e-mail) et son profil (RIB) dans la réponse au client.
        $driverProfile = DriverProfile::where('user_id', $user->driver_id)->first();

        if (! $driverProfile) {
            return null;
        }

        return [
            'slug' => $driverProfile->slug,
            'brand_name' => $driverProfile->brand_name,
            'logo_path' => $driverProfile->logo_path,
        ];
    }
}
