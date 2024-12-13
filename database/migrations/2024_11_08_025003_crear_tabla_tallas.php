<?php

// database/migrations/xxxx_xx_xx_create_tallas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTallas extends Migration
{
    public function up()
    {
        Schema::create('tallas', function (Blueprint $table) {
            $table->id('idTalla');
            $table->string('nombreTalla'); // Ejemplo: S, M, L, XL
        });
    }

    public function down()
    {
        Schema::dropIfExists('tallas');
    }
}
