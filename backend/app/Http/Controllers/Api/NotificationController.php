<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return NotificationResource::collection(
            Notification::where('user_id', $request->user()->id)->latest()->paginate(20)
        );
    }

    public function show($id, Request $request)
    {
        $notification = Notification::findOrFail($id);

        $this->authorize('view', $notification);

        return new NotificationResource($notification);
    }

    public function markAsRead($id, Request $request)
    {
        $notification = Notification::findOrFail($id);

        $this->authorize('update', $notification);

        $notification->update(['read_at' => now()]);

        return new NotificationResource($notification->refresh());
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }
}
