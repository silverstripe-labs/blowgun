<?php
namespace SilverStripe\BlowGun\Action;

use Aws\S3\S3Client;
use Monolog\Logger;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;

class SSPakSaveAction {

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
	 * @param $mode
	 * @return bool
	 */
	public function exec(SQSHandler $mq, S3Client $s3, $siteRoot, $mode) {
		$filePath = sys_get_temp_dir().'/'.uniqid('sandbox') . '.pak';
		$siteRoot = escapeshellarg($siteRoot);
		$mode = escapeshellarg($this->message->getArgument('mode'));
		$bucketFolder = escapeshellarg($this->message->getArgument('bucket-folder'));

		$args = array('sspak', 'save', $siteroot, $filepath);
		if($mode && $mode == 'db') {
			$args[] = '--db';
		} elseif($mode && $mode == 'assets') {
			$args[] = '--assets';
		}

		$builder = new ProcessBuilder($args);
		$process = $builder->getProcess();
		$process->setTimeout(3600);

		// @todo(increase visibility timeout of the message after every 1 minute?)
		$this->logNotice('Running '.$process->getCommandLine());
		$process->run(
			function ($type, $buffer) {
				if (Process::ERR === $type) {
					$this->logError($buffer);
				} else {
					$this->logNotice($buffer);
				}
			}
		);

		if(!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}

		$keyName = $bucketFolder . '/' . basename($filePath);
		$bucket = escapeshellarg($this->message->getArgument('bucket'));
		$this->logNotice('Uploading to S3 bucket "'.$bucket.'" '.$keyName);
		$result = $s3->putObject(array(
			'Bucket'       => $bucket,
			'Key'          => $keyName,
			'SourceFile'   => $filePath,
			//@todo(stig) any other options?
			'StorageClass' => 'REDUCED_REDUNDANCY',
		));

		if(!isset($result['ObjectURL'])) {
			$this->log->addError('Upload failed');
			return false;
		}

		$signedUrl = $s3->getObjectUrl($bucket, $keyName, '+10 minutes');

		$this->logNotice('Upload complete');

		if($this->message->getResponseQueue()) {
			$this->logNotice('need to respond to '.$this->message->getResponseQueue());
			$responseMsg = new Message($this->message->getResponseQueue());
			$responseMsg->setAction('sspak/load');
			$responseMsg->setArgument('sspak_url', $signedUrl);
			$mq->send($responseMsg);
		}

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
