<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryRequest extends Model
{
    use HasFactory;

    const STATUS_EN_ATTENTE = 'en_attente';
    const STATUS_PRIX_PROPOSE = 'prix_propose';
    const STATUS_CONFIRMEE = 'confirmee';
    const STATUS_COLIS_RECUPERE = 'colis_recupere';
    const STATUS_EN_LIVRAISON = 'en_livraison';
    const STATUS_LIVREE = 'livree';
    const STATUS_REFUSEE = 'refusee';
    const STATUS_ECHEC = 'echec';
    const STATUS_ANNULEE = 'annulee';

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
        'confirmation_code_hash',
        'scheduled_at',
        'picked_up_at',
        'delivered_at',
        'status',
    ];

    protected $hidden = [
        'confirmation_code_hash',
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

    public function review()
    {
        return $this->hasOne(Review::class);
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
