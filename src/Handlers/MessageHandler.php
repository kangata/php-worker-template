<?php

namespace App\Handlers;

class MessageHandler
{
    private $message = null;

    public function __construct()
    {
        //
    }

    /**
     * Remove from queue
     *
     * @return void
     */
    private function ack()
    {
        $this->message->delivery_info['channel']->basic_ack($this->message->delivery_info['delivery_tag']);
    }

    /**
     * Return to queue
     *
     * @return void
     */
    private function nack()
    {
        $payload = json_decode($this->message->body, true);

        $delay = env('REQUEUE_DELAY', 30);

        logInfo(
            'Requeue message with delay '. $delay .' seconds',
            $payload ?? ['raw' => $this->message->body],
            $publish = true
        );

        sleep($delay);

        $this->message->delivery_info['channel']->basic_nack(
            $tag = $this->message->delivery_info['delivery_tag'],
            $multiple = false,
            $requeue = true
        );
    }

    public function __invoke($message)
    {
        $this->message = $message;

        $this->nack();
    }
}
