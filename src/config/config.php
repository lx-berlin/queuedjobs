<?php

return array(
    
    // Interval defines the time in minutes between two run method calls - in other words, the time between the queuedjobs job route will be called
    'runInterval' => 1,
    
    // Enable or disable database logging
    'databaseLogging' => true,
    
    // Enable or disable logging error jobs only
    'logOnlyErrorJobsToDatabase' => false,
    
    // Delte old database entries after how many hours
    'deleteDatabaseEntriesAfter' => 240,
    
);