<?php
namespace SilverStripe\BlowGun\Action;

use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Aws\S3\S3Client;

class SSPakSaveAction extends BaseAction {

	/**
	 * @param SQSHandler $mq
	 * @param S3Client $s3
	 * @param $siteRoot
	 * @return bool
	 */
	public function exec(SQSHandler $mq, S3Client $s3, $siteRoot) {
		$filePath = sys_get_temp_dir().'/'.uniqid('sandbox') . '.pak';
		$mode = $this->message->getArgument('mode');

		$args = array('sspak', 'save', $siteRoot, $filePath);
		if($mode && $mode == 'db') {
			$args[] = '--db';
		} elseif($mode && $mode == 'assets') {
			$args[] = '--assets';
		}

		// ProcessBuilder escapes the args for you!
		$builder = new ProcessBuilder($args);
		$process = $builder->getProcess();
		$process->setTimeout(3600);
		$status = $this->execProcess($mq, $process);

		$keyName = $this->message->getArgument('bucket-folder') . '/' . basename($filePath);
		$bucket = $this->message->getArgument('bucket');
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
			$responseMsg->setArgument('sspak_url', $signedUrl);
			$responseMsg->setArgument('response_id', $this->message->getArgument('response_id'));
			$responseMsg->setArgument('status', $status);
			$mq->send($responseMsg);
		}

		unlink($filePath);
		$this->logNotice('Deleted file '.$filePath);

		$mq->delete($this->message);
		$this->logNotice('Deleting message');
	}

}
