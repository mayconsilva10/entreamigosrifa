<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internacionalização: moeda da rifa (ISO 4217) e idioma preferido do cliente.
 * Rifas existentes ficam em BRL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->char('currency', 3)->default('BRL')->after('number_price');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
