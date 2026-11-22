<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagamentos recebidos pela plataforma (liberação de rifas).
     * gateway_reference = id do pagamento no gateway; payload = dados retornados pelo gateway.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_reference', 100)->nullable()->index();
            $table->string('status', 30)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
