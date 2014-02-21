<?php

/**
 * Job scheduling for Laravel
 *
 * @author      Gunnar Matz & lx-berlin
 * @copyright   2013 Gunnar Matz & lx-berlin (originally based on Marc Liebig's QueuedJobs Job code)
 * @link        https://github.com/lx-berlin/queuedjobs/
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     QueuedJobs
 *
 * Please find more copyright information in the LICENSE file
 */

namespace lxberlin\QueuedJobs;

use lxberlin\QueuedJobs\models\CompletedJob;
use lxberlin\QueuedJobs\models\Manager;
use lxberlin\QueuedJobs\models\ScheduledJob;

use \DateTime;


class JobState {
    const QUEUED   = 1;
    const RUNNING  = 2;
}

class QueuedJobEngine {

    private static $DEBUGMODE =1;

    /**
     * @static
     * @var \Monolog\Logger Logger object if logging is requested or null if nothing should be logged.
     */
    private static $logger;

    /**
     * Add a job
     *
     * @static
     * @param  DateTime $dateTime The date and time when to execute this job
     * @param string $jobClass The name of the job class conforming to QueuedJobExecutable that includes an executable function
     * @param  $additionalExecParams array of additional execution params
     * @param boolean $isEnabled optional If the queuedjobs job is enabled or not - the standard configuration is true
     * @param integer $progress the current job's progress -> usually -1 at the beginning or >=0 when executing
     * @param integer $restartCount the iteration count how many times this job has been restarted so far
     * @return void|false Return void if everything worked and false if there is any error
     */
    public static function add(\DateTime $dateTime, $jobClass, array $additionalExecParams = null,  $isEnabled = true, $progress = -1, $restartCount = 0) {

        self::initDefaultLogger();

        // Check if the given datetime is set
        if (!isset($dateTime)) {
            return false;
        }

        // Check if the isEnabled boolean is okay, if not use the standard 'true' configuration
        if (!is_bool($isEnabled)) {
            $isEnabled = true;
        }

        // instantiate job to get the name:
        $job = new $jobClass;
        $name= $job->getUniqueName($additionalExecParams, self::$logger);

        // Check if the name is unique
        $allScheduledJobs = ScheduledJob::all();

        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $name) {
                self::log('info', 'Could not add job to database (name is not unique):'.self::logJob($scheduledJob), true);
                return false;
            }
        }

        // retrieve all accessible non-static vars:
        $serializedVars = '';
        if ($additionalExecParams != null) {
            $serializedVars = serialize($additionalExecParams);
        }

        // save to database:
        $scheduledJob = new ScheduledJob();
        $scheduledJob->name               = $name;
        $scheduledJob->enabled            = $isEnabled;
        $scheduledJob->state              = JobState::QUEUED;
        $scheduledJob->restart_count      = $restartCount;
        $scheduledJob->jobclass           = $jobClass;
        $scheduledJob->serializedVars     = $serializedVars;
        $scheduledJob->execution_date     = $dateTime;
        $scheduledJob->progress           = $progress;
        $scheduledJob->last_progress      = $progress - 1;
        $scheduledJob->last_progress_date = new \DateTime();
        $scheduledJob->save();

        self::log('info', 'Added job to database:'.self::logJob($scheduledJob), true);
    }

    /**
     * Remove a queuedjobs job from execution by name
     * 
     * @static
     * @param string $name The name of the queuedjobs job which should be removed from execution
     * @return void|false Return null if a queuedjobs job with the given name was found and was successfully removed or return false if no job with the given name was found
     */
    public static function remove($name) {

        self::initDefaultLogger();

        $allScheduledJobs = ScheduledJob::all();

        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $name) {
                self::log('info', 'Found job to delete:'.self::logJob($scheduledJob));
                //echo 'Removed job';
                $scheduledJob->delete();
                return null;
            }
        }

        self::log('info', 'Could not find job to delete:'.$name, true);

        return false;
    }

    /**
     * Run the queuedjobs jobs
     * This method checks and runs all the defined queuedjobs jobs at the right time
     * This method (route) should be called automatically by a server or service
     * 
     * @static
     * @return array Return an array with the rundate, runtime, errors and a result queuedjobs job array (with name, function return value, rundate and runtime)
     */
    public static function run() {
        self::initDefaultLogger();
        self::log('info', 'Called run engine ...');

        // Get the rundate
        $runDate = new \DateTime();

        // Get the run interval from Laravel config
        $runInterval = self::getRunInterval();

        // Get the time (in seconds) between this and the last run and save this to $timeBetween
        $lastManager = Manager::orderBy('rundate', 'DESC')->take(1)->get();
        if (!empty($lastManager[0])) {
            $lastRun = new \DateTime($lastManager[0]->rundate);
            $timeBetween = $runDate->getTimestamp() - $lastRun->getTimestamp();
        } else {
            // No previous queuedjobs job runs are found
            $timeBetween = -1;
        }

        // Initialize the job and job error array and start the runtime calculation
        $allJobs = array();
        $errorJobs = array();
        $beforeAll = microtime(true);

        while (true) {

            self::log('info', 'Now checking for executable jobs...');

            $allScheduledJobs = ScheduledJob::all();

            if ($allScheduledJobs->isEmpty()) break;

            $foundJobToExecute = false;
            foreach ($allScheduledJobs as $scheduledJob) {
                $name              = $scheduledJob->name;
                $isEnabled         = $scheduledJob->enabled;
                $db_state          = $scheduledJob->state;
                $restartCount      = $scheduledJob->restart_count;
                $jobClass          = $scheduledJob->jobclass;
                $serializedVars    = $scheduledJob->serializedVars;
                $executionDate     = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledJob->execution_date);
                $progress          = $scheduledJob->progress;
                $lastProgress      = $scheduledJob->last_progress;

                self::log('info', 'Now checking job:'.self::logJob($scheduledJob), true);

                // if the job is already running then check if it's still progressing and not stalled.
                // if the job is not already running, then try to start etc.:
                if ($db_state == JobState::RUNNING) {
                    self::log('info', 'Job is already running :'.self::logJob($scheduledJob), true);

                    if ($progress <= $lastProgress) {
                        self::log('warning', 'Job seems to be stalled:'.self::logJob($scheduledJob), true);

                        // ATTENTION: In this case we assume that the job hangs for a long while or has even been completely killed by the operating system
                        // THE PROBLEM: if we think that this job has been executed and we then re-start it but the truth is that this job resumes after a while,
                        // then it could be executed TWICE!

                        // remove this job from the queue ...
                        self::remove($name);
                        // ... and then add it again with the current progress parameter:
                        self::add($executionDate, $jobClass, unserialize($serializedVars), $isEnabled, $progress, $restartCount + 1);

                        $emailNotifications = \Config::get('queuedjobs::emailNotifications');
                        if ($emailNotifications) {
                            $addressee = \Config::get('queuedjobs::emailNotificationsReceiverAddress');
                            $contextVars = ['infos' => self::logJob($scheduledJob)];
                            \Mail::queue('queuedjobs::mailTemplate', $contextVars, function($message) use ($addressee) {
                                $message->to($addressee, "Queued Jobs Admin")->subject('QueuedJobs: Job requeued!');
                            });
                        }

                        self::log('info', 'Requeued job:'.self::logJob($scheduledJob), true);

                    }
                    else {

                        self::log('info', 'Trying to update last_progress for job:'.self::logJob($scheduledJob));

                        // now write last_progress to scheduled job:
                        $scheduledJob->last_progress  = $progress;
                        $scheduledJob->save();

                        self::log('info', 'Updated last_progress for job:'.self::logJob($scheduledJob), true);
                    }
                }
                else {
                    // If the job is enabled and if the time for this job has come :
                    if ($isEnabled == 1 && self::isJobOverDue($executionDate)) {

                        $foundJobToExecute = true;

                        self::log('info', 'Starting job:'.self::logJob($scheduledJob));

                        $started_date = new \DateTime();

                        // now write started_date to original scheduled job:
                        $scheduledJob->state        = JobState::RUNNING;
                        $scheduledJob->started_date = $started_date;
                        $savedSuccessfully = $scheduledJob->save();

                        // in very rare cases there could have been a race condition (when another engine process took the same job)
                        if (!$savedSuccessfully) {
                            break;
                        }


                        $myInstance = new $jobClass();

                        // get the context vars:
                        $vars = unserialize($serializedVars);

                        $beforeOne = 0;
                        $afterOne  = 0;
                        $finalProgress = -1;
                        $return = NULL;
                        $exception = NULL;
                        try {
                            // setup (execute only when first run (NOT with restarted jobs)):
                            if ($restartCount == 0) {
                                self::log('info', 'Now setting up job:'.self::logJob($scheduledJob));
                                $myInstance->preExecute($vars, self::$logger);
                            }

                            // Because it could have been that the job added in preExecute more params, we have to reload the params:
                            // So walk through the queuedjobs jobs and find the job with the given name
                            $reloadedJob     = ScheduledJob::find($scheduledJob->id);
                            $serializedVars  = $reloadedJob->serializedVars;
                            $vars = unserialize($serializedVars);


                            // Get the start time of the job runtime
                            $beforeOne = microtime(true);

                            // Run the function and save the return to $return - all the magic goes here
                            self::log('info', 'Now executing job:'.self::logJob($scheduledJob));
                            $return = $myInstance->execute($vars, $progress, self::$logger);

                            // Get the end time of the job runtime
                            $afterOne = microtime(true);

                            // clean up
                            self::log('info', 'Now cleaning up job:'.self::logJob($scheduledJob));
                            $myInstance->postExecute($vars, self::$logger);

                            // because the progress could/should have changed in between, we re-get it:
                            $reloadedJob = ScheduledJob::find($scheduledJob->id);
                            $finalProgress = $reloadedJob->progress;

                        }
                        catch (\Exception $e) {
                            self::log('error', 'Error in preExecute, execute or postExecute of job:'.self::logJob($scheduledJob).' Exception:'.$e->getMessage());
                            $exception = $e;
                        }



                        // If the function returned not null then we assume that there was an error
                        if ($return != NULL || $exception) {
                            // Add to error array
                            array_push($errorJobs, array('name' => $name, 'return' => $return, 'started_date' => $started_date, 'finished_date' => new \DateTime(), 'runtime' => ($afterOne - $beforeOne), 'final_progress' => $finalProgress));
                        }
                        else {
                            // Push the information of the ran queued job to the allJobs array (including name, return value, runtime)
                            array_push($allJobs, array('name' => $name, 'return' => $return, 'started_date' => $started_date, 'finished_date' => new \DateTime(), 'runtime' => ($afterOne - $beforeOne), 'final_progress' => $finalProgress));

                            // finally remove the job from the queue.
                            self::remove($name);
                            self::log('info', 'Now removed job from queue:'.self::logJob($scheduledJob));
                        }

                        // after one job has been executed try to find the next one (because another manager could have started some jobs in the meantime):
                        break;
                    }
                }
            }
            if (!$foundJobToExecute) break;
        }

        // Get the end runtime for all the queuedjobs jobs
        $afterAll = microtime(true);

        // save manager und jobs to db

        $cronmanager = new Manager();
        $cronmanager->rundate = $runDate;
        $cronmanager->runtime = $afterAll - $beforeAll;
        $cronmanager->save();

        // Check if the run between this run and the last run is in good time (30 seconds tolerance) or not and log this event
        if ($timeBetween === -1) {
            self::log('warning', 'QueuedJobs run with manager id ' . $cronmanager->id . ' has no previous ran jobs.');
        } elseif (($runInterval * 60) - $timeBetween <= -30) {
            self::log('error', 'QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too late.');
        } elseif (($runInterval * 60) - $timeBetween >= 30) {
            self::log('error', 'QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run maybe too fast.');
        } else {
            self::log('info', 'QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run in time.');
        }

        self::saveJobsFromArrayToDatabase($allJobs, $cronmanager->id);

        // Log the result of the queuedjobs run
        if (empty($errorJobs)) {
            self::log('info', 'The queuedjobs run with the manager id ' . $cronmanager->id . ' was finished without errors.');
        } else {
            self::log('error', 'The queuedjobs run with the manager id ' . $cronmanager->id . ' was finished with ' . count($errorJobs) . ' errors.');
        }

        // Return the queuedjobs jobs array (including rundate, in time boolean, runtime, number of errors and an array with the queuedjobs jobs report)
        return array('rundate' => $runDate->getTimestamp(), 'runtime' => ($afterAll - $beforeAll), 'errors' => count($errorJobs), 'crons' => $allJobs);
    }

    /**
     * Save the jobs progress in order to prevent it from being considered as stalled
     *
     * @param string $jobName the job's name
     * @param int $progress the integer value indicating the progress
     */
    public static function updateProgress($jobName, $progress) {

        self::initDefaultLogger();

        self::log('info', 'Updating progress for job:'.$jobName.': '.$progress);

        $allScheduledJobs = ScheduledJob::all();
        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $jobName) {

                $scheduledJob->progress      = $progress;
                $scheduledJob->last_progress_date = new \DateTime;
                $scheduledJob->save();
                break;
            }
        }
    }


    // ------- getters & setters ------------------------------------------------------------


    public static function setLogger(\Monolog\Logger $logger = null) {
       $myCronLogger = new QueuedJobLogger();
       $myCronLogger->setLogger($logger);
       self::$logger = $myCronLogger;
    }

    public static function getLogger() {
        return self::$logger;
    }

    public static function getRunInterval() {
        $interval = \Config::get('queuedjobs::runInterval');
        if (is_int($interval)) {
            return $interval;
        } else {
            return null;
        }
    }

    public static function setEnableJob($jobname, $enable = true) {
        self::initDefaultLogger();

        // Check parameter
        if (!is_bool($enable)) {
            return false;
        }

        // Walk through the queuedjobs jobs and find the job with the given name
        // Check if the name is unique
        $allScheduledJobs = ScheduledJob::all();
        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $jobname) {
                $scheduledJob->enabled=$enable;
                $scheduledJob->save();

                return null;
            }
        }

        return false;
    }

    public static function setDisableJob($jobname) {
        return self::setEnableJob($jobname, false);
    }

    public static function isJobEnabled($jobname) {
        self::initDefaultLogger();


        // Walk through the queuedjobs jobs and find the job with the given name
        $allScheduledJobs = ScheduledJob::all();
        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $jobname) {
                return $scheduledJob->enabled == 1 ? true : false;
            }
        }

        return null;
    }

    /**
     * Add more execution parameters to the current job
     *
     * @static
     * @param  String $jobname    The job's name
     * @param  String $moreParams Additional execution parameters
     * @return void
     */
    public static function addMoreExecParams($jobname, $moreParams) {
        // write new serialized vars to database!
        if ($moreParams != NULL) {

            // Walk through the queuedjobs jobs and find the job with the given name
            $allScheduledJobs = ScheduledJob::all();
            foreach ($allScheduledJobs as $scheduledJob) {
                if ($scheduledJob->name === $jobname) {

                    $serializedVars    = $scheduledJob->serializedVars;
                    $unserializedVars = unserialize($serializedVars);

                    $newFinalArray = array_merge($unserializedVars, $moreParams);

                    $scheduledJob->serializedVars = serialize($newFinalArray);
                    $scheduledJob->save();

                }
            }
        }
    }


    /**
     * Save queuedjobs jobs from an array to the database
     *
     * @static
     * @param  array $jobArray This array holds all the ran queuedjobs jobs which should be logged to database - entry structure must be job['name'], job['return'], job['runtime']
     * @param  int $managerId The id of the saved manager database object which cares about the jobs
     */
    private static function saveJobsFromArrayToDatabase($jobArray, $managerId) {

        foreach ($jobArray as $job) {
            $jobEntry = new CompletedJob();
            $jobEntry->name = $job['name'];

            // Get the type of the returned value
            $returnType = gettype($job['return']);

            // If the type is NULL there was no error running this job - insert empty string
            if ($returnType === 'NULL') {
                $jobEntry->return = '';
                // If the tyoe is boolean save the value as string
            } else if ($returnType === 'boolean') {
                if ($job['return']) {
                    $jobEntry->return = 'true';
                } else {
                    $jobEntry->return = 'false';
                }
                // If the type is integer, double or string we can cast it to String and save it to the error database object
            } else if ($returnType === 'integer' || $returnType === 'double' || $returnType === 'string') {
                // We cut the string at 500 characters to not overcharge the database
                $jobEntry->return = substr((string) $job['return'], 0, 500);
            } else {
                $jobEntry->return = 'Return value of job ' . $job['name'] . ' has the type ' . $returnType . ' - this type cannot be displayed as string (type error)';
            }

            $jobEntry->runtime        = $job['runtime'];
            $jobEntry->started_date   = $job['started_date'];
            $jobEntry->finished_date  = $job['finished_date'];
            $jobEntry->final_progress = $job['final_progress'];
            $jobEntry->manager_id     = $managerId;
            $jobEntry->save();
        }
    }




    /**
     * Checks if the given DateTime is *either* now due *or* has been due in the past (i. e. the $dateTime is overdue by now)
     *
     * @static
     * @param  DateTime $dateTime Date and Time of the job
     * @return void|false Return boolean if job is overdue or due by now
     */
    private static function isJobOverDue ($dateTime) {

        $currentTime = new \DateTime();

        $result = $dateTime <= $currentTime;

        self::log('info', 'Job is overdue: '.$currentTime->format('Y-m-d H:i:s').' runDate= '.$dateTime->format('Y-m-d H:i:s').' => '.$result);

        return $result;
    }


    // ------- logger functions --------------------------------------------------------------------------------------------------


    private static function logJob ($scheduledJob) {

        if ($scheduledJob instanceof ScheduledJob) {

            $result = ' name => '.$scheduledJob->name.' <br>'.
                ' enabled => '.$scheduledJob->enabled.' <br>'.
                ' state => '.$scheduledJob->state.' <br>'.
                ' restart_count => '.$scheduledJob->restart_count.' <br>'.
                ' job_class => '.$scheduledJob->jobclass.' <br>'.
                ' serialized_vars => '.$scheduledJob->serializedVars.' <br>'.
                ' started_date => ';

            $startedDateStr = 'NULL';
            if ($scheduledJob->started_date != null) {
                if ($scheduledJob->started_date instanceof DateTime) {
                    $startedDateStr = $scheduledJob->started_date->format('Y-m-d H:i:s');
                }
                else {
                    $startedDateStr = $scheduledJob->started_date;
                }
            }
            $result = $result.$startedDateStr.'<br>';

            $result = $result.' execution_date => ';

            $execDateStr = 'NULL';
            if ($scheduledJob->execution_date != null) {
                if ($scheduledJob->execution_date instanceof DateTime) {
                    $execDateStr = $scheduledJob->execution_date->format('Y-m-d H:i:s');
                }
                else {
                    $execDateStr = $scheduledJob->execution_date;
                }
            }
            $result = $result.$execDateStr.'<br>';

            $result = $result.' progress => '.$scheduledJob->progress.' last_progress => '.$scheduledJob->last_progress.'<br>';

            $lastProgressDateStr = 'NULL';
            if ($scheduledJob->last_progress_date != null) {
                if ($scheduledJob->last_progress_date instanceof DateTime) {
                    $lastProgressDateStr = $scheduledJob->last_progress_date->format('Y-m-d H:i:s');
                }
                else {
                    $lastProgressDateStr = $scheduledJob->last_progress_date;
                }
            }
            $result = $result.$lastProgressDateStr;

            return $result.'<br>';

        }
        else {
            return serialize($scheduledJob);

        }
    }

    private static function initDefaultLogger () {
        if (!self::$logger && \Config::get('queuedjobs::stdLogging')) {
            $logger =\Log::getMonolog();
            if ($logger) {
                self::setLogger($logger);
            }
        }
    }

    private static function log ($cat, $text, $echo = false) {
        self::$logger->log($cat, '[QUEUEDJOBS] '.$text);
        if ($echo || self::$DEBUGMODE) {
            echo '[QUEUEDJOBS] '.$text.'<br>';
        }
    }

}