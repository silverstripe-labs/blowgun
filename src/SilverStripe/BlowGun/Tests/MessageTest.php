<?php

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Tests\Helpers\MockHandler;

class MessageTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var MockHandler
	 */
	protected $handler = null;

	/**
	 * @var \Monolog\Handler\TestHandler
	 */
	protected $logHandler = null;

	public function setUp() {
		$this->logHandler = new TestHandler();
		$logger = new Logger('testLogger', [$this->logHandler]);
		$this->handler = new MockHandler('fakeprofile', 'region', $logger);
		parent::setUp();
	}

	public function testLoadIsValid() {
		$message = new Message('queueName', $this->handler);
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
			'Body' => $this->getJSON('example'),
		];
		$message->load($rawData);
	}

	/**
	 * @expectedException \SilverStripe\BlowGun\Exceptions\MessageLoadingException
	 */
	public function testMissingReceiptHandle() {
		$message = new Message('queueName', $this->handler);
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => '',
			'Body' => '',
		];
		$message->load($rawData);
	}

	public function testFetchLogsMessageLoadingException() {
		$this->handler->fetch('test-queue');
		$records = $this->logHandler->getRecords();
		$this->assertEquals('Syntax error, malformed JSON', $records[0]['message']);
	}

	protected function getJSON($name) {
		$content = file_get_contents(__DIR__ . '/example_message/' . $name . '.json');
		return $content;
	}

}
