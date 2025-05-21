<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaUsuarios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('idUsuario'); // Clave primaria
            $table->string('username', 255);
            $table->string('rol', 255);
            $table->string('nombres', 255);
            $table->string('apellidos', 255);
            $table->string('dni', 255)->nullable();
            $table->string('correo', 255);
            $table->integer('edad')->nullable();
            $table->string('nacimiento', 255)->nullable();
            $table->string('sexo', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 255)->nullable();
            $table->string('password', 255);
            $table->string('status', 255);
            $table->string('perfil')->nullable(); // Almacenar la ruta de la imagen
            $table->string('estado');
            $table->boolean('emailVerified')->default(false); // Campo para verificar el correo, por defecto false
            $table->string('verification_token', 60)->unique(); // Token único
            $table->timestamp('fecha_creado')->useCurrent(); // Fecha de creación por defecto con CURRENT_TIMESTAMP
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}
