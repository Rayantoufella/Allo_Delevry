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

class DriverProfileController extends Controller
{
    public function index(Request $request)
    {
        return DriverProfileResource::collection(
            DriverProfile::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function showPublic(string $slug)
    {
        $profile = DriverProfile::where('slug', $slug)
            ->with(['user.services' => fn ($q) => $q->where('is_active', true), 'user.deliveryZones' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        return new DriverProfileResource($profile);
    }

    public function qrCode(string $slug)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $url = rtrim(config('app.frontend_url'), '/').'/drivers/'.$profile->slug;

        return response(QrCode::format('svg')->generate($url))
            ->header('Content-Type', 'image/svg+xml');
    }

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

    public function show($id, Request $request)
    {
        $profile = DriverProfile::findOrFail($id);

        $this->authorize('view', $profile);

        return new DriverProfileResource($profile);
    }

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
