<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swarrot\Consumer;
use Swarrot\Broker\PeclPackageMessageProvider;
use Swarrot\Broker\Message;
use Swarrot\Processor\ProcessorInterface;

class Processor implements ProcessorInterface {
    public function process(Message $message, array $options) {
        echo sprintf("Consume message #%d\n", $message->getId());
    }
}

$connection = new \AMQPConnection();
$connection->connect();
$channel = new \AMQPChannel($connection);
$queue = new \AMQPQueue($channel);
$queue->setName('global');

$messageProvider = new PeclPackageMessageProvider($queue);

$consumer = new Consumer($messageProvider, new Processor());
$consumer->consume();
