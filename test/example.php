<?php

/*
 * http://localhost/evo/Handler/test/example.php
 *
 */
require __DIR__.'/../vendor/autoload.php';

\evo\debug\Debug::regesterFunctions();

$H = \evo\handler\ErrorHandler::I();


echo "<pre>";

print_r($H->getErrorCallbacks());

//catch an uncaught error
#throw new Exception('Test Exception');

//convert error to exception
#trigger_error('Test Error', E_USER_ERROR);

/* Shutdown test */
set_time_limit(1);
sleep(2);
