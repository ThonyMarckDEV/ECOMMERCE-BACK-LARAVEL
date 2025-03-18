<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTipoPago extends Migration
{
    public function up()
    {
        Schema::create('tipo_pago', function (Blueprint $table) {
            $table->id('idTipoPago');
            $table->string('nombre', 50); // Nombre del tipo de pago (ej: "comprobante", "mercadopago")
            $table->boolean('status')->default(1); // 1 = activo, 0 = inactivo
        });
    }

    public function down()
    {
        Schema::dropIfExists('tipo_pago');
    }
}