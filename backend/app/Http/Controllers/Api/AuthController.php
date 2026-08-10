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

/**
 * @group Authentification
 *
 * Endpoints d'inscription, connexion et gestion de session.
 * Les comptes clients sont créés dans le contexte d'un livreur spécifique (via son slug).
 */
class AuthController extends Controller
{
    /**
     * Inscrire un livreur
     *
     * Crée un nouveau compte livreur avec rôle "driver" forcé côté serveur.
     *
     * @unauthenticated
     *
     * @bodyParam name string required Le nom du livreur. Example: Ahmed
     * @bodyParam email string required L'adresse email. Example: ahmed@example.com
     * @bodyParam password string required Le mot de passe (min 8 caractères). Example: secret123
     * @bodyParam phone string Le numéro de téléphone. Example: +212600000000
     *
     * @response 201 {"success": true, "message": "Compte livreur créé avec succès", "data": {"user": {"id": 1, "name": "Ahmed", "email": "ahmed@example.com"}, "token": "1|abc..."}}
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
     * Connecter un livreur
     *
     * Authentifie un livreur existant par email et mot de passe.
     *
     * @unauthenticated
     *
     * @bodyParam email string required L'adresse email. Example: ahmed@example.com
     * @bodyParam password string required Le mot de passe. Example: secret123
     *
     * @response 200 {"success": true, "message": "OK", "data": {"user": {"id": 1, "name": "Ahmed", "email": "ahmed@example.com"}, "token": "1|abc..."}}
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
     * Inscrire un client (contexte livreur)
     *
     * Crée un compte client rattaché au livreur identifié par le slug.
     * Le rôle "client" et le rattachement sont déduits du slug.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Le slug unique du livreur. Example: rayan-express
     * @bodyParam name string required Le nom du client. Example: Sara
     * @bodyParam email string required L'adresse email. Example: sara@example.com
     * @bodyParam password string required Le mot de passe (min 8 caractères). Example: secret123
     * @bodyParam phone string Le numéro de téléphone. Example: +212600000000
     *
     * @response 201 {"success": true, "message": "Compte client créé avec succès", "data": {"user": {"id": 2, "name": "Sara", "email": "sara@example.com"}, "token": "2|abc..."}}
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
     * Connecter un client (contexte livreur)
     *
     * Authentifie un client rattaché au livreur du slug.
     * Un client du livreur A ne peut pas se connecter via l'URL du livreur B.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Le slug du livreur. Example: rayan-express
     * @bodyParam email string required L'adresse email. Example: sara@example.com
     * @bodyParam password string required Le mot de passe. Example: secret123
     *
     * @response 200 {"success": true, "message": "OK", "data": {"user": {"id": 2, "name": "Sara", "email": "sara@example.com"}, "token": "2|abc..."}}
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

    /**
     * Profil utilisateur courant
     *
     * Retourne les informations de l'utilisateur connecté et son contexte livreur
     * (marque, logo) si applicable.
     *
     * @authenticated
     */
    public function me(Request $request)
    {
        $this->authorize('view', $request->user());

        $user = $request->user();

        return $this->success(data: [
            'user' => $user,
            'driver' => $this->driverContext($user),
        ]);
    }

    /**
     * Déconnexion
     *
     * Supprime le token Sanctum courant de l'utilisateur.
     *
     * @authenticated
     */
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
