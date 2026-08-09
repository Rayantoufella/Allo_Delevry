<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// driver_key est une colonne générée interne, support de l'unicité
// (email, livreur) : elle n'a aucun sens hors de la base.
#[Hidden(['password', 'remember_token', 'driver_key'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_CLIENT = 'client';

    const ROLE_DRIVER = 'driver';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'driver_id',
    ];

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function clients()
    {
        return $this->hasMany(User::class, 'driver_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function deliveryZones()
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function aiRequestDrafts()
    {
        return $this->hasMany(AiRequestDraft::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function clientRequests()
    {
        return $this->hasMany(DeliveryRequest::class, 'client_id');
    }

    public function driverRequests()
    {
        return $this->hasMany(DeliveryRequest::class, 'driver_id');
    }
}
