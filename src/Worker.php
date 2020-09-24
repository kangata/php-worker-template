<?php

namespace App;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Worker
{
    private $connection;

    private $channel;

    private $validator;

    public function __construct()
    {
        $this->connect();

        $this->validator = new Validator;
    }

    private function connect()
    {
        try {
            $this->connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST'),
                env('RABBITMQ_PORT'),
                env('RABBITMQ_USERNAME'),
                env('RABBITMQ_PASSWORD'),
                env('RABBITMQ_VHOST')
            );

            logInfo('Worker connected to server');
        } catch (Exception $e) {
            logError($e->getMessage());
        }

        try {
            $this->channel = $this->connection->channel();

            logInfo('Worker connected to channel');
        } catch (Exception $e) {
            logError($e->getMessage());
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
        $self = new static;

        try {
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
                $customerTag = '',
                $noLocal = false,
                $noAck = true,
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
        } catch (Exception $e) {
            logError($e->getMessage());
        }

        while ($self->channel()->is_consuming()) {
            $self->channel()->wait();
        }

        $this->channel()->close();
        $this->connection()->close();
    }

    private function handleMessage($message)
    {
        $payloadJson = $message->body;
        $payload = json_decode($payloadJson, true);
        $properties = new AMQPTable($message->get_properties());
        $data = $properties->getNativeData();
        $headers = isset($data['application_headers'])
            ? $data['application_headers']
            : null;

        $headersJson = json_encode($headers);

        logInfo('Received payload');
        showLog('PAYLOAD: '.$payloadJson);

        $this->validatePayload($payload);

        logInfo('Received headers');
        showLog('HEADERS: '.$headersJson);

        $this->validateHeaders($headers);

        (new \App\Handlers\MessageHandler)($message);
    }

    private function handleMessageError($message, $error)
    {
        showLog('ERROR: '.$error->getMessage());

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
