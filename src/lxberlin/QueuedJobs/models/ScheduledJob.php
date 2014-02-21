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

use lxberlin\QueuedJobs\JobState;

class ScheduledJob extends \Eloquent{

    protected $table = 'queuedjobs_scheduled_jobs';
    public $timestamps = false;
    protected $fillable = array('name', 'enabled', 'state', 'restart_count', 'jobclass', 'serializedVars', 'execution_date', 'started_date', 'progress', 'last_progress', 'last_progress_date');


    public static function boot() {
        parent::boot();

        ScheduledJob::updating(function ($model) {

            // IN ORDER TO AVOID RACE CONDITIONS: check again whether the state column has been updated in between; if so don't update here!
            $dirtyFields = $model->getDirty();

            if (array_key_exists('state', $dirtyFields)) {
                $savedModel = ScheduledJob::find($model->id);
                return ($savedModel->state != JobState::RUNNING);
            }
            else {
                // all other update operations are of course allowed:
                return true;
            }
        });
    }
}