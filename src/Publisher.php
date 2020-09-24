<?php

namespace App;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Publisher
{
    private $channel;

    private $connection;

    private $options;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            $this->connection = new AMQPStreamConnection($host = 'localhost', $port = 5672, $username = 'guest', $password = 'guest',$vhost = '/');

            logInfo('Publisher connected to server');
        } catch (Exception $e) {
            logError($e->getMessage());

            die;
        }

        try {
            $this->channel = $this->connection->channel();

            logInfo('Publisher connected to channel');
        } catch (Exception $e) {
            logError($e->getMessage());

            die;
        }
    }

    public function publish(String $exchange, String $routingKey, String $payload, Array $headers = [])
    {
        $this->channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

        $message = new AMQPMessage($payload);
        $messageHeaders = new AMQPTable($headers);

        $message->set('application_headers', $messageHeaders);

        $this->channel->basic_publish($message, $exchange, $routingKey);

        showLog('PUBLISH: '.$exchange.' --> '.$routingKey);

        logInfo('Publish payload');
        showLog('PAYLOAD: '.$payload);

        logInfo('Publish headers');
        showLog('HEADERS: '.json_encode($headers));

        $this->channel->close();
        $this->connection->close();
    }
}
