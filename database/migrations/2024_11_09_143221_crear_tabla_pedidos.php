<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_pedidos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class CrearTablaPedidos extends Migration
{
    public function up()
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id('idPedido')->unsigned();;
            $table->unsignedBigInteger('idUsuario');
            $table->decimal('total', 10, 2);
            $table->string('estado', 255)->default('pendiente');
            $table->string('departamento');
            $table->string('distrito');
            $table->string('provincia');
            $table->string('direccion', 255); 
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->timestamp('fecha_pedido')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('tipo_comprobante',255)->nullable();
            $table->string('ruc', 11)->nullable();

            // Claves foráneas
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedidos');
    }
}
