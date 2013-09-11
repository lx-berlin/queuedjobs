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

class TestStalledJob implements QueuedJobExecutable {

    static $name = 'TestStalledJob';

    function setup($additionalExecParams, $logger) {
        $logger->log('info', 'Setting up job ... ');
    }

    function execute($additionalExecParams, $lastProgress, $logger) {
        $logger->log('info', 'Executing job ... ');


        for ($i = $lastProgress + 1;  $i < 500; $i++) {

            // do something useful here
            sleep(1);

            // SIMULATES A JOB THAT HANGS 20 SECONDS AFTER START OR RESTART!:
            if ($i>$lastProgress + 20) {
                sleep(1000);
            }

            // log current progress
            $logger->log('info', 'Logging Progress '.$i.' ...');
            QueuedJobEngine::updateProgress(self::$name, $i);
        }

        return null;
    }

    // this gets called at run time as step 3 (the process is 'setup'->'execute'->cleanUp)
    // don't forget to log your progress
    function cleanUp($additionalExecParams, $logger) {
        $logger->log('info', 'Cleaning up job ... ');
    }

}

