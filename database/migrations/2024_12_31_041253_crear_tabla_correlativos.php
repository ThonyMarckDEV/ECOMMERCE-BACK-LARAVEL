<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('correlativos', function (Blueprint $table) {
            $table->id('idCorrelativo');
            $table->unsignedBigInteger('idTipoDocumento');
            $table->string( 'codigo', 2);
            $table->string('numero_serie', 4);
            $table->integer('numero_actual')->default(1);
            $table->timestamps();

            $table->foreign('idTipoDocumento')
                  ->references('idTipoDocumento')
                  ->on('tipo_documentos')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('correlativos');
    }
};