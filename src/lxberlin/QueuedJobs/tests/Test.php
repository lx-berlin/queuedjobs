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

class Test {

    static $name = 'TestJob1';

    static function startTests1() {

        QueuedJobEngine::setDefaultConfigValues();

        // this has to be done every time:
        $pathToLogfile = 'job-logger.txt';
        $logger = new \Monolog\Logger('job-logger');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($pathToLogfile, \Monolog\Logger::DEBUG));
        QueuedJobEngine::setLogger($logger);

        QueuedJobEngine::add(new \DateTime('2013-09-09 15:10:00'), 'lxberlin\QueuedJobs\tests\TestJob1');
        QueuedJobEngine::add(new \DateTime('2013-09-09 15:08:00'), 'lxberlin\QueuedJobs\tests\TestJob2');
        QueuedJobEngine::add(new \DateTime('2013-09-11 15:12:00'), 'lxberlin\QueuedJobs\tests\TestJob3');
        QueuedJobEngine::add(new \DateTime('2013-09-09 15:14:00'), 'lxberlin\QueuedJobs\tests\TestStalledJob');
    }
}
