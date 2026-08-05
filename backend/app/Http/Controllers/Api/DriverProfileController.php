<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverProfileRequest;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Http\Resources\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
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

        return response()->json(new DriverProfileResource(DriverProfile::create($data)), 201);
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

        $profile->update($request->validated());

        return new DriverProfileResource($profile->refresh());
    }

    public function destroy($id, Request $request)
    {
        $profile = DriverProfile::findOrFail($id);

        $this->authorize('delete', $profile);

        $profile->delete();

        return response()->json(['message' => 'Profil supprimé avec succès']);
    }
}
