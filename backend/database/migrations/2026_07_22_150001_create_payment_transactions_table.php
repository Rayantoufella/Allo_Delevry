<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_request_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('reference')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('MAD');
            $table->string('status')->default('pending');
            $table->string('environment')->default('sandbox');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
