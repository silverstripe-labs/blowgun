<?php
namespace SilverStripe\BlowGun\Command;

use Aws\S3\S3Client;
use SilverStripe\BlowGun\Action\SSPakLoadAction;
use SilverStripe\BlowGun\Action\SSPakSaveAction;
use SilverStripe\BlowGun\Model\Message;
use SilverStripe\BlowGun\Service\MessageQueue;
use SilverStripe\BlowGun\Service\SQSHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class ListenCommand extends BaseCommand {

	/**
	 * Configures the current command.
	 */
	protected function configure() {
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
	protected function execute(InputInterface $input, OutputInterface $output) {
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

		$s3 = S3Client::factory(array(
			'profile' => $this->profile,
			'region' => $this->region
		));

		$siteRoot = trim($input->getArgument('site-root'));
		if(!$siteRoot) {
			$errorMsg = 'Missing site-root argument';
			$this->log->addCritical($errorMsg);
			throw new \RuntimeException($errorMsg);
		}
		if(!file_exists($siteRoot)) {
			$errorMsg = sprintf('%s does not exist!', $siteRoot);
			$this->log->addCritical($errorMsg);
			throw new \RuntimeException($errorMsg);
		}

		$queueName = sprintf(
			'%s-%s-%s',
			$input->getArgument('cluster'),
			$input->getArgument('stack'),
			$input->getArgument('env')
		);

		$messages = $handler->fetch($queueName);

		foreach($messages as $message) {

			if(!$message->isValid()) {
				$this->logError($message->getErrorMessage(), $message);
				continue;
			}

			$type = $message->getType();

			// At this point there is no action a blowgun worker can do with
			// a status message, so just log it and delete the message
			if($type == 'status') {
				$this->logNotice($message->getMessage(), $message);
				$handler->delete($message);
				$this->logNotice('Deleted message', $message);
				continue;
			}

			$scriptPath = sprintf("%s/scripts/%s", BASE_DIR, $type);
			// ProcessBuilder escapes the args for you!
			$builder = new ProcessBuilder(array($scriptPath));
			// Inject the arguments into the ENV for the script, it's the easiest
			// way to get key=value params into it since there is other good option
			// for // supplying named parameters for a bash script
			foreach($message->getArguments() as $name => $value) {
				$builder->setEnv($name, $value);
			}
			$builder->setEnv('webroot', $siteRoot);

			$process = $builder->getProcess();
			$process->setTimeout(3600);

			// The output data contains key=value pair that have been echo'd by
			// the script.
			$outputData = [];
			try {
				$outputData = $this->execProcess($handler, $message, $process);
			// Capture timeout errors and other exceptions
			} catch(\Exception $e) {
				$this->logError($e->getMessage(), $message);
			}

			if($message->getRespondTo()) {
				$responseMsg = new Message($message->getRespondTo());
				$responseMsg->setType('status');
				foreach($outputData as $key => $value) {
					$responseMsg->setArgument($key, $value);
				}
				$responseMsg->setResponseId($message->getResponseId());
				$responseMsg->setSuccess($process->isSuccessful());
				$handler->send($responseMsg);
			}

			$handler->delete($message);
			$this->logNotice('Deleted message', $message);
		}
	}

	/**
	 * @param SQSHandler $handler
	 * @param Message $message
	 * @param Process $process
	 * @return array - will return data that the script prints to STDOUT
	 */
	protected function execProcess(SQSHandler $handler, Message $message, Process $process) {
		$this->logNotice('Running ' . $process->getCommandLine(), $message);

		$outputData = [];

		// Run the command and capture data from stdout
		$process->start(function($type, $buffer) use(&$outputData, $message) {
			foreach(explode(PHP_EOL, $buffer) as $line) {
				if(!trim($line)) {
					continue;
				}
				if('err' === $type) {
					$this->logError(trim($line), $message);
					continue;
				}
				// this capture data that the script outputs
				if(stristr($line, '=')) {
					list($key, $value) = explode('=', $line);
					$outputData[trim($key)] = trim($value);
				}
				$this->logNotice(trim($line), $message);
			}
		});

		// Wait for the command to finish and also increase the visibility
		// timeout so that the message doesn't get put back into the queue
		// while this instance of the worker is still working on it.
		$currentTime = time();
		$timePassed = 0;
		while($process->isRunning()) {
			$timePassed += time() - $currentTime;
			$currentTime = time();
			if($timePassed > 20) {
				$this->logNotice('Waiting for command to finish', $message);
				$handler->addVisibilityTimeout($message, 20);
				$timePassed = 0;
			}
			$process->checkTimeout();
		}

		return $outputData;
	}

	/**
	 * @param $string
	 * @param Message|string $message
	 */
	protected function logError($string, Message $message) {
		$this->log->addError($string, [$message->getQueue(), $message->getMessageId()]);
	}

	/**
	 * @param $string
	 * @param Message|string $message
	 */
	protected function logNotice($string, Message $message) {
		$this->log->addNotice($string, [$message->getQueue(), $message->getMessageId()]);
	}
}
