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

		var_dump($this->message);

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

		$pakLocation = sprintf('s3://%s/%s',$bucket, $keyName);

		$this->logNotice(sprintf('Uploading to "%s"', $pakLocation));

		try {
			$result = $s3->putObject(array(
				'Bucket'       => $bucket,
				'Key'          => $keyName,
				'SourceFile'   => $filePath,
				//@todo(stig) any other options?
				'StorageClass' => 'REDUCED_REDUNDANCY',
			));
			$this->logNotice('Upload complete');
			$statusMsg = '';
		} catch(\Exception $e) {
			$this->logError('Upload failed: ' . $e->getMessage());
			$status = false;
			$statusMsg = $e->getMessage();
		}

		if($this->message->getRespondTo()) {
			$responseMsg = new Message($this->message->getRespondTo());
			$responseMsg->setType('status');
			$responseMsg->setArgument('sspak_url', $pakLocation);
			$responseMsg->setResponseId($this->message->getResponseId());
			$responseMsg->setSuccess($status);
			$mq->send($responseMsg);
		}

		unlink($filePath);
		$this->logNotice('Deleted file '.$filePath);

		$mq->delete($this->message);
		$this->logNotice('Deleting message');
	}

}
