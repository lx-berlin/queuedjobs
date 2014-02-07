<?php

return array(
    
    // Interval defines the time in minutes between two run method calls - in other words, the time between the queuedjobs job route will be called
    'runInterval' => 1,
    
    // Enable or disable database(!) logging
    'databaseLogging' => true,
    
    // Enable or disable logging error jobs only
    'logOnlyErrorJobsToDatabase' => false,
    
    // Delte old database entries after how many hours
    'deleteDatabaseEntriesAfter' => 240,

    // do we want to log into a file?
    'fileLogging' => true,

    // the path to where the log file is written:
    'pathToLogfile' => storage_path().'/logs/job-logger.txt',

    // if you want to receive email notifications (e. g. in case of a stalled job)
    // ATTENTION: please make sure you have set up the configs in mail.php and queue.php before
    'emailNotifications' => true,

    // where do you want the emails to be sent to?
    'emailNotificationsReceiverAddress' => 'g.matz@gmx.de'
    
);