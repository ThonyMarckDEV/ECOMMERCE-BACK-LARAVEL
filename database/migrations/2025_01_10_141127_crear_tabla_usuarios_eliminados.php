<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaUsuariosEliminados extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuarios_eliminados', function (Blueprint $table) {
            $table->id('idEliminado'); // Columna `idEliminado` como clave primaria autoincremental
            $table->unsignedBigInteger('idUsuario')->nullable(); // Columna `idUsuario` que puede ser nula
            $table->string('nombres', 100)->nullable(); // Columna `nombres` que puede ser nula
            $table->string('apellidos', 100)->nullable(); // Columna `apellidos` que puede ser nula
            $table->string('dni', 255)->nullable(); // Columna `dni` que puede ser nula
            $table->string('correo', 100)->nullable(); // Columna `correo` que puede ser nula
            $table->dateTime('fecha_creado')->nullable(); // Columna `fecha_creado` que puede ser nula
            $table->dateTime('fecha_eliminado')->useCurrent(); // Columna `fecha_eliminado` con valor predeterminado `current_timestamp()`
            $table->enum('estado', ['eliminado'])->default('eliminado'); // Columna `estado` con valor predeterminado
            $table->string('descripcion', 255)->default('Se ha eliminado su cuenta por no verificar.'); // Columna `descripcion` con valor predeterminado
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuarios_eliminados');
    }
}