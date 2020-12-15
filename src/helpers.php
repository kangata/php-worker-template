<?php

use App\Log;

function logger($message)
{
    $format = "[%s] %s\n";

    $log = sprintf($format, date('Y-m-d H:i:s'), $message);

    return $log;
}

function showLog($message, array $data = [], $publish = false)
{
    echo logger($message);

    if (!empty($data)) {
        echo logger('DATA: ' . json_encode($data));
    }

    if ($publish) {
        $level = preg_match('/^ERROR:/', $message) ? 'error' : 'info';

        $publishMessage = preg_replace('/(^ERROR:\s)|(^INFO:\s)/', '', $message);

        try {
            (new Log)->publish($level, $publishMessage, $data);
        } catch (\Exception $e) {
            logError($e->getMessage());
        }
    }
}

function logInfo($message, array $data = [], $publish = false)
{
    showLog('INFO: ' . $message, $data, $publish);
}

function logError($message, array $data = [], $publish = false)
{
    showLog('ERROR: ' . $message, $data, $publish);
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
    $format = "%s %s:%d";

    $message = sprintf($format, $errstr, $errfile, $errline);

    logError($message, $data = [], $publish = true);

    die;
}

function fatalHandler()
{
    logError('Fatal error');
}
