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

class CompletedJob extends \Eloquent{
    
    protected $table = 'queuedjobs_completed_jobs';
    public $timestamps = false;
    protected $fillable = array('name', 'return', 'started_date', 'finished_date', 'runtime');
    
    public function manager() {
        return $this->belongsTo('\lxberlin\QueuedJobs\models\Manager', 'manager_id');
    }
    
}