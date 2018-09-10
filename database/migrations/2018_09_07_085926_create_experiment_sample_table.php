<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExperimentSampleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('experiment_sample', function (Blueprint $table) {
            $table->bigInteger('sample_id')->unsigned();
            $table->bigInteger('experiment_id')->unsigned();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->primary(['sample_id', 'experiment_id']);
            $table->foreign('sample_id')->references('id')->on('samples')->onDelete('CASCADE');
            $table->foreign('experiment_id')->references('id')->on('experiments')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('experiment_sample');
    }
}