<?php
/**
 * QueuedJobs - Job scheduling for Laravel
 *
 * @author      Gunnar Matz & lx-berlin (originally based on Marc Liebig's QueuedJobs Job code)
 * @copyright   2013 Gunnar Matz & lx-berlin (originally based on Marc Liebig's QueuedJobs Job code)
 * @link        https://github.com/lx-berlin/queuedjobs/
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     QueuedJobs
 *
 * Please find more copyright information in the LICENSE file
 */
namespace lxberlin\QueuedJobs;


interface QueuedJobExecutable {

    /**
     * This method should be used to get the unique name of the job
     * @param $additionalExecParams the context variables
     * @param $logger a logger
     * @return String unique name
     */
    function getUniqueName($additionalExecParams, $logger);

    /**
     * This method should be used to prepare the execution of the job and do anything which is necessary *before* execution of the job
     * don't forget to log your progress
     * @param $additionalExecParams the context variables
     * @param $logger a logger
     * @return nothing
     */
    function preExecute($additionalExecParams, $logger);

    /**
     * This method should be used to execute the job - it contains the job's code
     * don't forget to log your progress
     * and definitely take care of the lastProgress parameter!
     *
     * @param $additionalExecParams the context variables
     * @param $lastProgress -1 if this job is executed for the first time; >=0 if this job is executed for another time (because it has been detected as stalled and now gets called again)
     * @param $logger a logger: if you log to logger please use a unique prefix like the name of your job to allow for later distinguishing between log lines of different jobs in one log file
     * @return null, if everyhting is ok, otherwise return the error (this conforms to Liebig's job description)
     */
    function execute($additionalExecParams, $lastProgress, $logger);

    /**
     * This method should be used to tearDown, clean up etc. *after* the execution of the job
     * don't forget to log your progress
     * @param $additionalExecParams the context variables
     * @param $logger a logger
     * @return nothing
     */
    function postExecute($additionalExecParams, $logger);
}

