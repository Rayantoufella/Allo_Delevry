<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverProfileRequest;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Http\Resources\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * @group Profils livreurs
 *
 * Gestion des profils de livreurs. Inclut le profil public accessible sans authentification,
 * la page publique avec services et zones, ainsi que le CRUD pour les livreurs connectés.
 */
class DriverProfileController extends Controller
{
    /**
     * Lister mes profils livreur
     *
     * Retourne les profils du livreur connecté, du plus récent au plus ancien.
     *
     * @authenticated
     */
    public function index(Request $request)
    {
        return DriverProfileResource::collection(
            DriverProfile::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    /**
     * Profil public d'un livreur
     *
     * Affiche le profil public d'un livreur par son slug, incluant les services actifs
     * et les zones de livraison actives. Accessible sans authentification.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Le slug unique du livreur. Example: rayan-express
     */
    public function showPublic(string $slug)
    {
        $profile = DriverProfile::where('slug', $slug)
            ->with(['user.services' => fn ($q) => $q->where('is_active', true), 'user.deliveryZones' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        return new DriverProfileResource($profile);
    }

    /**
     * QR Code du livreur
     *
     * Génère un QR code SVG pointant vers la page publique du livreur.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Le slug du livreur. Example: rayan-express
     *
     * @response 200 {"content": "image/svg+xml"}
     */
    public function qrCode(string $slug)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $url = rtrim(config('app.frontend_url'), '/').'/drivers/'.$profile->slug;

        return response(QrCode::format('svg')->generate($url))
            ->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Créer un profil livreur
     *
     * Crée un nouveau profil de marque pour le livreur connecté.
     * Le slug est rendu unique automatiquement (suffixe -2, -3... si déjà pris).
     *
     * @authenticated
     *
     * @bodyParam slug string required L'identifiant URL unique. Example: rayan-express
     * @bodyParam brand_name string required Le nom de la marque. Example: Rayan Express
     * @bodyParam description string La description du livreur. Example: Livraison rapide à Agadir
     * @bodyParam logo_path string Le chemin du logo. Example: logos/mon-logo.png
     * @bodyParam city string La ville. Example: Agadir
     * @bodyParam phone string Le téléphone du profil. Example: +212600000000
     */
    public function store(StoreDriverProfileRequest $request)
    {
        $this->authorize('create', DriverProfile::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $this->resolveUniqueSlug($data['slug']);

        if ($request->filled('phone')) {
            $request->user()->update(['phone' => $request->input('phone')]);
        }

        return response()->json(new DriverProfileResource(DriverProfile::create(Arr::except($data, ['phone']))), 201);
    }

    /**
     * Détail d'un profil livreur
     *
     * Retourne les détails d'un profil spécifique appartenant au livreur connecté.
     *
     * @authenticated
     *
     * @urlParam id int required L'identifiant du profil. Example: 1
     */
    public function show($id, Request $request)
    {
        $profile = DriverProfile::findOrFail($id);

        $this->authorize('view', $profile);

        return new DriverProfileResource($profile);
    }

    /**
     * Modifier un profil livreur
     *
     * Met à jour un profil existant. Le slug est rendu unique si modifié.
     *
     * @authenticated
     *
     * @urlParam id int required L'identifiant du profil. Example: 1
     * @bodyParam brand_name string Le nom de la marque. Example: Rayan Express Updated
     * @bodyParam description string La description. Example: Nouvelle description
     * @bodyParam slug string Le slug. Example: rayan-express-v2
     */
    public function update(UpdateDriverProfileRequest $request, $id)
    {
        $profile = DriverProfile::findOrFail($id);

        $this->authorize('update', $profile);

        $validated = $request->validated();

        if (isset($validated['slug'])) {
            $validated['slug'] = $this->resolveUniqueSlug($validated['slug'], ignoreId: $profile->id);
        }

        if ($request->filled('phone')) {
            $request->user()->update(['phone' => $request->input('phone')]);
        }

        $profile->update(Arr::except($validated, ['phone']));

        return new DriverProfileResource($profile->refresh());
    }

    /**
     * Supprimer un profil livreur
     *
     * Supprime définitivement un profil de marque.
     *
     * @authenticated
     *
     * @urlParam id int required L'identifiant du profil. Example: 1
     *
     * @response 200 {"message": "Profil supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $profile = DriverProfile::findOrFail($id);

        $this->authorize('delete', $profile);

        $profile->delete();

        return response()->json(['message' => 'Profil supprimé avec succès']);
    }

    /**
     * Garantit un slug libre : si celui demandé est pris, on ajoute un
     * suffixe numérique (-2, -3…) jusqu'à trouver une place. La création de
     * profil ne peut donc plus échouer sur un identifiant déjà utilisé.
     */
    private function resolveUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug === '' ? 'profil' : $slug;
        $candidate = $base;
        $i = 1;

        while (DriverProfile::where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.(++$i);
        }

        return $candidate;
    }
}
