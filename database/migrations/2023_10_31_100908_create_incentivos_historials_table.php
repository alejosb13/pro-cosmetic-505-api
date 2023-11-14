<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIncentivosHistorialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incentivos_historials', function (Blueprint $table) {
            $table->id();

            // usuarios
            $table->unsignedBigInteger("user_id");
            $table->foreign("user_id")->references("id")->on("users");
            
            $table->double('porcentaje', 7, 2);
            $table->integer("estado")->length(1);
            
            $table->dateTime("fecha_indice",$precision = 0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('incentivos_historials');
    }
}
