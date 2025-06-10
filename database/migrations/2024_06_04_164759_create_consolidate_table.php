<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consolidate', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->double('Target_Revenue')->nullable();
            $table->double('Actual_Revenue');
            $table->integer('Pax');
            $table->double('Average_Pax');
            $table->double('Main_Course');
            $table->double('Side_Dish');
            $table->double('Dessert');
            $table->double('Beverage');
            $table->string('store');
            $table->int('client_id');
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
        Schema::dropIfExists('consolidate');
    }
}
