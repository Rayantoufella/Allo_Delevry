<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    use HasFactory;

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
