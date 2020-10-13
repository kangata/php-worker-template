<?php

namespace App;

use Exception;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;

class Worker
{
    private $connection = null;

    private $connectionName = null;

    private $channel = null;

    private $env = null;

    private $name = null;

    private $validator = null;

    private function __construct()
    {
        $this->name = env('APP_NAME');
        $this->env = strtoupper(env('APP_ENV'));
        $this->connectionName = "{$this->env} # {$this->name}";

        $this->connect();

        $this->validator = new Validator;
    }

    private function config()
    {
        $config = [
            'host' => env('RABBITMQ_HOST'),
            'port' => env('RABBITMQ_PORT'),
            'user' => env('RABBITMQ_USERNAME'),
            'password' => env('RABBITMQ_PASSWORD'),
            'vhost' => env('RABBITMQ_VHOST')
        ];

        return $config;
    }

    private function connect()
    {
        try {
            $hosts = [$this->config()];

            AMQPLazyConnection::$LIBRARY_PROPERTIES['connection_name'] = ['S', $this->connectionName];

            $this->connection = AMQPLazyConnection::create_connection($hosts);

            logInfo("Start {$this->name} [{$this->env}]", $data = [], $publish = true);
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $this->channel = $this->connection->channel();

            logInfo('Connected to channel', $data = [], $publish = true);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function connection()
    {
        return $this->connection;
    }

    public function channel()
    {
        return $this->channel;
    }

    public static function start()
    {
        try {
            $self = new static;

            $self->channel()->exchange_declare(
                env('RABBITMQ_EXCHANGE'),
                AMQPExchangeType::DIRECT,
                $passive = false,
                $durable = true,
                $autoDelete = false
            );

            $self->channel()->queue_bind(
                env('RABBITMQ_QUEUE'),
                env('RABBITMQ_EXCHANGE'),
                env('RABBITMQ_ROUTING_KEY')
            );

            $self->channel()->basic_consume(
                env('RABBITMQ_QUEUE'),
                $consumerTag = '',
                $noLocal = false,
                $noAck = env('RABBITMQ_NOACK', false),
                $exclusive = false,
                $nowait = false,
                $callback = function ($message) use ($self) {
                    try {
                        $self->handleMessage($message);
                    } catch (Exception $e) {
                        $self->handleMessageError($message, $e);
                    }
                }
            );

            while ($self->channel()->is_consuming()) {
                $self->channel()->wait();
            }
    
            $this->channel()->close();
            $this->connection()->close();
        } catch (Exception $e) {
            logError($e->getMessage(), $data = [], $publish = true);
        }
    }

    private function handleMessage($message)
    {
        $payloadJson = $message->body;
        $payload = json_decode($payloadJson, true);
        $properties = new AMQPTable($message->get_properties());
        $data = $properties->getNativeData();
        $headers = isset($data['application_headers'])
            ? $data['application_headers']
            : [];
        $headersJson = json_encode($headers);

        logInfo(
            'Received message',
            $data = $payload ?? ['raw' => $payloadJson],
            $publish = true
        );

        try {
            $this->validatePayload($payload);
        } catch (Exception $e) {
            throw $e;
        }

        if (!empty($headers)) {
            logInfo('Received headers', $data = $headers, $publish = true);
        }

        try {
            $this->validateHeaders($headers);
        } catch (Exception $e) {
            throw $e;
        }

        (new \App\Handlers\MessageHandler)($message);
    }

    private function handleMessageError($message, $error)
    {
        logError($error->getMessage(), $data = [], $publish = true);

        (new \App\Handlers\MessageErrorHandler)($message);
    }

    private function validatePayload($payload)
    {
        if (is_array($payload)) {
            $validator = $this->validator->make($payload, ValidationRule::payload());

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first().' (PAYLOAD)');
            }
        }
    }

    private function validateHeaders($headers)
    {
        if (is_array($headers)) {
            $validator = $this->validator->make($headers, ValidationRule::header());

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first().' (HEADERS)');
            }
        }
    }
}
