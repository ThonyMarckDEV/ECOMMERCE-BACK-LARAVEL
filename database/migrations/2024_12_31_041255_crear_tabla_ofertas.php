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
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id('idOferta'); // Clave primaria
            $table->string('descripcion');
            $table->decimal('porcentajeDescuento', 5, 2); // Porcentaje con 2 decimales
            $table->date('fechaInicio');
            $table->date('fechaFin');
            $table->boolean('estado')->default(true); // Estado activo/inactivo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofertas');
    }
};