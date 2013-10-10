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

class Manager extends \Eloquent{
    
    protected $table = 'queuedjobs_manager';
    public $timestamps = false;
    protected $fillable = array('rundate', 'runtime');
    
    public function queuedJobs() {
        return $this->hasMany('\lxberlin\QueuedJobs\models\CompletedJob', 'manager_id');
    }
    
    
}