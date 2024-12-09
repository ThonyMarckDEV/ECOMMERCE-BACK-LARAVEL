<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaImagenModelos extends Migration
{
    public function up()
    {
        Schema::create('imagenes_modelo', function (Blueprint $table) {
            $table->id('idImagen');
            $table->unsignedBigInteger('idModelo'); // Relación con la tabla modelos
            $table->foreign('idModelo')->references('idModelo')->on('modelos'); // Relación con idModelo
            $table->string('urlImagen');
            $table->string('descripcion')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('imagenes_modelo');
    }
}