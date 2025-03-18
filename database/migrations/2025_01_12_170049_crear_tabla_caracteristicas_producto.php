<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caracteristicas_producto', function (Blueprint $table) {
            $table->id('idCaracteristica');
            $table->unsignedBigInteger('idProducto');
            $table->text('caracteristicas')->nullable(); // Campo para almacenar texto largo
    
            // Clave foránea
            $table->foreign('idProducto')
                  ->references('idProducto')
                  ->on('productos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caracteristicas_producto');
    }
};