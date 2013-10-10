<?php

use Illuminate\Database\Migrations\Migration;

class CreateQueuedjobsCompletedTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('queuedjobs_completed_jobs', function($table) {
                    $table->increments('id');
                    $table->string('name');
                    $table->text('return');
                    $table->dateTime('started_date');
                    $table->dateTime('finished_date');
                    $table->float('runtime');
                    $table->integer('manager_id');
                });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('queuedjobs_completed_jobs');
    }

}