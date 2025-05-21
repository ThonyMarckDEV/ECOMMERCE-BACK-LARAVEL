<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMantenimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mantenimiento', function (Blueprint $table) {
            $table->id(); // Esto crea una columna `id` autoincremental
            $table->tinyInteger('estado')->default(0); // Columna `estado` con valor predeterminado 0
            $table->string('mensaje', 255)->nullable(); // Columna `mensaje` que puede ser nula
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mantenimiento');
    }
}