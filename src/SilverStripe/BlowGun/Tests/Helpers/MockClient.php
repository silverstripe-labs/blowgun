<?php

namespace SilverStripe\BlowGun\Tests\Helpers;

use Aws\Sqs\SqsClient;

class MockClient extends SqsClient
{
    public function __construct()
    {
    }

    public function getOrCreateQueueURL($name)
    {
        return 'https://aws.southeast/'.$name;
    }

    public function receiveMessage(array $args = [])
    {
        return [
            'Messages' => [
                [
                    'MessageId' => 'MessageId',
                    'ReceiptHandle' => '',
                    'Body' => 'msg body',
                ],
            ],
        ];
    }
}
