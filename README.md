# TASoft Process
This library controls process management using the PHP extension ```pcntl```.

#### Installation
```bin
$ composer require tasoft/process
```

##### Simple Usage

```php
<?php
use TASoft\Util\Process;

$process = new Process(function() {
    // Do stuff
});

// Now it will fork the process and call the callback function in separate process.
$process->run();

// Do other stuff in main process

// Wait until the child process has done
$process->wait();
// or kill the child process immediately.
$process->kill();
```

##### Problem
The main problem is that a forked process runs in an exact copy of the environment.  
That means, on calling ```$process->run()``` the child process gets a copy of the current environment.

This raises two problems:
1.  Everything you change (properties, $variables, etc) in the child process, remains there.
1.  Everything you change in the parent process, remains there also.

##### Solution
I use the Pipe class. It creates a unix socket pair to communicate between the two processes.

##### Example With Pipe
```php
<?php
use TASoft\Util\Process;

$process = new Process(function(Process $childProcess) {
    $result = NULL;
    
    // Do hard stuff
    
    // Please don't import the parent process!
    $childProcess->sendData( $result );
});

$process->run();

// Do other stuff

$data = $process->receiveData();
echo $data;

$process->wait(); // or kill
```
Don't import the parent process!
```php
<?php
use TASoft\Util\Process;

$process = new Process(function() use (&$process) {
    $result = NULL;
    // Do stuff
    $process->sendData( $result );
    
    // This is wrong and will not work because $process IS A COPY OF THE MAIN PROCESS!
});

...
```