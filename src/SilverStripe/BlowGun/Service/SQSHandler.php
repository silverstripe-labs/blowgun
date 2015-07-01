<?php
namespace SilverStripe\BlowGun\Service;

use Aws\Common\Credentials\Credentials;
use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Monolog\Logger;
use SilverStripe\BlowGun\Exceptions\MessageLoadingException;
use SilverStripe\BlowGun\Model\Message;

class SQSHandler {

	// http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.Sqs.SqsClient.html#_setQueueAttributes
	const QUEUE_DEFAULT_DELAY_SECONDS = 0;
	const QUEUE_DEFAULT_MAXIMUM_MESSAGE_SIZE = 262144; // 256 KiB
	const QUEUE_DEFAULT_MESSAGE_RETENTION_PERIOD = 21600; // 6 hours
	const QUEUE_DEFAULT_VISIBILITY_TIMEOUT = 30; // 30 seconds

	/**
	 * @var Credentials
	 */
	protected static $credentials;

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
	 * @param Credentials $credentials
	 */
	public static function setCredentials(Credentials $credentials) {
		self::$credentials = $credentials;
	}

	/**
	 * @param string $profile
	 * @param string $region
	 * @param Logger $logger
	 */
	public function __construct($profile, $region, Logger $logger) {

		$this->logger = $logger;

		if(self::$credentials) {
			$this->client =  SqsClient::factory(array(
	             'credentials' => self::$credentials,
	             'region' => $region
	         ));
			return;
		}
		$this->client = SqsClient::factory(
			[
				'profile' => $profile,
				'region' => $region,
			]
		);
	}

	/**
	 * @param string $queuePrefix
	 *
	 * @return array
	 */
	public function listQueues($queuePrefix) {
		$result = $this->client->listQueues(['QueueNamePrefix' => $queuePrefix]);
		if(!isset($result['QueueUrls'])) {
			return [];
		}
		$queueNames = [];
		foreach($result['QueueUrls'] as $queueURL) {
			$queueNames[] = basename($queueURL);
		}
		return $queueNames;
	}

	/**
	 * @param $queueName - short name of the queue, e.g. "my-personal-queue
	 *
	 * @return string
	 */
	public function getOrCreateQueueURL($queueName) {
		$queueURL = $this->getQueueUrl($queueName);
		if($queueURL) {
			return $queueURL;
		}

		$queueURL = $this->createQueueWithDefaultAttributes($queueName);
		if($queueURL) {
			return $queueURL;
		}

		throw new \RuntimeException('Can\t create or find queue ' . $queueName);
	}

	/**
	 * Will return one single message in an array
	 *
	 * @param string $queueName
	 * @param int $waitTimeSeconds - max 20 seconds
	 *
	 * @return \SilverStripe\BlowGun\Model\Message[]
	 */
	public function fetch($queueName, $waitTimeSeconds = 20) {
		$queueURL = $this->getOrCreateQueueURL($queueName);
		if($waitTimeSeconds > 20) {
			$waitTimeSeconds = 20;
		}
		$result = $this->client->receiveMessage(
			[
				'QueueUrl' => $queueURL,
				'MaxNumberOfMessages' => 1,
				'WaitTimeSeconds' => $waitTimeSeconds,
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
		$this->validateReceiptHandle($message);
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
		$this->validateReceiptHandle($message);
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->changeMessageVisibility(
			[
				'QueueUrl' => $queueURL,
				'ReceiptHandle' => $message->getReceiptHandle(),
				'VisibilityTimeout' => $seconds,
			]
		);
	}

	/**
	 * @param $queueName
	 *
	 * @return string
	 */
	protected function createQueueWithDefaultAttributes($queueName) {
		$result = $this->client->createQueue(
			[
				'QueueName' => trim($queueName),
			]
		);

		if(!isset($result['QueueUrl'])) {
			throw new \RuntimeException("Cant create queue with name ".$queueName);
		}
		$queueURL = $result['QueueUrl'];

		$deadQueueURL = $this->getQueueUrl('dead-messages');
		if(!$deadQueueURL) {
			$deadQueueURL = $this->createDeadLetterQueue();
		}
		$deadQueueAttributes = $this->getQueueAttributes($deadQueueURL);
		$this->setDefaultQueueAttributes($queueURL, $deadQueueAttributes['QueueArn']);

		return $queueURL;
	}

	/**
	 * @param $queueName
	 *
	 * @return string
	 */
	protected function getQueueUrl($queueName) {
		try {
			$result = $this->client->getQueueUrl(
				[
					'QueueName' => trim($queueName),
				]
			);
		} catch(SqsException $e) {
			return '';
		}
		return $result['QueueUrl'];
	}

	/**
	 * @return string
	 */
	protected function createDeadLetterQueue() {
		$result = $this->client->createQueue(
			[
				'QueueName' => 'dead-messages'
			]
		);
		return $result['QueueUrl'];
	}

	/**
	 * @param string $queueURL
	 *
	 * @return array
	 */
	protected function getQueueAttributes($queueURL) {
		$result = $this->client->getQueueAttributes(
			[
				'QueueUrl' => $queueURL,
				'AttributeNames' => [
					'All'
				]
			]
		);
		return $result['Attributes'];
	}

	/**
	 * @param string $queueURL
	 * @param string $deadQueueArn
	 */
	protected function setDefaultQueueAttributes($queueURL, $deadQueueArn) {
		$this->client->setQueueAttributes(
			[
				'QueueUrl' => $queueURL,
				'Attributes' => [
					'DelaySeconds' => self::QUEUE_DEFAULT_DELAY_SECONDS,
					'MaximumMessageSize' => self::QUEUE_DEFAULT_MAXIMUM_MESSAGE_SIZE,
					'MessageRetentionPeriod' => self::QUEUE_DEFAULT_MESSAGE_RETENTION_PERIOD,
					'VisibilityTimeout' => self::QUEUE_DEFAULT_VISIBILITY_TIMEOUT,
					'RedrivePolicy' => '{"maxReceiveCount": 6, "deadLetterTargetArn": "'.$deadQueueArn.'"}'
				]
			]
		);
	}

	/**
	 * @param Message $message
	 */
	private function validateReceiptHandle(Message $message) {
		if($message->getReceiptHandle() == '') {
			throw new \RuntimeException("Can't delete message without ReceiptHandle():\n" . $message->getAsJson());
		}
	}
}
