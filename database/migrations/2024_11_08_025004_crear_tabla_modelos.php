<?php

// database/migrations/xxxx_xx_xx_create_modelos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaModelos extends Migration
{
    public function up()
    {
        Schema::create('modelos', function (Blueprint $table) {
            $table->id('idModelo'); // Correcto
            $table->unsignedBigInteger('idProducto'); // Definimos manualmente la columna
            $table->foreign('idProducto')->references('idProducto')->on('productos'); // Referencia explícita a 'idProducto'
            $table->string('nombreModelo');
            $table->string('descripcion')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('modelos');
    }
}
