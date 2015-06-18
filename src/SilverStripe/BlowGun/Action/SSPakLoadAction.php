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

		// @todo(increase visibility timeout of the message after every 1 minute?)
		$this->logNotice('Running '.$process->getCommandLine());
		$process->run(
			function ($type, $buffer) {
				if (Process::ERR === $type) {
					$this->logError('| '.$buffer);
				} else {
					$this->logNotice('| '.$buffer);
				}
			}
		);

		if(!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}

		$this->logNotice('Upload complete');

		unlink($filePath);
		$this->logNotice('Deleted file '.$filePath);

		$mq->delete($this->message);
		$this->logNotice('Deleting message');

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
}
