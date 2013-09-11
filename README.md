# ![alt text] Queued Jobs ![project status]
Job scheduling for Laravel

QueuedJobs can be used for easily and conveniently performing queued jobs in Laravel without using Artisan commands.
You can define jobs which are queued for later execution.
This is possible be saving the created queued jobs including their schedule information to a database table.
You can also use parameters that are added at creation time and are available for your convenience when executing the job.
Furthermore you can use a Monolog logger instance.


- [Overview](#overview)
- [Installation](#installation)
- [Usage](#usage)
- [|--Add a job](#addjob)
- [|--Remove a job](#removejob)
- [|--Enable / disable a job](#enabledisable)
- [|--Run the jobs](#runjob)
- [|--Set a Monolog logger](#setlogger)
- [|--Disable database logging](#disabledatabaselogging)
- [|--Log only error jobs to database](#logonlyerrorjobstodatabase)
- [|--Delete old database entries](#deleteolddatabaseentries)
- [|--Reset](#reset)
- [|--Changing default values](#defaultvalues)
- [Full example](#fullexample)

---

<a name="overview"></a>
## Overview

You have to
*   ... download this package
*   ... write your code with all job definitions, add the jobs and call `run()` somewhen later
*   ... buy or rent a server or service which call the defined route every predefined number of minutes (default is every minute) as normal a web request (e.g. with wget)

You don't have to
*   ... create Artisan commands
*   ... own shell access to your server
*   ... run the regular  route requests on the same machine where your Laravel site is located
*   ... worry about job management anymore

**NOTE**:
If you have any trouble, questions or suggestions just open an issue. It would be nice to hear from you.

This documentation will be updated in the near future.

---

<a name="installation"></a>
## Installation

1.  Add `"lx-berlin/queuedjobs": "dev-master"` to your `/laravel/composer.json` file at the `"require":` section (Find more about composer at http://getcomposer.org/)
2.  Run the `composer update --no-dev` command in your shell from your `/laravel/` directory 
3.  Add `'lxberlin\QueuedJobs\QueuedJobsServiceProvider'` to your `'providers'` array in the `app\config\app.php` file
4.  Migrate the database with running the command `php artisan migrate --package="lxberlin/QueuedJobs"`
5.  Now you can use `\lxberlin\QueuedJobs\QueuedJobEngine` everywhere for free

---

<a name="usage"></a>
## Usage

<a name="addjob"></a>
### Add a queued job

Adding a  job is very easy by using a class that is implementing the QueuedJobsJobExecutable interface function.
As parameter the **name** of the job, the **execution date & time** and a **job class** is needed.
The **additionalExecParams** and **isEnabled** are optional.

```
public static function add($name, $dateTime, $jobClass, $additionalExecParams = null, $isEnabled = true, $progress = -1, $restartCount = 0) {
```

The **name** is needed for identifying a job if an error appears and for logging.

The given class defines the job and has four methods that have to be implemented.
These methode maintain the job lifecycle and will be invoked if the expression details match with the current timestamp.
The class's execute function should return null in the case of success or anything else if there was an error while executing this job.
By default, completed job will also be logged to the database and to a Monolog logger object (if logger is enabled).

The **isEnabled** boolean parameter makes it possible to deactivate a job from execution without removing it completely.
Later the job execution can be enabled very easily by giving a true boolean to the method. This parameter is optional and the default value is enabled.

#### Example

```
$params = array('param1' => '333', 'param2' => 22, 'param3' => true);
QueuedJobEngine::add(TestJob1::$name, new \DateTime('2013-09-09 15:10:00'), 'lxberlin\QueuedJobs\tests\TestJob1', $params );
```

---

<a name="removejob"></a>
### Remove a queued job

To remove a set job on runtime use the **remove** method with the  job name as string parameter.

```
public static function remove($name) {
```

#### Example

```
$report = QueuedJobEngine::remove('Every minute');
```

----

#### Interface

Each queued job must implement te QueuedJobExecutable interface.
Please have a look at the DemoQueuedJob file in order to acquaint yourself with the interface methods:

function setup($additionalExecParams, $logger);

function execute($additionalExecParams, $lastProgress, $logger);

function cleanUp($additionalExecParams, $logger);


<a name="runjob"></a>
### Run the queued jobs

Running the jobs is as easy as adding them.
Just call the static **run** method and wait until each added job is checked.
As soon as the time has come, the corresponding  job will be invoked.
The **run** method returns a detailed report.
By default we reckon that you call this method.

```
public static function run() {
```

#### Example

```
$report = QueuedJobEngine::run();
```


---

<a name="setlogger"></a>
### Set a Monolog logger

If logging should be activated just add a Monolog logger object to the static **setLogger** method. Only Monolog is supported at the moment.

```
public static function setLogger(\Monolog\Logger $logger = null) {
```

**NOTE**: If you want to remove the logger, just call the **setLogger** method without parameters.

#### Example

```
QueuedJobEngine::setLogger(new \Monolog\Logger('job-Logger'));
```

---

<a name="fullexample"></a>
## Full example

At first we create a route which should be called in a defined interval.
Then we have to call add within this route's function.

**NOTE**: We have to protect this route because if someone calls this uncontrolled, our cron management doesn't work. A possibility is to set the route path to a long value. Another good alternative is (if you know the IP address of the calling server) to check if the IP address matchs.


```
Route::get('/QueuedJobs/run/c68pd2s4e363221a3064e8807da20s1sf', function () {

  QueuedJobEngine::setDefaultConfigValues();

  // this has to be done every time:
  $pathToLogfile = 'job-logger.txt';
  $logger = new \Monolog\Logger('job-logger');
  $logger->pushHandler(new \Monolog\Handler\StreamHandler($pathToLogfile, \Monolog\Logger::DEBUG));
  QueuedJobEngine::setLogger($logger);


  // create job:
  $params = array('param1' => '333', 'param2' => 22, 'param3' => true);
  QueuedJobEngine::add(TestJob1::$name, new \DateTime('2013-09-09 15:10:00'), 'lxberlin\QueuedJobs\tests\TestJob1', $params );



});

```

----

Then somewhen later on we can call the run method (which has also been defined as its own route):

```
Route::get('/test/queuedjobs/execJob', function () {

    QueuedJobEngine::setDefaultConfigValues();

    // this has to be done every time:
    $pathToLogfile = 'job-logger.txt';
    $logger = new \Monolog\Logger('job-logger');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler($pathToLogfile, \Monolog\Logger::DEBUG));
    QueuedJobEngine::setLogger($logger);


    $report = QueuedJobEngine::run();

    print_r ($report);
});

```

And that is the  magic. Now we have to ensure that this route is called in an interval.
This can be done with renting an own (virtual) server or with an online service.

To configure a wget web request by using `crontab -e` or a control panel software (e.g. cPanel or Plesk) on an own (virtual) server use the following code:

```
* * * * * wget -O - http://yoursite.com/cron/run/c68pd2s4e363221a3064e8807da20s1sf >/dev/null 2>&1
```

The starting five asterisks are the cron expressions. We want to start our Cron management every minute in this example. The tool `wget` retrieves files using HTTP, HTTPS and FTP. Using the parameter `-O -` causes that the output of the web request will be sent to STDOUT (standard output). By adding `>/dev/null` we instruct standard output to be redirect to a black hole (/dev/null). By adding `2>&1` we instruct STDERR (standard errors) to also be sent to STDOUT (in this example this is /dev/null). So it will load our website at the Cron route every minute, but never write a file anywhere.

---

## License

The MIT License (MIT)

Copyright (c) 2013 Marc Liebig, Gunnar Matz, lx-berlin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

Icon Copyright (c) Timothy Miller (http://www.iconfinder.com/icondetails/171279/48/alarm_bell_clock_time_icon) under Creative Commons (Attribution-Share Alike 3.0 Unported) License - Thank you for this awesome icon