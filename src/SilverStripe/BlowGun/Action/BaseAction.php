<?php
namespace SilverStripe\BlowGun\Action;

use Monolog\Logger;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Console\Input\InputInterface;

class BaseAction {

	/**
	 * @var Message
	 */
	protected $message;

	/**
	 * @var Logger
	 */
	protected $log;

	/**
	 * @var InputInterface
	 */
	protected $input;

	/**
	 * @param Message $message
	 * @param Logger $log
	 */
	public function __construct(Message $message, Logger $log) {
		$this->message = $message;
		$this->log = $log;
	}

	/**
	 * @param string $message
	 */
	protected function logError($message) {
		$context = array($this->message->getQueue(), $this->message->getMessageId());
		$this->log->addError($message, $context);
	}

	/**
	 * @param string $message
	 */
	protected function logNotice($message) {
		$context = array($this->message->getQueue(), $this->message->getMessageId());
		$this->log->addNotice($message, $context);
	}

	/**
	 * @param SQSHandler $mq
	 * @param Process $process
	 * @return bool
	 */
	protected function execProcess(SQSHandler $mq, Process $process) {
		$this->logNotice('Running ' . $process->getCommandLine());

		$process->start(function($type, $buffer) {
			foreach(explode(PHP_EOL, $buffer) as $line) {
				if(!trim($line)) continue;

				if('err' === $type) {
					$this->logError(trim($line));
				} else {
					$this->logNotice(trim($line));
				}
			}
		});

		$currentTime = time();
		$timePassed = 0;

		while($process->isRunning()) {
			$timePassed += time() - $currentTime;
			$currentTime = time();

			// Increase the visibility another 20 sec
			if($timePassed > 20) {
				$this->logNotice('Waiting for command to finish');
				$mq->addVisibilityTimeout($this->message, 20);
				$timePassed = 0;
			}

			$process->checkTimeout();
		}

		return $process->isSuccessful();
	}

}
