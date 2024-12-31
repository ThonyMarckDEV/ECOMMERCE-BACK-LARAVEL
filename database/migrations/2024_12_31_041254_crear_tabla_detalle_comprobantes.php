<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('detalle_comprobantes', function (Blueprint $table) {
            $table->id('idDetalleComprobante');
            $table->unsignedBigInteger('idComprobante');
            $table->unsignedBigInteger('idProducto');
            $table->unsignedBigInteger('idTalla');
            $table->unsignedBigInteger('idModelo');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->foreign('idComprobante')
                  ->references('idComprobante')
                  ->on('comprobantes')
                  ->onDelete('cascade');
                  
            // Asumiendo que existen las tablas productos, tallas y modelos
            $table->foreign('idProducto')
                  ->references('idProducto')
                  ->on('productos')
                  ->onDelete('cascade');
                  
            $table->foreign('idTalla')
                  ->references('idTalla')
                  ->on('tallas')
                  ->onDelete('cascade');
                  
            $table->foreign('idModelo')
                  ->references('idModelo')
                  ->on('modelos')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('detalle_comprobantes');
    }
};