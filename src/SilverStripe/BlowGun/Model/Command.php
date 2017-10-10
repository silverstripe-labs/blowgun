<?php

namespace SilverStripe\BlowGun\Model;

use Exception;
use Monolog\Logger;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Command
{
    /**
     * SQS visibility period for updates.
     */
    const MSG_UPDATE_VISIBILITY_TIMEOUT = 30;

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
     * @param string  $scriptDir
     */
    public function __construct(Message $message, $scriptDir)
    {
        $this->message = $message;
        $this->scriptDir = $scriptDir;
        $this->process = $this->getProcess();
    }

    /**
     * @param Logger $logger
     *
     * @return Status
     */
    public function run(Logger $logger)
    {
        $status = new Status();
        $this->process = $this->getProcess();

        try {
            $this->execProcess($status, $logger);
        } catch (Exception $e) {
            $status->failed();
            $status->addError($e->getMessage());
        }

        return $status;
    }

    /**
     * @return Process
     */
    protected function getProcess()
    {
        $scriptPath = sprintf(
            '%s/%s',
            realpath($this->scriptDir),
            basename($this->message->getType())
        );
        $builder = new ProcessBuilder([$scriptPath]);
        // Inject the arguments into the ENV for the script, it's the easiest
        // way to get key=value params into it since there is other good option
        // for // supplying named parameters for a bash script
        if ($this->message->getArguments()) {
            foreach ($this->message->getArguments() as $name => $value) {
                $builder->setEnv($name, $value);
            }
        }
        $process = $builder->getProcess();
        $process->setTimeout(3600);

        return $process;
    }

    /**
     * @param Status $status
     * @param Logger $logger
     */
    protected function execProcess(Status $status, Logger $logger)
    {
        // Run the command and capture data from stdout
        $this->process->start(
            function ($type, $buffer) use ($status, $logger) {
                foreach (explode(PHP_EOL, $buffer) as $line) {
                    $line = trim($line);
                    if (!$line) {
                        continue;
                    }
                    if ('err' === $type) {
                        if ($logger !== null) {
                            $logger->addError($line, [$this->message->getQueue(), $this->message->getMessageId()]);
                        }
                        $status->addError($line);

                        continue;
                    }
                    // this capture data that the script outputs
                    if (stristr($line, '=')) {
                        list($key, $value) = explode('=', $line);
                        $status->setData($key, $value);
                    }
                    if ($logger !== null) {
                        $logger->addNotice($line, [$this->message->getQueue(), $this->message->getMessageId()]);
                    }
                    $status->addNotice($line);
                }
            }
        );
        // Wait for the command to finish and also increase the visibility
        // timeout so that the message doesn't get put back into the queue
        // while this instance of the worker is still working on it.
        $previousTime = time();
        $timeout = (self::MSG_UPDATE_VISIBILITY_TIMEOUT - 10);
        while ($this->process->isRunning()) {
            $now = time();
            if ($previousTime + $timeout < $now) {
                $this->message->increaseVisibility(self::MSG_UPDATE_VISIBILITY_TIMEOUT);
                $this->message->logNotice(
                    sprintf('Increased visibility by %ss', self::MSG_UPDATE_VISIBILITY_TIMEOUT)
                );
                $previousTime = $now;
            }
            $this->process->checkTimeout();
        }

        if ($this->process->isSuccessful()) {
            $status->succeeded();
        } else {
            $status->failed();
        }
    }
}
