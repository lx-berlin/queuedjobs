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
use Symfony\Component\Translation\Tests\String;


class JobState {
    const QUEUED   = 1;
    const RUNNING  = 2;
}

/**
 * QueuedJobs
 *
 * QueuedJobs job management
 *
 * @package QueuedJobs
 * @author  Gunnar Matz & lx-berlin (originally based on Marc Liebig's QueuedJobs Job code)
 * @since   1.0.0
 */
class QueuedJobEngine {


    /**
     * @static
     * @var array Saves all the queuedjobs jobs
     */
    private static $cronJobs = array();

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

        self::initLoggerAndConfig();

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
                //echo 'Could not add job -> duplicate name';
                self::$logger->log('info', 'GENERAL: Could not add job to database (name is not unique):'.self::logJob($scheduledJob));
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

        self::$logger->log('info', 'GENERAL: Added job to database:'.self::logJob($scheduledJob));

        //echo 'Added job';
    }

    /**
     * Remove a queuedjobs job from execution by name
     * 
     * @static
     * @param string $name The name of the queuedjobs job which should be removed from execution
     * @return void|false Return null if a queuedjobs job with the given name was found and was successfully removed or return false if no job with the given name was found
     */
    public static function remove($name) {

        self::initLoggerAndConfig();

        $allScheduledJobs = ScheduledJob::all();

        foreach ($allScheduledJobs as $scheduledJob) {
            if ($scheduledJob->name === $name) {
                self::$logger->log('info', 'GENERAL: Found job to delete:'.self::logJob($scheduledJob));
                //echo 'Removed job';
                $scheduledJob->delete();
                return null;
            }
        }

        self::$logger->log('info', 'GENERAL: Could not find job to delete:'.$name);
        //echo 'Could not find job';

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
        //echo "........";
        self::initLoggerAndConfig();

        // Get the rundate
        $runDate = new \DateTime();

        // Get the run interval from Laravel config
        $runInterval = self::getRunInterval();

        // Getting last run time only if database logging is enabled
        if (self::isDatabaseLogging()) {
            // Get the time (in seconds) between this and the last run and save this to $timeBetween
            $lastManager = Manager::orderBy('rundate', 'DESC')->take(1)->get();
            if (!empty($lastManager[0])) {
                $lastRun = new \DateTime($lastManager[0]->rundate);
                $timeBetween = $runDate->getTimestamp() - $lastRun->getTimestamp();
            } else {
                // No previous queuedjobs job runs are found
                $timeBetween = -1;
            }
            // If database logging is disabled
        } else {
            // Cannot check if the queuedjobs run is in time
            $inTime = -1;
        }

        // Initialize the job and job error array and start the runtime calculation
        $allJobs = array();
        $errorJobs = array();
        $beforeAll = microtime(true);

        while (true) {

            self::$logger->log('info', 'GENERAL: Now checking for executable jobs...');

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

                self::$logger->log('info', 'GENERAL: Now checking job:'.self::logJob($scheduledJob));

                // if the job is already running then check if it's still progressing and not stalled.
                // if the job is not already running, then try to start etc.:
                if ($db_state == JobState::RUNNING) {

                    self::$logger->log('info', 'GENERAL: Job is already running :'.self::logJob($scheduledJob));

                    if ($progress <= $lastProgress) {
                        echo 'HANGING'.$progress.' '.$lastProgress.' -> '.$name;
                        self::$logger->log('warning', 'GENERAL: Job seems to be stalled:'.self::logJob($scheduledJob));

                        // ATTENTION: In this case we assume that the job hangs for a long while or has even been completely killed by the operating system
                        // THE PROBLEM: if we think that this job has been executed and we then re-start it but the truth is that this job resumes after a while,
                        // then it could be executed TWICE!

                        // remove this job from the queue ...
                        self::remove($name);
                        // ... and then add it again with the current progress parameter:
                        self::add($executionDate, $jobClass, unserialize($serializedVars), $isEnabled, $progress, $restartCount + 1);

                        self::$logger->log('info', 'GENERAL: Requeued job:'.self::logJob($scheduledJob));

                    }
                    else {

                        self::$logger->log('info', 'Trying to update last_progress for job:'.self::logJob($scheduledJob));

                        // now write last_progress to scheduled job:
                        foreach ($allScheduledJobs as $scheduledJob) {
                            if ($scheduledJob->name === $name) {

                                self::$logger->log('info', 'Updated last_progress for job:'.self::logJob($scheduledJob));

                                $scheduledJob->last_progress  = $progress;
                                $scheduledJob->save();
                                break;
                            }
                        }
                    }
                }
                else {
                    // If the job is enabled and if the time for this job has come :
                    if ($isEnabled == 1 && self::isJobOverDue($executionDate)) {

                        $foundJobToExecute = true;

                        self::$logger->log('info', 'GENERAL: Starting job:'.self::logJob($scheduledJob));

                        $started_date = new \DateTime();

                        // now write started_date to original scheduled job:
                        foreach ($allScheduledJobs as $scheduledJob) {
                            if ($scheduledJob->name === $name) {
                                $scheduledJob->state        = JobState::RUNNING;
                                $scheduledJob->started_date = $started_date;
                                $scheduledJob->save();
                                break;
                            }
                        }


                        $myInstance = new $jobClass();

                        // get the context vars:
                        $vars = unserialize($serializedVars);


                        // setup (execute only when first run (NOT with restarted jobs)):
                        if ($restartCount == 0) {
                            self::$logger->log('info', 'Now setting up job:'.self::logJob($scheduledJob));

                            $myInstance->preExecute($vars, self::$logger);
                        }

                        // Because it could have been that the job added in preExecute more params, we have to reload the params:
                        // So walk through the queuedjobs jobs and find the job with the given name
                        $allAvailableJobs = ScheduledJob::all();
                        foreach ($allAvailableJobs as $availableJob) {
                            if ($availableJob->name === $name) {
                                $serializedVars    = $availableJob->serializedVars;
                                $vars = unserialize($serializedVars);
                            }
                        }

                        // Get the start time of the job runtime
                        $beforeOne = microtime(true);

                        // Run the function and save the return to $return - all the magic goes here
                        self::$logger->log('info', 'GENERAL: Now executing job:'.self::logJob($scheduledJob));

                        $return = $myInstance->execute($vars, $progress, self::$logger);

                        // Get the end time of the job runtime
                        $afterOne = microtime(true);

                        // clean up
                        self::$logger->log('info', 'GENERAL: Now cleaning up job:'.self::logJob($scheduledJob));
                        $myInstance->postExecute($vars, self::$logger);

                        // because the progress could/should have changed in between, we re-get it:
                        $finalProgress = $progress;
                        foreach (ScheduledJob::all() as $scheduledJobToTest) {
                            if ($scheduledJobToTest->name === $name) {
                                $finalProgress = $scheduledJobToTest->progress;
                                break;
                            }
                        }

                        // If the function returned not null then we assume that there was an error
                        if ($return !== null) {
                            // Add to error array
                            array_push($errorJobs, array('name' => $name, 'return' => $return, 'started_date' => $started_date, 'finished_date' => new \DateTime(), 'runtime' => ($afterOne - $beforeOne), 'final_progress' => $finalProgress));
                        }

                        // Push the information of the ran queuedjobs job to the allJobs array (including name, return value, runtime)
                        array_push($allJobs, array('name' => $name, 'return' => $return, 'started_date' => $started_date, 'finished_date' => new \DateTime(), 'runtime' => ($afterOne - $beforeOne), 'final_progress' => $finalProgress));

                        // finally remove the job from the queue.
                        self::remove($name);
                        self::$logger->log('info', 'GENERAL: Now removed job from queue:'.self::logJob($scheduledJob));

                        // after one job has been executed try to find the next one (because another manager could have started some jobs in the meantime):
                        break;

                    }
                }

            }
            if (!$foundJobToExecute) break;
        }


       //echo 'Done';

        // Get the end runtime for all the queuedjobs jobs
        $afterAll = microtime(true);

        // If database logging is enabled, save manager und jobs to db
        if (self::isDatabaseLogging()) {

            // Create a new cronmanager database object for this run and save it
            $cronmanager = new Manager();
            $cronmanager->rundate = $runDate;
            $cronmanager->runtime = $afterAll - $beforeAll;
            $cronmanager->save();

            $inTime = false;
            // Check if the run between this run and the last run is in good time (30 seconds tolerance) or not and log this event
            if ($timeBetween === -1) {
                self::$logger->log('warning', 'GENERAL: QueuedJobs run with manager id ' . $cronmanager->id . ' has no previous ran jobs.');
                $inTime = -1;
            } elseif (($runInterval * 60) - $timeBetween <= -30) {
                self::$logger->log('error', 'GENERAL: QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too late.');
                $inTime = false;
            } elseif (($runInterval * 60) - $timeBetween >= 30) {
                self::$logger->log('error', 'GENERAL: QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too fast.');
                $inTime = false;
            } else {
                self::$logger->log('info', 'GENERAL: QueuedJobs run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run in time.');
                $inTime = true;
            }

            if (self::isLogOnlyErrorJobsToDatabase()) {
                // Save error jobs only to database
                self::saveJobsFromArrayToDatabase($errorJobs, $cronmanager->id);
            } else {
                // Save all jobs to database
                self::saveJobsFromArrayToDatabase($allJobs, $cronmanager->id);
            }

            // Log the result of the queuedjobs run
            if (empty($errorJobs)) {
                self::$logger->log('info', 'GENERAL: The queuedjobs run with the manager id ' . $cronmanager->id . ' was finished without errors.');
            } else {
                self::$logger->log('error', 'GENERAL: The queuedjobs run with the manager id ' . $cronmanager->id . ' was finished with ' . count($errorJobs) . ' errors.');
            }

            // If database logging is disabled
        } else {
            // Log the status of the queuedjobs job run without the cronmanager id
            if (empty($errorJobs)) {
                self::$logger->log('info', 'GENERAL: QueuedJobs run was finished without errors.');
            } else {
                self::$logger->log('error', 'GENERAL: QueuedJobs run was finished with ' . count($errorJobs) . ' errors.');
            }
        }

        // Check for old database entires and delete them
        self::deleteOldDatabaseEntries();

        // Return the queuedjobs jobs array (including rundate, in time boolean, runtime, number of errors and an array with the queuedjobs jobs reports)
        return array('rundate' => $runDate->getTimestamp(), 'inTime' => $inTime, 'runtime' => ($afterAll - $beforeAll), 'errors' => count($errorJobs), 'crons' => $allJobs);
    }

    /**
     * Save the jobs progress in order to prevent it from being considered as stalled
     *
     * @param string $jobName the job's name
     * @param int $progress the integer value indicating the progress
     */
    public static function updateProgress($jobName, $progress) {

        self::initLoggerAndConfig();

        self::$logger->log('info', 'GENERAL: Updating progress for job:'.$jobName.': '.$progress);

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


    /**
     * Add a Monolog logger object and activate logging
     *
     * @static
     * @param  \Monolog\Logger $logger optional The Monolog logger object which will be used for queuedjobs logging - if this parameter is null the logger will be removed
     */
    public static function setLogger(\Monolog\Logger $logger = null) {
       $myCronLogger = new QueuedJobLogger();
       $myCronLogger->setLogger($logger);
       self::$logger = $myCronLogger;
    }

    /**
     * Get the Monolog logger object
     *
     * @static
     * @return  \Monolog\Logger Return the set logger object - return null if no logger is set
     */
    public static function getLogger() {
        return self::$logger;
    }



    /**
     * Enable or disable database logging - start value is true
     *
     * @static
     * @param  boolean $bool Set to enable or disable database logging
     * @return void|false Retun void if value was set successfully or false if there was an problem with the parameter
     */
    public static function setDatabaseLogging($bool) {
        if (is_bool($bool)) {
            \Config::set('queuedjobs::databaseLogging', $bool);
        } else {
            return false;
        }
    }

    /**
     * Is logging to database true or false
     * 
     * @return boolean Return boolean which indicates if database logging is true or false
     */
    public static function isDatabaseLogging() {
        $databaseLogging = \Config::get('queuedjobs::databaseLogging');
        if (is_bool($databaseLogging)) {
            return $databaseLogging;
        } else {
            return null;
        }
    }

    /**
     * Enable or disable logging error jobs to database only - start value is true
     * NOTE: Works only if database logging is enabled
     *
     * @static
     * @param  boolean $bool Set to enable or disable logging error jobs only
     * @return void|false Retun void if value was set successfully or false if there was an problem with the parameter
     */
    public static function setLogOnlyErrorJobsToDatabase($bool) {
        if (is_bool($bool)) {
            \Config::set('queuedjobs::logOnlyErrorJobsToDatabase', $bool);
        } else {
            return false;
        }
    }

    /**
     * Is logging jobs to database only true or false
     * 
     * @return boolean Return boolean which indicates if logging only error jobs to database is true or false
     */
    public static function isLogOnlyErrorJobsToDatabase() {
        $logOnlyErrorJobsToDatabase = \Config::get('queuedjobs::logOnlyErrorJobsToDatabase');
        if (is_bool($logOnlyErrorJobsToDatabase)) {
            return $logOnlyErrorJobsToDatabase;
        } else {
            return null;
        }
    }

    /**
     * Set the run interval - the run interval is the time between two queuedjobs job route calls
     *
     * @static
     * @param  int $minutes Set the interval in minutes
     * @return void|false Retun void if value was set successfully or false if there was an problem with the parameter
     */
    public static function setRunInterval($minutes) {
        if (is_int($minutes)) {
            \Config::set('queuedjobs::runInterval', $minutes);
        } else {
            return false;
        }
    }

    /**
     * Get the current run interval value
     * 
     * @return int|null Return the current interval value in minutes or null if there was no value set or the value type is not equals integer
     */
    public static function getRunInterval() {
        $interval = \Config::get('queuedjobs::runInterval');
        if (is_int($interval)) {
            return $interval;
        } else {
            return null;
        }
    }

    /**
     * Set the delete time of old database entries in hours 
     *
     * @static
     * @param  int $hours Set the delete time in hours
     * @return void|false Return void if value was set successfully or false if there was an problem with the parameter
     */
    public static function setDeleteDatabaseEntriesAfter($hours = 0) {
        if (is_int($hours)) {
            \Config::set('queuedjobs::deleteDatabaseEntriesAfter', $hours);
        } else {
            return false;
        }
    }

    /**
     * Get the current delete time value in hours for old database entries
     * 
     * @return int|null Return the current delete time value in hours or null if there was no value set or the value type is not equals integer
     */
    public static function getDeleteDatabaseEntriesAfter() {
        $deleteDatabaseEntriesAfter = \Config::get('queuedjobs::deleteDatabaseEntriesAfter');
        if (is_int($deleteDatabaseEntriesAfter)) {
            return $deleteDatabaseEntriesAfter;
        } else {
            return null;
        }
    }

    /**
     * Enable a job by job name
     *
     * @static
     * @param  String $jobname The name of the job which should be enabled
     * @param  boolean $enable The trigger for enable (true) or disable (false) the job with the given name
     * @return void|false Retun void if job was enabled successfully or false if there was an problem with the parameters
     */
    public static function setEnableJob($jobname, $enable = true) {
        self::initLoggerAndConfig();

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

    /**
     * Disable a job by job name
     *
     * @static
     * @param  String $jobname The name of the job which should be disabled
     * @return void|false Retun void if job was disabled successfully or false if there was an problem with the parameters
     */
    public static function setDisableJob($jobname) {
        return self::setEnableJob($jobname, false);
    }

    /**
     * Is the given job by name enabled or disabled
     *
     * @static
     * @param  String $jobname The name of the job which should be checked
     * @return void|false Retun boolean if job was enabled (true) or disabled (false) or null if no job with the given name is found
     */
    public static function isJobEnabled($jobname) {
        self::initLoggerAndConfig();


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
     * Delete old manager and job entries
     *
     * @static
     * @return void|false Retun false if the database was not cleaned successfully or void if the database is cleaned of old enrties
     */
    private static function deleteOldDatabaseEntries() {

        // Get the delete after hours value
        $deleteDatabaseEntriesAfter = self::getDeleteDatabaseEntriesAfter();
        // If the value is not set or equals 0 delete old database entries is disabled
        if (!empty($deleteDatabaseEntriesAfter)) {

            self::$logger->log('info', 'GENERAL: Deleting old db entries ... ');

            // Get the current time and subtract the hour values
            $now = new \DateTime();
            date_sub($now, date_interval_create_from_date_string($deleteDatabaseEntriesAfter . ' hours'));

            // Get the old manager entries which are expired
            $oldManagers = Manager::where('rundate', '<=', $now->format('Y-m-d H:i:s'))->get();

            foreach ($oldManagers as $manager) {

                // Get the old job entries from thee expired manager
                $oldJobs = $manager->cronJobs()->get();

                foreach ($oldJobs as $job) {
                    // Delete old job
                    $job->delete();
                }

                // After running through the manager jobs - delete the manager entry
                $manager->delete();
            }
            // Database was cleaned successfully
            return null;
        }
        // Database clean was skipped
        return false;
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

        self::$logger->log('info', 'GENERAL: Job is overdue: '.$currentTime->format('Y-m-d H:i:s').' runDate= '.$dateTime->format('Y-m-d H:i:s').' => '.$result);

        return $result;
    }


    private static function logJob ($scheduledJob) {

        if ($scheduledJob instanceof ScheduledJob) {

            $result = ' name => '.$scheduledJob->name.
                ' enabled => '.$scheduledJob->enabled.
                ' state => '.$scheduledJob->state.
                ' restart_count => '.$scheduledJob->restart_count.
                ' job_class => '.$scheduledJob->jobclass.
                ' serialized_vars => '.$scheduledJob->serializedVars.' started_date => ';

            $startedDateStr = 'NULL';
            if ($scheduledJob->started_date != null) {
                if ($scheduledJob->started_date instanceof DateTime) {
                    $startedDateStr = $scheduledJob->started_date->format('Y-m-d H:i:s');
                }
                else {
                    $startedDateStr = $scheduledJob->started_date;
                }
            }
            $result = $result.$startedDateStr;

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
            $result = $result.$execDateStr;

            $result = $result.' progress => '.$scheduledJob->progress.' last_progress => '.$scheduledJob->last_progress;

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

            return $result;

        }
        else {
            return serialize($scheduledJob);

        }
    }

    private static function setDefaultConfigValues() {
        \Config::set('queuedjobs::runInterval', 1);
        \Config::set('queuedjobs::databaseLogging', true);
        \Config::set('queuedjobs::logOnlyErrorJobsToDatabase', false);
        \Config::set('queuedjobs::deleteDatabaseEntriesAfter', 240);
    }

    private static function initLoggerAndConfig () {
        QueuedJobEngine::setDefaultConfigValues();

        if (self::$logger == NULL) {
            $logger = new \Monolog\Logger('job-logger');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(self::getPathToLogfile(), \Monolog\Logger::DEBUG));
            self::setLogger($logger);
        }
    }

    /**
     *  Get the global path to log file
     */
    private static function getPathToLogfile () {
        return app_path().'/storage/logs/job-logger.txt';
    }

}