<?php

namespace App;

class Logger
{
    private $filename = null;

    private $publisher = null;

    public function __construct()
    {
        $this->publisher = new Publisher(
            env('RABBITMQ_LOGGER_HOST'),
            env('RABBITMQ_LOGGER_PORT'),
            env('RABBITMQ_LOGGER_USERNAME'),
            env('RABBITMQ_LOGGER_PASSWORD'),
            env('RABBITMQ_LOGGER_VHOST')
        );

        $this->publisher->setLog(false);
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
        $hasDir = env('RABBITMQ_LOGGER_DIR') != null;
        $hasExchange = env('RABBITMQ_LOGGER_EXCHANGE') != null;
        $hasRoutingKey = env('RABBITMQ_LOGGER_ROUTING_KEY') != null;

        if (!$hasDir || !$hasExchange || !$hasRoutingKey) {
            return false;
        }

        $headers = [
            'dir' => env('RABBITMQ_LOGGER_DIR'),
            'filename' => $this->filename(),
            'level' => $level,
            'message' => $message,
        ];

        $this->publisher->publish(
            env('RABBITMQ_LOGGER_EXCHANGE'),
            env('RABBITMQ_LOGGER_ROUTING_KEY'),
            json_encode($payload),
            $headers
        );

        return true;
    }
}
