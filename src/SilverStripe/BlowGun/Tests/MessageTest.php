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

	public function testSetAndGetSuccess() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];
		$rawData['Body'] = '{"type": "test", "success": true}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertTrue($message->isSuccess());

		$rawData['Body'] = '{"type": "test", "success": false}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertFalse($message->isSuccess());

		$rawData['Body'] = '{"type": "test"}';
		$message->load($rawData);
		$this->assertFalse($message->isSuccess());
	}

	public function testSetAndGetMessage() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];
		$rawData['Body'] = '{"type": "test", "message": "hello"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('hello', $message->getMessage());

		$message = new Message('queueName', $this->handler);
		$rawData['Body'] = '{"type": "test"}';
		$message->load($rawData);
		$this->assertEquals('', $message->getMessage());
	}

	public function testSetAndGetErrorMessage() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];
		$rawData['Body'] = '{"type": "test", "error_message": "error!"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('error!', $message->getErrorMessage());

		$rawData['Body'] = '{"type": "test", "error_message": ""}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('', $message->getErrorMessage());
	}

	public function testSetAndGetArguments() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];

		$rawData['Body'] = '{"type": "test", "arguments": {"key1": "value1", "key2": "value2"}}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('value1', $message->getArgument('key1'));
		$this->assertEquals('value2', $message->getArgument('key2'));
		$this->assertNull($message->getArgument('nokey'));
	}

	public function testSetAndGetRespondTo() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];

		$rawData['Body'] = '{"type": "test", "respond_to": "response-queue"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('response-queue', $message->getRespondTo());

		$rawData['Body'] = '{"type": "test"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('', $message->getRespondTo());

		$message->setRespondTo('respond_to', 'myid');
		$this->assertEquals('respond_to', $message->getRespondTo());
		$this->assertEquals('myid', $message->getResponseId());
	}

	public function testSetAndGetResponseID() {
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==',
		];

		$rawData['Body'] = '{"type": "test", "response_id": "12345"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('12345', $message->getResponseId());

		$rawData['Body'] = '{"type": "test"}';
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals('', $message->getResponseId());

		$message->setResponseId('new_id');
		$this->assertEquals('new_id', $message->getResponseId());
	}

	public function testSetAndGetReceiptHandle() {
		$handle = 'AQEBIPxrJx1BF5I5NUnf1PQms46nm1r5JT52DZ9Gxbn/5T4JgtnBEv75gdHPTXS6rFSYr6AGmAJFwpp7otJHbD+3k4s6qQyG/VV5zu+cVkljcPBFkQPDnFdrVRfmJUDXI94HX5Wi6Lzwhn0fNj4JtTCvBo/Hjb2b6/Km8OE+x8VeosOxUZVYG7TNIj03Pp73A8+SNZq7EX9yRp3QfHqs53II8JFHBUwZdhMb8oq+f7Dar+N1d5uko5wZvrNAJkWp2kNrkGAKlnX8FOwYj57w0opVdPZ3U8htDA8q1CwGXabWzqAMb6WkncI000ScHp7RERCFynM3/hb6yX3xQT9kAPaqFg==';
		$rawData = [
			'MessageId' => '0c36a3e2-baf8-4bcf-ad2c-210fc3a0ac75',
			'ReceiptHandle' => $handle,
			'Body' => '{"type": "test", "response_id": "12345"}',
		];
		$message = new Message('queueName', $this->handler);
		$message->load($rawData);
		$this->assertEquals($handle, $message->getReceiptHandle());
	}

	public function testGetAsJson() {
		$message = new Message('queueName', $this->handler);
		$message->setType('test');
		$message->setSuccess(true);
		$message->setArgument('key1','value1');
		$message->setMessage("hello");
		$message->setErrorMessage('ohnoes');
		$message->setRespondTo('response-queue', '12345');
		$expected = <<<EOT
{
    "type": "test",
    "success": true,
    "arguments": {
        "key1": "value1"
    },
    "respond_to": "response-queue",
    "response_id": "12345",
    "message": "hello",
    "error_message": "ohnoes"
}
EOT;
		$this->assertEquals($expected, $message->getAsJson());

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



