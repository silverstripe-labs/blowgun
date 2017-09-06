<?php

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use SilverStripe\BlowGun\Service\SQSHandler;

class SQSHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Monolog\Handler\TestHandler
     */
    protected $logHandler;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PHPUnit_Framework_MockObject_MockBuilder
     */
    protected $mockBuilder;

    public function setUp()
    {
        $this->logHandler = new TestHandler();
        $this->logger = new Logger('testLogger', [$this->logHandler]);
        $this->mockBuilder = $this->getMockBuilder('\Aws\Sqs\SqsClient')->disableOriginalConstructor();
    }

    public function testGetInstance()
    {
        $inst = new SQSHandler('profile', 'region', $this->logger);
        $this->assertTrue($inst instanceof SQSHandler);
    }

    public function testSetClient()
    {
        $inst = new SQSHandler('profile', 'region', $this->logger);
        $mock = $this->getMockBuilder('\Aws\Sqs\SqsClient')->disableOriginalConstructor();
        $client = $mock->getMock();
        $inst->setClient($client);
    }

    public function testListQueues()
    {
        $inst = new SQSHandler('profile', 'region', $this->logger);
        $this->mockBuilder->setMethods(['listQueues']);
        $mock = $this->mockBuilder->getMock();
        $mock->method('listQueues')
            ->willReturn(['QueueUrls' => ['https://some/ulr/queuename1', 'https://some/ulr/queuename2']]);
        $inst->setClient($mock);
        $queues = $inst->listQueues('queue');
        $this->assertEquals(['queuename1', 'queuename2'], $queues);
    }
}
