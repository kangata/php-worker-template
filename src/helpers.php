<?php

function logger($message)
{
    $format = "[%s] %s\n";

    return sprintf($format, date('Y-m-d H:i:s'), $message);
}

function showLog($message)
{
  echo logger($message);
}

function logInfo($message)
{
  showLog('INFO: ' . $message);
}

function logError($message)
{
  showLog('ERROR: ' . $message);
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
    $format = "%s %s:%d";

    $message = sprintf($format, $errstr, $errfile, $errline);

    logError($message);

    die;
}

