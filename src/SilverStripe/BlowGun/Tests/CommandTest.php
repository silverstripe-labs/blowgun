<?php

namespace SilverStripe\BlowGun\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use SilverStripe\BlowGun\Model\Command;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Tests\Helpers\MockHandler;

class CommandTest extends \PHPUnit_Framework_TestCase
{
    protected $logHandler;

    protected $handler;

    protected $logger;

    /**
     * @var Command
     */
    protected $command;

    public function setUp()
    {
        $this->logHandler = new TestHandler();
        $this->logger = new Logger('testLogger', [$this->logHandler]);
        $this->handler = new MockHandler('fakeprofile', 'region', $this->logger);
    }

    public function testRunProcessIsSuccessful()
    {
        $msg = new Message('test-queue', $this->handler);
        $msg->setType('echo_command');
        $command = new Command($msg, __DIR__.'/test_scripts');
        $status = $command->run($this->logger);
        $this->assertTrue($status->isSuccessful());
        $this->assertEquals('Hello world', $status->getNotices()[0]);
    }

    public function testRunProcessScriptNotFound()
    {
        $msg = new Message('test-queue', $this->handler);
        $msg->setType('dont_exists');
        $command = new Command($msg, __DIR__.'/test_scripts');
        $status = $command->run($this->logger);
        $this->assertFalse($status->isSuccessful());
        if (PHP_OS_FAMILY == 'Linux') {
            $this->assertContains('not found', $status->getErrors()[0]);
        } elseif (PHP_OS_FAMILY == 'Darwin') {
            $this->assertContains('No such file', $status->getErrors()[0]);
        } else {
            $this->markTestSkipped('unknown OS '.PHP_OS);
        }
    }

    public function testRunProcessScriptError()
    {
        $msg = new Message('test-queue', $this->handler);
        $msg->setType('error_command');
        $command = new Command($msg, __DIR__.'/test_scripts');
        $status = $command->run($this->logger);
        $this->assertFalse($status->isSuccessful());
        $this->assertEquals('I will fail', $status->getNotices()[0]);
        $this->assertEquals('command failed', $status->getErrors()[0]);
    }

    public function testRunScriptReturnArguments()
    {
        $msg = new Message('test-queue', $this->handler);
        $msg->setType('arg_command');

        $msg->setArgument('arg1', 'value1');
        $command = new Command($msg, __DIR__.'/test_scripts');
        $status = $command->run($this->logger);
        $this->assertTrue($status->isSuccessful());

        $this->assertEquals(['arg1' => 'value1'], $status->getData());
        $this->assertEquals('arg1=value1', $status->getNotices()[0]);
    }
}
