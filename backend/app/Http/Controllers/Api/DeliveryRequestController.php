<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Http\Resources\DeliveryRequestResource;
use App\Http\Resources\PublicTrackingResource;
use App\Jobs\CreateDeliveryRequestNotificationJob;
use App\Jobs\ExpireConfirmationCodeJob;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DeliveryRequest::with(['client', 'driver', 'service', 'deliveryZone']);

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        return DeliveryRequestResource::collection($query->latest()->paginate(20));
    }

    public function storeForDriver(string $slug, StoreDeliveryRequest $request)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $this->authorize('create', DeliveryRequest::class);

        $data = $request->validated();

        $this->ensureOwnedByDriver($profile, $data);

        $data['client_id'] = $request->user()->id;
        $data['driver_id'] = $profile->user_id;
        $data['tracking_number'] = 'DLV-'.strtoupper(Str::random(10));
        $data['private_token'] = Str::random(32);
        $data['status'] = DeliveryRequest::STATUS_EN_ATTENTE;

        $deliveryRequest = DeliveryRequest::create($data);

        CreateDeliveryRequestNotificationJob::dispatch($deliveryRequest)->afterCommit();

        return response()->json(new DeliveryRequestResource($deliveryRequest), 201);
    }

    public function show($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('view', $deliveryRequest);

        return new DeliveryRequestResource($deliveryRequest);
    }

    public function update(UpdateDeliveryRequest $request, DeliveryRequest $deliveryRequest)
    {
        $this->authorize('update', $deliveryRequest);

        if (! $deliveryRequest->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Une demande en cours de livraison ou terminée ne peut plus être modifiée.',
            ]);
        }

        $data = $request->validated();

        $this->ensureBelongsToDriver($deliveryRequest->driver_id, $data);

        $deliveryRequest->update($data);

        return new DeliveryRequestResource($deliveryRequest->refresh());
    }

    public function destroy($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('delete', $deliveryRequest);

        if (! $deliveryRequest->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Seules les demandes aux statuts terminaux peuvent être supprimées.',
            ]);
        }

        $deliveryRequest->delete();

        return response()->json(['message' => 'Demande de livraison supprimée avec succès']);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(DeliveryRequest::statuses())],
            'comment' => ['nullable', 'string'],
            'proposed_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('updateStatus', $deliveryRequest);

        $newStatus = $validated['status'];

        if (! $deliveryRequest->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Transition non autorisée : '{$deliveryRequest->status}' → '{$newStatus}'.",
            ]);
        }

        if ($newStatus === DeliveryRequest::STATUS_PRIX_PROPOSE && empty($validated['proposed_price'])) {
            throw ValidationException::withMessages([
                'proposed_price' => 'Le prix proposé est requis pour passer au statut "prix_propose".',
            ]);
        }

        if (! empty($validated['proposed_price'])) {
            $deliveryRequest->proposed_price = $validated['proposed_price'];
        }

        $deliveryRequest->transitionTo(
            $newStatus,
            changedBy: $request->user()->id,
            comment: $validated['comment'] ?? null,
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    public function confirmPrice(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('confirmPrice', $deliveryRequest);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_PRIX_PROPOSE) {
            throw ValidationException::withMessages([
                'status' => 'Le prix ne peut être confirmé que sur une demande au statut "prix_propose".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_CONFIRMEE,
            changedBy: $request->user()->id,
            comment: 'Prix proposé confirmé par le client',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    public function cancel(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('cancel', $deliveryRequest);

        if (! $deliveryRequest->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Une demande ne peut être annulée qu\'aux statuts "en_attente" ou "prix_propose".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_ANNULEE,
            changedBy: $request->user()->id,
            comment: 'Demande annulée par le client',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    public function tracking(string $privateToken)
    {
        $deliveryRequest = DeliveryRequest::with(['statusHistories'])
            ->where('private_token', $privateToken)
            ->firstOrFail();

        return new PublicTrackingResource($deliveryRequest);
    }

    public function generateCode(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('generateCode', $deliveryRequest);

        if (! $deliveryRequest->canGenerateCode()) {
            throw ValidationException::withMessages([
                'status' => 'Le code de confirmation ne peut être généré qu\'après confirmation du prix.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $deliveryRequest->update([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_expires_at' => now()->addMinutes(30),
            'confirmation_code_attempts' => 0,
        ]);

        ExpireConfirmationCodeJob::dispatch($deliveryRequest)
            ->delay(now()->addMinutes(30))
            ->afterCommit();

        return response()->json(['code' => $code]);
    }

    public function confirmDelivery(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('confirmDelivery', $deliveryRequest);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_EN_LIVRAISON) {
            throw ValidationException::withMessages([
                'status' => 'La livraison ne peut être confirmée qu\'au statut "en_livraison".',
            ]);
        }

        if ($deliveryRequest->proofs()->count() === 0) {
            throw ValidationException::withMessages([
                'proof' => 'Une preuve de livraison est requise avant la confirmation (RG06).',
            ]);
        }

        if ($deliveryRequest->confirmation_code_hash === null
            || $deliveryRequest->confirmation_code_expires_at === null
            || now()->greaterThan($deliveryRequest->confirmation_code_expires_at)) {
            throw ValidationException::withMessages([
                'code' => 'Le code de confirmation est expiré ou inexistant. Générez un nouveau code.',
            ]);
        }

        if ($deliveryRequest->confirmation_code_attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => 'Trop de tentatives. Générez un nouveau code de confirmation.',
            ]);
        }

        if (! Hash::check($validated['code'], $deliveryRequest->confirmation_code_hash)) {
            $deliveryRequest->increment('confirmation_code_attempts');

            throw ValidationException::withMessages([
                'code' => 'Code de confirmation incorrect.',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_LIVREE,
            changedBy: $request->user()->id,
            comment: 'Code de confirmation vérifié par le client (RG06)',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    private function ensureOwnedByDriver(DriverProfile $profile, array $data): void
    {
        $this->ensureBelongsToDriver($profile->user_id, $data);
    }

    private function ensureBelongsToDriver(int $driverUserId, array $data): void
    {
        if (! empty($data['service_id'])
            && ! Service::where('id', $data['service_id'])->where('user_id', $driverUserId)->exists()) {
            throw ValidationException::withMessages([
                'service_id' => 'Ce service n\'appartient pas à ce livreur.',
            ]);
        }

        if (! empty($data['delivery_zone_id'])
            && ! DeliveryZone::where('id', $data['delivery_zone_id'])->where('user_id', $driverUserId)->exists()) {
            throw ValidationException::withMessages([
                'delivery_zone_id' => 'Cette zone de livraison n\'appartient pas à ce livreur.',
            ]);
        }
    }
}
