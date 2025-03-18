<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tipo_documentos', function (Blueprint $table) {
            $table->id('idTipoDocumento');
            $table->string('codigo', 2);
            $table->string('descripcion');
            $table->string('abreviatura', 1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tipo_documentos');
    }
};