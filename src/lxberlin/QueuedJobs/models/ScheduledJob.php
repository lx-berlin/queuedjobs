<?php

/**
 * QueuedJobs - Job scheduling for Laravel
 *
 * @author      Marc Liebig  & lx-berlin
 * @copyright   2013 Marc Liebig  & lx-berlin
 * @link        https://github.com/lx-berlin/queuedjobs/
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     QueuedJobs
 *
 * Please find more copyright information in the LICENSE file
 */


namespace lxberlin\QueuedJobs\models;

class ScheduledJob extends \Eloquent{

    protected $table = 'queuedjobs_scheduled_jobs';
    public $timestamps = false;
    protected $fillable = array('name', 'enabled', 'state', 'restart_count', 'jobclass', 'serializedVars', 'execution_date', 'started_date', 'progress', 'last_progress', 'last_progress_date');


}