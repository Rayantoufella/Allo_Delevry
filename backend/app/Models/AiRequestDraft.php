<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiRequestDraft extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'service_id',
        'input_message',
        'generated_data',
        'status',
        'error_message',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_data' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function deliveryRequests()
    {
        return $this->hasMany(DeliveryRequest::class);
    }
}
