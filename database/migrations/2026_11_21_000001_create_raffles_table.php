<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rifas.
     *
     * numbers: JSON com um item por número (formato legado, lido pelo JavaScript)
     *   {"pay":"N|S","number":1,"situation":"D|R|I","customer_id":null,"date":null}
     *   D = disponível, R = reservado, I = indisponível (pago/confirmado)
     * winners: JSON com os números sorteados [{"number","name","email","phone"}]
     * status: 1 = aguardando pagamento da plataforma, 2 = liberada/em andamento, 3 = finalizada
     */
    public function up(): void
    {
        Schema::create('raffles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('share_code')->unique();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->longText('draw_rules')->nullable();
            $table->decimal('number_price', 10, 2)->default(0);
            $table->unsignedInteger('total_numbers')->default(0);
            $table->json('numbers')->nullable();
            $table->json('winners')->nullable();
            $table->json('photos')->nullable();
            $table->dateTime('draw_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffles');
    }
};
