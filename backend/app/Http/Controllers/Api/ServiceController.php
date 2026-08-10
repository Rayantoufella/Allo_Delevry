<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * @group Services
 *
 * CRUD des services proposés par un livreur (envoi de colis, documents, courses, etc.).
 * Chaque service a un nom, une description et un prix de base.
 *
 * @authenticated
 */
class ServiceController extends Controller
{
    /**
     * Lister mes services
     *
     * Retourne la liste des services du livreur connecté.
     */
    public function index(Request $request)
    {
        return ServiceResource::collection(
            Service::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    /**
     * Créer un service
     *
     * Ajoute un nouveau service au catalogue du livreur.
     *
     * @bodyParam name string required Le nom du service. Example: Envoi de colis
     * @bodyParam description string La description. Example: Envoi rapide de colis dans la ville
     * @bodyParam base_price float Le prix de base en DH. Example: 15.00
     */
    public function store(StoreServiceRequest $request)
    {
        $this->authorize('create', Service::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new ServiceResource(Service::create($data)), 201);
    }

    /**
     * Détail d'un service
     *
     * Retourne les informations d'un service spécifique.
     *
     * @urlParam id int required L'identifiant du service. Example: 1
     */
    public function show($id, Request $request)
    {
        $service = Service::findOrFail($id);

        $this->authorize('view', $service);

        return new ServiceResource($service);
    }

    /**
     * Modifier un service
     *
     * Met à jour les informations d'un service existant.
     *
     * @urlParam id int required L'identifiant du service. Example: 1
     * @bodyParam name string Le nom. Example: Envoi de colis express
     * @bodyParam base_price float Le prix de base. Example: 20.00
     */
    public function update(UpdateServiceRequest $request, $id)
    {
        $service = Service::findOrFail($id);

        $this->authorize('update', $service);

        $service->update($request->validated());

        return new ServiceResource($service->refresh());
    }

    /**
     * Supprimer un service
     *
     * Supprime définitivement un service du catalogue.
     *
     * @urlParam id int required L'identifiant du service. Example: 1
     *
     * @response 200 {"message": "Service supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $service = Service::findOrFail($id);

        $this->authorize('delete', $service);

        $service->delete();

        return response()->json(['message' => 'Service supprimé avec succès']);
    }
}
