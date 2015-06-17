<?php namespace SilverStripe\BlowGun\Command;

use Aws\S3\S3Client;
use SilverStripe\BlowGun\Action\SSPakLoadAction;
use SilverStripe\BlowGun\Action\SSPakSaveAction;
use SilverStripe\BlowGun\Service\MessageQueue;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListenCommand extends BaseCommand {

	/**
	 * Configures the current command.
	 */
	protected function configure()
	{
		parent::configure();
		$this->setName('listen');
		$this->addArgument('cluster');
		$this->addArgument('stack');
		$this->addArgument('env');
		$this->addArgument('site-root');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool|void
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		parent::execute($input, $output);

		while(true) {
			$this->handle($input, $output);
			sleep(1);
		}
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function handle(InputInterface $input, OutputInterface $output) {

		$handler = new SQSHandler($this->profile, $this->region);

		$s3 = S3Client::factory([
			'profile' => $this->profile,
			'region' => $this->region
		]);

		$siteRoot = trim($input->getArgument('site-root'));
		if(!$siteRoot) {
			$errorMsg = 'siteroot is empty';
			$this->log->addCritical($errorMsg);
			throw new \RuntimeException('siteroot is empty');
		}
		if(!file_exists($siteRoot)) {
			$errorMsg = 'site root '.$siteRoot.' doesn\'t exists.';
			$this->log->addCritical($errorMsg);
			throw new \RuntimeException($errorMsg);
		}

		$queueName = $input->getArgument('cluster').'-'.$input->getArgument('stack').'-'.$input->getArgument('env');
		$messages = $handler->fetch($queueName);

		foreach($messages as $message) {
			if(!$message->isValid()) {
				// @todo(stig): push message back on queue
				$this->log->addError($message->getErrorMessage(), array($queueName, $message->getMessageId()));
				continue;
			}

			try {
				switch($message->getAction()) {
					case 'sspak/save':
						$command = new SSPakSaveAction($message, $this->log);
						$command->exec($handler, $s3, $siteRoot);
						break;
					case 'sspak/load':
						$command = new SSPakLoadAction($message, $this->log);
						$command->exec($handler, $s3, $siteRoot);
						break;
					default:
						$this->log->addInfo("Can't handle action '".$message->getAction()."' in message ", array($queueName, $message->getMessageId()));
						continue;
						break;
				}
			} catch(\Exception $e) {
				$this->log->addCritical($e->getMessage(), array($queueName, $message->getMessageId(), get_class($command)));
			}
		}

	}
}