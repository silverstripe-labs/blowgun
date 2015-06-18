<?php
namespace SilverStripe\BlowGun\Service;

use Aws\CloudFront\Exception\Exception;
use Aws\Sqs\SqsClient;
use SilverStripe\BlowGun\Model\Message;
use Symfony\Component\Process\Exception\RuntimeException;

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
	 * @param string $profile
	 * @param string $region
	 */
	public function __construct($profile, $region) {
		$this->client = SqsClient::factory(
			array(
				'profile' =>$profile,
				'region' => $region
			)
		);
	}

	/**
	 * @param $queueName - short name of the queue, e.g. "my-personal-queue
	 * @return string
	 */
	public function getOrCreateQueueURL($queueName) {
		$result = $this->client->createQueue(array(
			'QueueName' => trim($queueName),
		));

		if(isset($result['QueueUrl'])) {
			return $result['QueueUrl'];
		}
		throw new \RuntimeException('Can\t create or find queue '.$queueName);
	}

	/**
	 * Will return one single message in an array
	 *
	 * @param $queueName
	 * @param int $wait - max 20 seconds
	 * @return \SilverStripe\BlowGun\Model\Message[]
	 */
	public function fetch($queueName, $wait=2) {
		$queueURL = $this->getOrCreateQueueURL($queueName);

		if($wait > 20) {
			$wait = 20;
		}

		$result = $this->client->receiveMessage(array(
			'QueueUrl' => $queueURL,
			'MaxNumberOfMessages' => 1,
			'WaitTimeSeconds' => $wait,
			'AttributeNames' => array(
				'All'
			)
		));

		// @todo(stig): investigate the AttributeNames

		if(!count($result['Messages'])) {
			return [];
		}

		$messages = [];
		foreach($result['Messages'] as $message) {
			$tmp = new Message($queueName);
			$tmp->load($message);
			$messages[] = $tmp;
		}
		return $messages;
	}

	/**
	 * @param Message $message
	 */
	public function send(Message $message) {
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$result = $this->client->sendMessage(array(
			'QueueUrl' => $queueURL,
			'MessageBody' => $message->getRawBody(),
			'DataType' => 'string',
		));
	}

	/**
	 * @param Message $message
	 */
	public function delete(Message $message) {
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->deleteMessage(array(
			'QueueUrl' => $queueURL,
			'ReceiptHandle' => $message->getReceiptHandle())
		);
	}

	/**
	 * Increase the timeout before this message gets put back into
	 * the queue.
	 *
	 * @param Message $message
	 * @param int $seconds
	 */
	public function addVisibilityTimeout(Message $message, $seconds) {
		$queueURL = $this->getOrCreateQueueURL($message->getQueue());
		$this->client->changeMessageVisibility([
			'QueueUrl' => $queueURL,
			'ReceiptHandle' => $message->getReceiptHandle(),
			'VisibilityTimeout' => $seconds
		]);
	}
}
