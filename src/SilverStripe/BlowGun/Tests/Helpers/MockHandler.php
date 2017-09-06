<?php

namespace SilverStripe\BlowGun\Tests\Helpers;

use Monolog\Logger;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;

class MockHandler extends SQSHandler
{
    public function __construct($profile, $region, Logger $logger)
    {
        parent::__construct($profile, $region, $logger);
        $this->client = new MockClient();
    }

    public function getOrCreateQueueURL($queueName)
    {
    }

    public function send(Message $message)
    {
    }

    public function delete(Message $message)
    {
    }

    public function addVisibilityTimeout(Message $message, $seconds)
    {
    }
}
