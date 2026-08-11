<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\ChatMessage;
use App\Models\DeliveryRequest;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @group Dashboard livreur
 *
 * Tableau de bord du livreur (AR-39). Un seul endpoint retournant des indicateurs agrégés :
 * demandes, missions en cours, chiffre d'affaires, avis et notifications.
 *
 * @authenticated
 */
class DashboardController extends Controller
{
    /**
     * Tableau de bord du livreur
     *
     * Retourne les indicateurs du livreur connecté : nombre de demandes,
     * missions actives, en attente, livrées, chiffre d'affaires estimé/encaissé,
     * note moyenne, notifications non lues, les 5 dernières demandes et les 5 derniers messages.
     */
    public function index(Request $request)
    {
        $driverId = $request->user()->id;

        // ---- Demandes -----------------------------------------------------
        $totalRequests = DeliveryRequest::where('driver_id', $driverId)->count();

        // Missions en cours : tous les statuts sauf les statuts terminaux.
        $activeMissions = DeliveryRequest::where('driver_id', $driverId)
            ->active()
            ->count();

        // Nouvelles demandes qui attendent une décision du livreur.
        $pendingRequests = DeliveryRequest::where('driver_id', $driverId)
            ->where('status', DeliveryRequest::STATUS_EN_ATTENTE)
            ->count();

        $deliveredMissions = DeliveryRequest::where('driver_id', $driverId)
            ->where('status', DeliveryRequest::STATUS_LIVREE)
            ->count();

        // ---- Chiffre d'affaires -------------------------------------------
        // Estimé : toutes les missions engagées ou terminées avec un prix.
        $revenueStatuses = [
            DeliveryRequest::STATUS_CONFIRMEE,
            DeliveryRequest::STATUS_COLIS_RECUPERE,
            DeliveryRequest::STATUS_EN_LIVRAISON,
            DeliveryRequest::STATUS_LIVREE,
        ];

        $estimatedRevenue = (float) DeliveryRequest::where('driver_id', $driverId)
            ->whereIn('status', $revenueStatuses)
            ->sum('proposed_price');

        // Encaissé : uniquement les livraisons terminées.
        $collectedRevenue = (float) DeliveryRequest::where('driver_id', $driverId)
            ->where('status', DeliveryRequest::STATUS_LIVREE)
            ->sum('proposed_price');

        // ---- Notifications -------------------------------------------------
        $unreadNotifications = Notification::where('user_id', $driverId)
            ->whereNull('read_at')
            ->count();

        // ---- Dernières demandes -------------------------------------------
        $recentRequests = DeliveryRequest::where('driver_id', $driverId)
            ->latest()
            ->limit(5)
            ->get(['id', 'tracking_number', 'status', 'proposed_price', 'created_at']);

        // ---- Derniers messages du chat -------------------------------------
        $recentMessages = ChatMessage::whereHas(
            'deliveryRequest',
            fn ($query) => $query->where('driver_id', $driverId)
        )
            ->with('sender:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'sender_name' => $message->sender?->name,
                'content' => $message->content,
                'created_at' => $message->created_at?->toDateTimeString(),
            ]);

        return $this->success(data: new DashboardResource([
            'total_requests' => $totalRequests,
            'active_missions' => $activeMissions,
            'pending_requests' => $pendingRequests,
            'delivered_missions' => $deliveredMissions,
            'estimated_revenue' => number_format($estimatedRevenue, 2, '.', ''),
            'collected_revenue' => number_format($collectedRevenue, 2, '.', ''),
            'unread_notifications' => $unreadNotifications,
            'recent_requests' => $recentRequests,
            'recent_messages' => $recentMessages,
        ]));
    }
}
