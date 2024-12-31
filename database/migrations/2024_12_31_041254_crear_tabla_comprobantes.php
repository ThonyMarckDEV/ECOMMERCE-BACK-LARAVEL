<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id('idComprobante');
            $table->unsignedBigInteger('idTipoDocumento');
            $table->unsignedBigInteger('idPedido');
            $table->unsignedBigInteger('idUsuario');
            $table->string('serie', 4);
            $table->integer('correlativo');
            $table->dateTime('fecha_emision');
            $table->decimal('sub_total', 10, 2);
            $table->decimal('mto_total', 10, 2);
            $table->timestamps();

            $table->foreign('idTipoDocumento')
                  ->references('idTipoDocumento')
                  ->on('tipo_documentos')
                  ->onDelete('cascade');
            
            // Asumiendo que existen las tablas pedidos y usuarios
            $table->foreign('idPedido')
                  ->references('idPedido')
                  ->on('pedidos')
                  ->onDelete('cascade');
            
            $table->foreign('idUsuario')
                  ->references('idUsuario')
                  ->on('usuarios')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comprobantes');
    }
};