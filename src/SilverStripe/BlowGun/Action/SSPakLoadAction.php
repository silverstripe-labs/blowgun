<?php
namespace SilverStripe\BlowGun\Action;

use Aws\S3\S3Client;
use Monolog\Logger;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class SSPakLoadAction {

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
	 * @param SQSHandler $mq
	 * @param S3Client $s3
	 * @param $siteRoot
	 * @return bool
	 */
	public function exec(SQSHandler $mq, S3Client $s3, $siteRoot) {
		$ssPakUrl = $this->message->getArgument('sspak_url');
		$filePath = sys_get_temp_dir().'/'.uniqid('sandbox') . '.sspak';

		$downloaded = @file_put_contents($filePath, fopen($ssPakUrl, 'r'));
		if(!$downloaded) {
			$this->logError('could not download file');
			return;
		}

		$this->logNotice('Downloaded '.$filePath);

		// ProcessBuilder escapes the args for you!
		$builder = new ProcessBuilder(array('sspak', 'load', $filePath, $siteRoot));
		$process = $builder->getProcess();

		$process->setTimeout(3600);
		$status = $this->execProcess($mq, $process);

		if($this->message->getResponseQueue()) {
			$responseMsg = new Message($this->message->getResponseQueue());
			$responseMsg->setArgument('response_id', $this->message->getArgument('response_id'));
			$responseMsg->setArgument('status', $status);
			$mq->send($responseMsg);
			$this->logNotice('Sent response message');
		}

		unlink($filePath);
		$this->logNotice('Deleted file '.$filePath);

		$mq->delete($this->message);
		$this->logNotice('Deleted message');
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
	 * @return mixed
	 */
	protected function execProcess(SQSHandler $mq, Process $process)
	{
		$this->logNotice('Running ' . $process->getCommandLine());
		$process->start(function ($type, $buffer) {
			foreach (explode(PHP_EOL, $buffer) as $line) {
				if (!trim($line)) { continue; }
				if ('err' === $type) {
					$this->logError(trim($line));
				} else {
					$this->logNotice(trim($line));
				}
			}
		});
		$currentTime = time();
		$timePassed = 0;
		while ($process->isRunning()) {
			$timePassed += time() - $currentTime;
			$currentTime = time();
			// Increase the visibility another 20 sec
			if ($timePassed > 20) {
				$this->logNotice('Waiting for command to finish');
				$mq->addVisibilityTimeout($this->message, 20);
				$timePassed = 0;
			}
			$process->checkTimeout();
		}

		$status = $process->isSuccessful();
		return $status;
	}
}