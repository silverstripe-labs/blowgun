<?php
namespace SilverStripe\BlowGun\Command;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SilverStripe\BlowGun\Credentials\BlowGunCredentials;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handle common parameters for AWS commands
 */
abstract class BaseCommand extends Command {

	/**
	 * Name of current profile
	 *
	 * @var string
	 */
	protected $profile = null;

	/**
	 * Name of current region
	 *
	 * @var string
	 */
	protected $region = null;

	/**
	 * @var string
	 */
	protected $roleArn = null;

	/**
	 * @var \Monolog\Logger
	 */
	protected $log;

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		// Load credentials
		$this->setCredentials($input);
		$this->log = new Logger('blowgun');

		$streamLogger = new StreamHandler(STDOUT);
		$streamFormatter = new LineFormatter("%level_name% - %message% %context%\n");
		$streamLogger->setFormatter($streamFormatter);
		$this->log->pushHandler($streamLogger );
	}

	/**
	 * Check selected profile and region
	 *
	 * @param InputInterface $input
	 *
	 * @throws \Exception
	 */
	public function setCredentials(InputInterface $input) {

		if($this->profile && $this->region) {
			return;
		}

		if($input->getOption('profile')) {
			$this->profile = $input->getOption('profile');
		} elseif(BlowGunCredentials::defaultProfile()) {
			$this->profile = BlowGunCredentials::defaultProfile();
		}

		// Check region
		$this->region = BlowGunCredentials::defaultRegion($this->profile);
		if($input->getOption('region')) {
			$this->region = $input->getOption('region');
		}

		// We always needs a region
		if(!$this->region) {
			throw new \RuntimeException("Missing value for <region> and could not be determined from profile");
		}

	}

	/**
	 *
	 */
	protected function configure() {
		$this->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'AWS profile')
		     ->addOption('region', 'r', InputOption::VALUE_REQUIRED, 'AWS Region')
		     ->addOption('role-arn', null, InputOption::VALUE_REQUIRED, 'AWS role arn for temporary assuming a role');
	}


}
