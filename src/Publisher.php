<?php

namespace App;

use Exception;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Publisher
{
    private $channel;

    private $config;

    private $connection;

    private $showLog = true;

    private $options;

    public function __construct($host, $port, $username, $password, $vhost)
    {
        $this->config = [
            'host' => $host,
            'port' => $port,
            'user' => $username,
            'password' => $password,
            'vhost' => $vhost,
        ];
    }

    private function name()
    {
        $appName = env('APP_NAME');
        $appEnv = strtoupper(env('APP_ENV', 'local'));

        $name = "{$appEnv} # {$appName}";

        return $name;
    }

    private function connect()
    {
        try {
            $hosts = [$this->config];

            AMQPLazyConnection::$LIBRARY_PROPERTIES['connection_name'] = ['S', $this->name()];

            $this->connection = AMQPLazyConnection::create_connection($hosts);

            if ($this->showLog) {
                logInfo('Publisher connected to server');
            }
        } catch (Exception $e) {
            logError($e->getMessage());

            die;
        }

        try {
            $this->channel = $this->connection->channel();

            if ($this->showLog) {
                logInfo('Publisher connected to channel');
            }
        } catch (Exception $e) {
            logError($e->getMessage());

            die;
        }
    }

    public function setLog(bool $val)
    {
        $this->showLog = $val;
    }

    public function publish(String $exchange, String $routingKey, String $payload, Array $headers = [])
    {
        $this->connect();

        $this->channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

        $message = new AMQPMessage($payload);
        $messageHeaders = new AMQPTable($headers);

        $message->set('application_headers', $messageHeaders);

        $this->channel->basic_publish($message, $exchange, $routingKey);

        if ($this->showLog) {
            showLog('PUBLISH: '.$exchange.' --> '.$routingKey);
    
            logInfo('Publish payload');
            showLog('PAYLOAD: '.$payload);
    
            logInfo('Publish headers');
            showLog('HEADERS: '.json_encode($headers));
        }

        $this->channel->close();
        $this->connection->close();

        if ($this->showLog) {
            logInfo('Publisher channel closed');
            logInfo('Publisher connection closed');
        }
    }
}
