<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->string('private_token')->unique();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_request_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('pickup_address');
            $table->string('delivery_address');
            $table->text('package_description')->nullable();
            $table->decimal('product_amount', 10, 2)->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->decimal('proposed_price', 10, 2)->nullable();
            $table->string('confirmation_code_hash')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->enum('status', ['en_attente', 'prix_propose', 'confirmee', 'colis_recupere', 'en_livraison', 'livree', 'refusee', 'echec', 'annulee'])->default('en_attente');
            $table->index('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_requests');
    }
};
