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

	private $connectionOptions = [
		'heartbeat' => 0,
	];

    private $channel = null;

    private $env = null;

    private $name = null;

    private $validator = null;

    private function __construct()
    {
        $this->name = env('APP_NAME');
        $this->env = strtoupper(env('APP_ENV'));
        $this->connectionName = "{$this->name} # {$this->env}";

        $this->connect();

        $this->validator = new Validator;
    }

    private function config($key = null)
    {
        $config = [
            'host' => env('RABBITMQ_HOST'),
            'port' => env('RABBITMQ_PORT'),
            'user' => env('RABBITMQ_USERNAME'),
            'password' => env('RABBITMQ_PASSWORD'),
            'vhost' => env('RABBITMQ_VHOST')
        ];

        if ($key != null && isset($config[$key])) {
            return $config[$key];
        }

        return $config;
    }

    private function url()
    {
        $host = $this->config('host');
        $port = $this->config('port');
        $vhost = $this->config('vhost');

        $url = "{$host}:{$port}/$vhost";
        $url = preg_replace("/\/\//", "/", $url);

        return $url;
    }

    private function connect()
    {
        try {
            $hosts = [$this->config()];

            AMQPLazyConnection::$LIBRARY_PROPERTIES['connection_name'] = ['S', $this->connectionName];

            $this->connection = AMQPLazyConnection::create_connection($hosts, $this->connectionOptions);

            logInfo("`{$this->name}` connected to {$this->url()}", $data = [], $publish = true);
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
        $name = env('APP_NAME');
        $env = strtoupper(env('APP_ENV'));

        logInfo("Start `{$name}` # {$env}", $data = [], $publish = true);

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

            logInfo(
                "Queue binded for `{$name}` # {$env}",
                $data = [
                    'exchange' => env('RABBITMQ_EXCHANGE'),
                    'queue' => env('RABBITMQ_QUEUE'),
                    'routing_key' => env('RABBITMQ_ROUTING_KEY'),
                ],
                $publish = true
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

        try {
            (new \App\Handlers\MessageHandler)($message);
        } catch (Exception $e) {
            throw $e;
        }
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
