<?php

namespace App\Models;

use App\Jobs\CreateStatusChangedNotificationJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryRequest extends Model
{
    use HasFactory;

    const STATUS_EN_ATTENTE = 'en_attente';

    const STATUS_PRIX_PROPOSE = 'prix_propose';

    const STATUS_CONFIRMEE = 'confirmee';

    const STATUS_COLIS_RECUPERE = 'colis_recupere';

    const STATUS_EN_LIVRAISON = 'en_livraison';

    const STATUS_LIVREUR_ARRIVE = 'livreur_arrive';

    const STATUS_LIVREE = 'livree';

    const STATUS_REFUSEE = 'refusee';

    const STATUS_ECHEC = 'echec';

    const STATUS_ANNULEE = 'annulee';

    /** Statuts atteignables depuis chaque statut (machine à états). */
    private const TRANSITIONS = [
        // Nouveau flux : le driver accepte (confirmee) ou refuse directement ;
        // prix_propose reste accessible pour les demandes legacy en vol.
        self::STATUS_EN_ATTENTE => [self::STATUS_PRIX_PROPOSE, self::STATUS_REFUSEE, self::STATUS_CONFIRMEE],
        self::STATUS_PRIX_PROPOSE => [self::STATUS_REFUSEE],
        self::STATUS_CONFIRMEE => [self::STATUS_COLIS_RECUPERE],
        self::STATUS_COLIS_RECUPERE => [self::STATUS_EN_LIVRAISON],
        self::STATUS_EN_LIVRAISON => [self::STATUS_LIVREUR_ARRIVE, self::STATUS_ECHEC],
        self::STATUS_LIVREUR_ARRIVE => [self::STATUS_LIVREE, self::STATUS_ECHEC],
    ];

    private const CANCELLABLE_STATUSES = [
        self::STATUS_EN_ATTENTE,
        self::STATUS_PRIX_PROPOSE,
    ];

    /** Statuts verrouillés : plus modifiables ni supprimables en cours de route. */
    private const IMMUTABLE_STATUSES = [
        self::STATUS_COLIS_RECUPERE,
        self::STATUS_EN_LIVRAISON,
        self::STATUS_LIVREUR_ARRIVE,
        self::STATUS_LIVREE,
        self::STATUS_ECHEC,
    ];

    private const TERMINAL_STATUSES = [
        self::STATUS_LIVREE,
        self::STATUS_REFUSEE,
        self::STATUS_ECHEC,
        self::STATUS_ANNULEE,
    ];

    /** Tous les statuts possibles. */
    public static function statuses(): array
    {
        return [
            self::STATUS_EN_ATTENTE,
            self::STATUS_PRIX_PROPOSE,
            self::STATUS_CONFIRMEE,
            self::STATUS_COLIS_RECUPERE,
            self::STATUS_EN_LIVRAISON,
            self::STATUS_LIVREUR_ARRIVE,
            self::STATUS_LIVREE,
            self::STATUS_REFUSEE,
            self::STATUS_ECHEC,
            self::STATUS_ANNULEE,
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, self::IMMUTABLE_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Applique un changement de statut, met à jour les horodatages liés
     * et journalise l'historique, le tout de façon atomique.
     */
    public function transitionTo(string $newStatus, ?int $changedBy = null, ?string $comment = null): static
    {
        $oldStatus = $this->status;

        DB::transaction(function () use ($newStatus, $oldStatus, $changedBy, $comment): void {
            $this->status = $newStatus;

            if (in_array($newStatus, [self::STATUS_COLIS_RECUPERE, self::STATUS_EN_LIVRAISON], true)
                && $this->picked_up_at === null) {
                $this->picked_up_at = now();
            }

            if ($newStatus === self::STATUS_LIVREE && $this->delivered_at === null) {
                $this->delivered_at = now();
            }

            $this->save();

            $this->statusHistories()->create([
                'changed_by' => $changedBy,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'comment' => $comment,
            ]);
        });

        $this->refresh();

        CreateStatusChangedNotificationJob::dispatch($this, $newStatus, $changedBy)->afterCommit();

        return $this;
    }

    protected $fillable = [
        'tracking_number',
        'private_token',
        'client_id',
        'driver_id',
        'service_id',
        'delivery_zone_id',
        'ai_request_draft_id',
        'recipient_name',
        'recipient_phone',
        'pickup_address',
        'delivery_address',
        'package_description',
        'product_amount',
        'amount_to_collect',
        'proposed_price',
        'scheduled_at',
        'picked_up_at',
        'delivered_at',
        'status',
    ];

    protected $hidden = [
        'private_token',
    ];

    protected function casts(): array
    {
        return [
            'product_amount' => 'decimal:2',
            'amount_to_collect' => 'decimal:2',
            'proposed_price' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function deliveryZone()
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function aiRequestDraft()
    {
        return $this->belongsTo(AiRequestDraft::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function proofs()
    {
        return $this->hasMany(DeliveryProof::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function gpsLocations()
    {
        return $this->hasMany(GpsLocation::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_LIVREE,
            self::STATUS_REFUSEE,
            self::STATUS_ECHEC,
            self::STATUS_ANNULEE,
        ]);
    }
}
