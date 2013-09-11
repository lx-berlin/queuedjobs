<?php

use Illuminate\Database\Migrations\Migration;

class CreateQueuedjobsManagerTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('queuedjobs_manager', function($table) {
                    $table->increments('id');
                    $table->dateTime('rundate');
                    $table->float('runtime');
                });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('queuedjobs_manager');
    }

}