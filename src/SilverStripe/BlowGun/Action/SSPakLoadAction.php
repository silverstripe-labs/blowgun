<?php
namespace SilverStripe\BlowGun\Action;

use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Aws\S3\S3Client;

class SSPakLoadAction extends BaseAction {

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

		$this->logNotice('Downloaded ' . $filePath);

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
		$this->logNotice('Deleted file ' . $filePath);

		$mq->delete($this->message);
		$this->logNotice('Deleted message');
	}

}
