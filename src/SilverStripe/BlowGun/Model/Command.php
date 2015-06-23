<?php
namespace SilverStripe\BlowGun\Model;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Command {

	/**
	 * SQS queues by default set message visibility to 30 seconds
	 */
	const MSG_DEFAULT_VISIBILITY = 30;

	/**
	 * @var Message
	 */
	protected $message;

	/**
	 * @var Process
	 */
	protected $process;

	/**
	 * @param Message $message
	 * @param string $scriptDir
	 * @param string $siteRoot
	 */
	public function __construct(Message $message, $scriptDir, $siteRoot) {
		$this->message = $message;
		$this->scriptDir = $scriptDir;
		$this->siteRoot = $siteRoot;
		$this->process = $this->getProcess();
	}

	/**
	 * @return Status
	 */
	public function run() {
		$status = new Status();
		$this->process = $this->getProcess();
		try {
			$this->execProcess($status);
		} catch(\Exception $e) {
			$status->Failed();
			$status->addError($e->getMessage());
		}
		return $status;
	}

	/**
	 * @return Process
	 */
	protected function getProcess() {

		$scriptPath = sprintf(
			"%s/%s",
			realpath($this->scriptDir),
			basename($this->message->getType())
		);
		$builder = new ProcessBuilder([$scriptPath]);
		// Inject the arguments into the ENV for the script, it's the easiest
		// way to get key=value params into it since there is other good option
		// for // supplying named parameters for a bash script
		foreach($this->message->getArguments() as $name => $value) {
			$builder->setEnv($name, $value);
		}
		$builder->setEnv('webroot', $this->siteRoot);
		$process = $builder->getProcess();
		$process->setTimeout(3600);
		return $process;
	}

	/**
	 * @param Status $status
	 *
	 */
	protected function execProcess(Status $status) {

		// Run the command and capture data from stdout
		$this->process->start(
			function ($type, $buffer) use ($status) {

				foreach(explode(PHP_EOL, $buffer) as $line) {
					$line = trim($line);
					if(!$line) {
						continue;
					}
					if('err' === $type) {
						$status->addError($line);
						continue;
					}
					// this capture data that the script outputs
					if(stristr($line, '=')) {
						list($key, $value) = explode('=', $line);
						$status->setData($key, $value);
					}
					$status->addNotice($line);
				}
			}
		);
		// Wait for the command to finish and also increase the visibility
		// timeout so that the message doesn't get put back into the queue
		// while this instance of the worker is still working on it.
		$previousTime = time();
		$timeout = (self::MSG_DEFAULT_VISIBILITY - 10);
		while($this->process->isRunning()) {
			$now = time();
			if($previousTime + $timeout < $now) {
				$this->message->increaseVisibility($timeout);
				$previousTime = $now;
			}
			$this->process->checkTimeout();
		}

		if($this->process->isSuccessful()) {
			$status->succeeded();
		} else {
			$status->failed();
		}
	}
}