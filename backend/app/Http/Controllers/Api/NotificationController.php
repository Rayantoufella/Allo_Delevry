<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @group Notifications
 *
 * Gestion des notifications utilisateur. Les notifications sont générées automatiquement
 * lors des événements importants (nouvelle demande, message chat, etc.).
 *
 * @authenticated
 */
class NotificationController extends Controller
{
    /**
     * Lister mes notifications
     *
     * Retourne les notifications de l'utilisateur connecté, de la plus récente à l'ancienne.
     * Pagination : 20 éléments par page.
     */
    public function index(Request $request)
    {
        return NotificationResource::collection(
            Notification::where('user_id', $request->user()->id)->latest()->paginate(20)
        );
    }

    /**
     * Détail d'une notification
     *
     * Retourne les détails d'une notification spécifique.
     *
     * @urlParam id int required L'identifiant de la notification. Example: 1
     */
    public function show($id, Request $request)
    {
        $notification = Notification::findOrFail($id);

        $this->authorize('view', $notification);

        return new NotificationResource($notification);
    }

    /**
     * Marquer une notification comme lue
     *
     * Marque une notification spécifique comme lue (définit `read_at`).
     *
     * @urlParam id int required L'identifiant de la notification. Example: 1
     */
    public function markAsRead($id, Request $request)
    {
        $notification = Notification::findOrFail($id);

        $this->authorize('update', $notification);

        $notification->update(['read_at' => now()]);

        return new NotificationResource($notification->refresh());
    }

    /**
     * Marquer toutes les notifications comme lues
     *
     * Marque toutes les notifications non lues de l'utilisateur comme lues.
     *
     * @response 200 {"message": "Toutes les notifications ont été marquées comme lues"}
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }
}
