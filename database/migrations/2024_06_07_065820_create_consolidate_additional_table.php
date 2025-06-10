<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidateAdditionalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consolidate_additional', function (Blueprint $table) {
            $table->id();
            $table->integer('consolidate_id')->nullable();
            $table->double('Target_Revenue');
            $table->boolean('isHoliday');
            $table->date('cons_date')->nullable();
            $table->string('cons_store')->nullable();
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
        Schema::dropIfExists('consolidate_additional');
    }
}
