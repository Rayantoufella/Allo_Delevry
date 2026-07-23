<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_request_id',
        'provider',
        'reference',
        'amount',
        'currency',
        'status',
        'environment',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class);
    }
}
