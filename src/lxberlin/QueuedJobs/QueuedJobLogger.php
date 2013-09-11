<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gunnarmatz
 * Date: 10.09.13
 * Time: 16:36
 * To change this template use File | Settings | File Templates.
 */

namespace lxberlin\QueuedJobs;

class QueuedJobLogger {

    /**
     * @static
     * @var \Monolog\Logger Logger object if logging is requested or null if nothing should be logged.
     */
    private $logger;


    /**
     * Add a Monolog logger object and activate logging
     *
     * @static
     * @param  \Monolog\Logger $logger optional The Monolog logger object which will be used for queuedjobs logging - if this parameter is null the logger will be removed
     */
    public function setLogger(\Monolog\Logger $logger = null) {
        $this->logger = $logger;
    }

    /**
     * Get the Monolog logger object
     *
     * @static
     * @return  \Monolog\Logger Return the set logger object - return null if no logger is set
     */
    public function getLogger() {
        return $this->logger;
    }


    /**
     * Log a message with the given level to the Monolog logger object if one is set
     *
     * @static
     * @param  string $level The logger level as string which can be debug, info, notice, warning, error, critival, alert, emergency
     * @param  string $message The message which will be logged to Monolog
     * @return void|false Retun false if there was an error or void if logging is enabled and the message was given to the Monolog logger object
     */
     public function log($level, $message) {

        // If no Monolog logger object is set just return false
        if (!empty($this->logger)) {
            // Switch the lower case level string and log the message with the given level
            switch (strtolower($level)) {
                case "debug":
                    $this->logger->addDebug($message);
                    break;
                case "info":
                    $this->logger->addInfo($message);
                    break;
                case "notice":
                    $this->logger->addNotice($message);
                    break;
                case "warning":
                    $this->logger->addWarning($message);
                    break;
                case "error":
                    $this->logger->addError($message);
                    break;
                case "critical":
                    $this->logger->addCritical($message);
                    break;
                case "alert":
                    $this->logger->addAlert($message);
                    break;
                case "emergency":
                    $this->logger->addEmergency($message);
                    break;
                default:
                    return false;
            }
        } else {
            return false;
        }
    }
}