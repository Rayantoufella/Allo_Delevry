<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_request_id',
        'changed_by',
        'old_status',
        'new_status',
        'comment',
    ];

    public function deliveryRequest()
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
