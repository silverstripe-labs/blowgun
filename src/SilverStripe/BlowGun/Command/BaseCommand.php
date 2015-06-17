<?php namespace SilverStripe\BlowGun\Command;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use SilverStripe\BlowGun\Credentials\BlowGunCredentials;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
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
	 *
	 */
	protected function configure() {
		$this
			->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'AWS profile', 'default')
			->addOption('region', 'r', InputOption::VALUE_REQUIRED, 'AWS Region')
			->addOption('role-arn', null, InputOption::VALUE_REQUIRED, 'AWS role arn for temporary assuming a role');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		// Custom styles
		$output->getFormatter()->setStyle(
			'header',
			new OutputFormatterStyle('blue', 'white', array('bold'))
		);

		// Load credentials
		$this->checkCredentials($input);
		$this->log = new Logger('blowgun');
		$this->log->pushHandler(new SyslogHandler('blowgun'));
		$this->log->pushHandler(new StreamHandler(STDOUT));
	}

	/**
	 * Check selected profile and region
	 *
	 * @param InputInterface $input
	 */
	public function checkCredentials(InputInterface $input) {
		// Skip if already set
		if($this->profile && $this->region) return;

		// Check profile name
		$this->profile = $input->getOption('profile') ?: BlowGunCredentials::defaultProfile();

		// Check region
		$this->region = $input->getOption('region') ?: BlowGunCredentials::defaultRegion($this->profile);

		if(!$this->region) {
			throw new \RuntimeException("Missing value for <region> and could not be determined from profile");
		}

		// Assume a role and set the clientFactory to use the temporary credentials for
		// that role. http://docs.aws.amazon.com/STS/latest/APIReference/API_AssumeRole.html
		if($input->getOption('role-arn')) {
			$this->roleArn = $input->getOption('role-arn');
			$stsClient = $this->clientFactory->getStsClient($this->profile);
			$result = $stsClient->assumeRole(array(
					'RoleArn' => $this->roleArn,
					'RoleSessionName' => 'blowgun',
					'DurationSeconds' => 3600,
				)
			);
			// override the default credentials with the temporary one
			$this->clientFactory->setCredentials($stsClient->createCredentials($result));
		}
	}

	/**
	 * Set profile and region explicitly
	 *
	 * @param string $profile
	 * @param string $region
	 */
	public function setCredentials($profile, $region) {
		$this->profile = $profile;
		$this->region = $region;
	}

	/**
	 * Helper for printing headers
	 *
	 * @param OutputInterface $output
	 * @param string $header
	 */
	protected function printHeader(OutputInterface $output, $header) {
		$output->writeln("");
		$output->writeln("<header>$header</header> ");
	}

	/**
	 * Helper for printing tables
	 *
	 * @param OutputInterface $output
	 * @param array|ParameterList $data List of data to output
	 */
	protected function printTable(OutputInterface $output, $data) {
		// Set column widths to 30 and 70
		$left = 30;
		$right = 70;
		if(!count($data)) {
			$output->writeln('<comment>No data</comment>');
			return;
		}
		foreach($data as $key => $value) {
			while($key || $value) {
				$wrap = '';
				if(strlen($value) > $right) {
					$wrap = substr($value, $right);
					$value = substr($value, 0, $right);
				}
				$output->writeln(
					"<comment>" . str_pad($key, $left, ' ') . "</comment><info>$value</info> "
				);
				$key = '';
				$value = $wrap;
			}
		}
	}

}
