<?php
/**
 * Demo Job file. This is a demo job file that shows how to use the queuedjobs job scheduling
 *
 * @author      Marc Liebig & lx-berlin
 * @copyright   2013 Marc Liebig & lx-berlin
 * @link        https://github.com/lx-berlin/queuedjobs/
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     QueuedJobs
 *
 * Please find more copyright information in the LICENSE file
 */

namespace lxberlin\QueuedJobs\tests;

use lxberlin\QueuedJobs\QueuedJobExecutable;
use lxberlin\QueuedJobs\QueuedJobEngine;

class TestJob2 implements QueuedJobExecutable {

    static $name = 'TestJob2';

    function getUniqueName($additionalExecParams, $logger) {
        return self::$name;
    }

    function preExecute($additionalExecParams, $logger) {
        $logger->log('info', 'Setting up job ... ');
    }

    function execute($additionalExecParams, $lastProgress, $logger) {
        $logger->log('info', 'Executing job ... ');


        for ($i = $lastProgress + 1;  $i < 60; $i++) {

            // do something useful here
            sleep(1);

            // log current progress
            $logger->log('info', 'Logging Progress '.$i.' ...');
            QueuedJobEngine::updateProgress(self::$name, $i);
        }

        return null;
    }

    // this gets called at run time as step 3 (the process is 'setup'->'execute'->cleanUp)
    // don't forget to log your progress
    function postExecute($additionalExecParams, $logger) {
        $logger->log('info', 'Cleaning up job ... ');
    }

}
