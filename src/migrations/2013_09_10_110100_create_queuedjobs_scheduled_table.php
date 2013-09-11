<?php

use Illuminate\Database\Migrations\Migration;

class CreateQueuedjobsScheduledTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('queuedjobs_scheduled_jobs', function($table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('enabled');
            $table->string('state');
            $table->integer('restart_count');
            $table->string('jobclass');
            $table->string('serializedVars');
            $table->dateTime('execution_date');
            $table->dateTime('started_date');
            $table->integer('progress');
            $table->integer('last_progress');
            $table->dateTime('last_progress_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('queuedjobs_scheduled_jobs');
    }

}