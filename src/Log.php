<?php

namespace App;

use Exception;

class Log
{
    private $filename = null;

    private $publisher = null;

    public function __construct()
    {
        if (!$this->isConfigured()) {
            throw new Exception('Cannot publish log, logger not configured');
        }

        $this->publisher = new Publisher(
            env('RABBITMQ_LOGGER_HOST'),
            env('RABBITMQ_LOGGER_PORT'),
            env('RABBITMQ_LOGGER_USER'),
            env('RABBITMQ_LOGGER_PASSWORD'),
            env('RABBITMQ_LOGGER_VHOST')
        );

        $this->publisher->setLog(false);
    }

    public function isConfigured()
    {
        $hasHost = env('RABBITMQ_LOGGER_HOST') != null;
        $hasPort = env('RABBITMQ_LOGGER_PORT') != null;
        $hasUser = env('RABBITMQ_LOGGER_USER') != null;
        $hasPassword = env('RABBITMQ_LOGGER_PASSWORD') != null;
        $hasVhost = env('RABBITMQ_LOGGER_VHOST') != null;

        return $hasHost && $hasHost && $hasUser && $hasPassword && $hasVhost;
    }

    private function filename()
    {
        if (!$this->filename) {
            $filename = preg_replace('/\s+/', '-', env('APP_NAME'));

            $this->filename = strtolower($filename);
        }

        return $filename;
    }

    public function publish($level, $message, array $payload = [])
    {
        $headers = [
            'dir' => env('RABBITMQ_LOGGER_DIR', ''),
            'filename' => $this->filename(),
            'level' => $level,
            'message' => $message,
        ];

        $this->publisher->publish(
            env('RABBITMQ_LOGGER_EXCHANGE', ''),
            env('RABBITMQ_LOGGER_ROUTING_KEY', ''),
            json_encode($payload),
            $headers
        );
    }
}
