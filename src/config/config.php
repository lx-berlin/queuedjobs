<?php

return array(
    
    // Interval defines the time in minutes between two run method calls - in other words, the time between the queuedjobs job route will be called
    'runInterval' => 1,

    // do we want to log to standard logger?
    'stdLogging' => true,

    // if you want to receive email notifications (e. g. in case of a stalled job)
    // ATTENTION: please make sure you have set up the configs in mail.php and queue.php before
    'emailNotifications' => false,

    // where do you want the emails to be sent to?
    'emailNotificationsReceiverAddress' => 'example@example.com'
    
);