<?php

namespace App\Providers;

use App\Models\AiRequestDraft;
use App\Models\ChatMessage;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\GpsLocation;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\RequestStatusHistory;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Policies\AiRequestDraftPolicy;
use App\Policies\ChatMessagePolicy;
use App\Policies\DeliveryProofPolicy;
use App\Policies\DeliveryRequestPolicy;
use App\Policies\DeliveryZonePolicy;
use App\Policies\DriverProfilePolicy;
use App\Policies\GpsLocationPolicy;
use App\Policies\IncidentPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PaymentTransactionPolicy;
use App\Policies\RequestStatusHistoryPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(DeliveryZone::class, DeliveryZonePolicy::class);
        Gate::policy(DriverProfile::class, DriverProfilePolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(DeliveryRequest::class, DeliveryRequestPolicy::class);
        Gate::policy(AiRequestDraft::class, AiRequestDraftPolicy::class);
        Gate::policy(ChatMessage::class, ChatMessagePolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(DeliveryProof::class, DeliveryProofPolicy::class);
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(GpsLocation::class, GpsLocationPolicy::class);
        Gate::policy(PaymentTransaction::class, PaymentTransactionPolicy::class);
        Gate::policy(RequestStatusHistory::class, RequestStatusHistoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
