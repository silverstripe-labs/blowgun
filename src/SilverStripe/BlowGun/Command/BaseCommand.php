<?php

namespace SilverStripe\BlowGun\Command;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RuntimeException;
use SilverStripe\BlowGun\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handle common parameters for AWS commands.
 */
abstract class BaseCommand extends Command
{
    /**
     * Name of current profile.
     *
     * @var string
     */
    protected $profile;

    /**
     * Name of current region.
     *
     * @var string
     */
    protected $region;

    /**
     * @var string
     */
    protected $roleArn;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * Check selected profile and region.
     *
     * @param InputInterface $input
     *
     * @throws RuntimeException
     */
    public function setCredentials(InputInterface $input)
    {
        if ($this->profile && $this->region) {
            return;
        }

        if ($input->getOption('profile')) {
            $this->profile = $input->getOption('profile');
        } elseif (Util::defaultProfile()) {
            $this->profile = Util::defaultProfile();
        }

        if ($input->getOption('region')) {
            $this->region = $input->getOption('region');
        } else {
            $this->region = Util::defaultRegion($this->profile);
        }

        if (!$this->region) {
            throw new RuntimeException('Missing value for <region> and could not be determined from profile');
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setCredentials($input);
        $this->log = new Logger('blowgun');

        $streamLogger = new StreamHandler(STDOUT);
        $streamFormatter = new LineFormatter("%level_name% - %message% %context%\n");
        $streamLogger->setFormatter($streamFormatter);
        $this->log->pushHandler($streamLogger);
    }

    protected function configure()
    {
        $this->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'AWS profile')
             ->addOption('region', 'r', InputOption::VALUE_REQUIRED, 'AWS Region')
             ->addOption('role-arn', null, InputOption::VALUE_REQUIRED, 'AWS role arn for temporary assuming a role');
    }
}
