<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ProductosOfertas', function (Blueprint $table) {
            $table->id('idOfertaProducto'); // Clave primaria
            $table->unsignedBigInteger('idProducto'); // Clave foránea a productos
            $table->unsignedBigInteger('idOferta'); // Clave foránea a ofertas

            // Definir las claves foráneas
            $table->foreign('idProducto')->references('idProducto')->on('productos')->onDelete('cascade');
            $table->foreign('idOferta')->references('idOferta')->on('ofertas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ProductosOfertas');
    }
};