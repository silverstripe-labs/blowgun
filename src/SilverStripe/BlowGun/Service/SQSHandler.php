<?php
namespace SilverStripe\BlowGun\Service;

use Aws\Sqs\SqsClient;
use Monolog\Logger;
use SilverStripe\BlowGun\Exceptions\MessageLoadingException;
use SilverStripe\BlowGun\Model\Message;

class SQSHandler {

	/**
	 * @var string
	 */
	protected $profile = '';

	/**
	 * @var string
	 */
	protected $region = '';

	/**
	 * @var SqsClient|null
	 */
	protected $client = null;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @param string $profile
	 * @param string $region
	 * @param Logger $logger
	 */
	public function __construct($profile, $region, Logger $logger) {

		$this->logger = $logger;

		$this->client = SqsClient::factory(
			[
				'profile' => $profile,
				'region' => $region,
			]
		);
	}

	/**
	 * @param $queueName - short name of the queue, e.g. "my-personal-queue
	 *
	 * @return string
	 */
	public function getOrCreateQueueURL($queueName) {
		$result = $this->client->createQueue(
			[
				'QueueName' => trim($queueName),
			]
		);

		if(isset($result['QueueUrl'])) {
			return $result['QueueUrl'];
		}
		throw new \RuntimeException('Can\t create or find queue ' . $queueName);
	}

	/**
	 * Will return one single message in an array
	 *
	 * @param $queueName
	 * @param int $wait - max 20 seconds
	 *
	 * @return \SilverStripe\BlowGun\Model\Message[]
	 */
	public function fetch($queueName, $wait = 20) {

		$queueURL = $this->getOrCreateQueueURL($queueName);

		if($wait > 20) {
			$wait = 20;
		}

		$result = $this->client->receiveMessage(
			[
				'QueueUrl' => $queueURL,
				'MaxNumberOfMessages' => 1,
				'WaitTimeSeconds' => $wait,
				'AttributeNames' => [
					'All',
				],
			]
		);

		if(!count($result['Messages'])) {
			return [];
		}

		$messages = [];
		foreach($result['Messages'] as $message) {
			$tmp = new Message($queueName, $this);
			try {
				$tmp->load($message);
			} catch(MessageLoadingException $e) {
				$this->logError($e->getMessage(), $tmp);
			}
			$messages[] = $tmp;
		}
		return $messages;
	}

	/**
	 * @param Message $message
	 */
	public function send(Message $message) {

		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->sendMessage(
			[
				'QueueUrl' => $queueURL,
				'MessageBody' => $message->getAsJson(),
				'DataType' => 'string',
			]
		);
	}

	/**
	 * @param Message $message
	 */
	public function delete(Message $message) {

		if(!($message->getReceiptHandle())) {
			throw new \RuntimeException("Can't delete message without ReceiptHandle():\n" . $message->getAsJson());
		}
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->deleteMessage(
			[
				'QueueUrl' => $queueURL,
				'ReceiptHandle' => $message->getReceiptHandle(),
			]
		);
	}

	/**
	 * @param $errorMsg
	 * @param Message $message
	 */
	public function logError($errorMsg, Message $message) {
		$this->logger->addError($errorMsg, [$message->getMessageId(), $message->getQueue()]);
	}

	/**
	 * Increase the timeout before this message gets put back into
	 * the queue.
	 *
	 * @param Message $message
	 * @param int $seconds
	 */
	public function addVisibilityTimeout(Message $message, $seconds) {

		if(!($message->getReceiptHandle())) {
			throw new \RuntimeException("Can't delete message without ReceiptHandle:\n" . $message->getAsJson());
		}
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->changeMessageVisibility(
			[
				'QueueUrl' => $queueURL,
				'ReceiptHandle' => $message->getReceiptHandle(),
				'VisibilityTimeout' => $seconds,
			]
		);
	}
}
