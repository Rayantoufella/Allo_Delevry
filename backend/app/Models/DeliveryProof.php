<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    use HasFactory;

    public const TYPE_PHOTO = 'photo';

    public const TYPE_SIGNATURE = 'signature';

    public const TYPE_TICKET = 'ticket';

    public const TYPE_PICKUP_PHOTO = 'pickup_photo';

    public const TYPE_PICKUP_ID_CARD = 'pickup_id_card';

    public const TYPES = [
        self::TYPE_PHOTO,
        self::TYPE_SIGNATURE,
        self::TYPE_TICKET,
        self::TYPE_PICKUP_PHOTO,
        self::TYPE_PICKUP_ID_CARD,
    ];

    protected $fillable = [
        'delivery_request_id',
        'uploaded_by',
        'proof_type',
        'file_path',
        'receiver_name',
    ];

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
