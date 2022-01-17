<?php

namespace Krlove\AsyncServiceCallBundle;

use Symfony\Component\Process\Process;

/**
 * Class AsyncService
 * @package Krlove\AsyncServiceCallBundle
 */
class AsyncService
{
    // All output must be redirected, else the script will hang as long as executes.
    const RUN_COMMAND_IN_BACKGROUND = '/dev/null 2>/dev/null ';
    const RUN_COMMAND_IN_BACKGROUND_AND_GET_PID = self::RUN_COMMAND_IN_BACKGROUND . '& echo $!';
    const COMMAND_NAME = 'krlove:service:call';

    /**
     * @var string
     */
    protected $consolePath;

    /**
     * @var string
     */
    protected $phpPath;

    /**
     * AsyncService constructor.
     * @param string $consolePath
     * @param string $phpPath
     */
    public function __construct(
        string $consolePath,
        string $phpPath
    ) {
        $this->consolePath = $consolePath;
        $this->phpPath = $phpPath;
    }

    /**
     * Method that constructs the command with the service to be called.
     *
     * @param string $service
     * @param string $method
     * @param array  $arguments
     * @return int
     */
    public function call(
        string $service,
        string $method,
        array  $arguments = []
    ) : int {
        $commandline = $this->createCommandString(
            $service,
            $method,
            $arguments
        );

        return $this->runProcess($commandline);
    }

    /**
     * @param array $arguments
     * @return string
     */
    public function escapeShellArguments(array $arguments) : string
    {
        return escapeshellarg(base64_encode(serialize($arguments)));
    }

    /**
     * Creates the command string to be executed in background.
     *
     * @param string $service
     * @param string $method
     * @param array $arguments
     * @return string
     */
    protected function createCommandString(
        string $service,
        string $method,
        array  $arguments
    ) : string {
        return sprintf(
            '%s %s ' . self::COMMAND_NAME . ' %s %s --args=%s > ' . self::RUN_COMMAND_IN_BACKGROUND_AND_GET_PID,
            $this->phpPath,
            $this->consolePath,
            $service,
            $method,
            $this->escapeShellArguments($arguments)
        );
    }

    /**
     * Executes the command that calls the service.
     * It will return the PID.
     *
     * @param string $commandline
     * @return int
     */
    protected function runProcess(string $commandline) : int
    {
        exec($commandline, $op);

        if (!\is_array($op)) {
            return 0;
        }

        return (int) $op[0];
    }

    /**
     * It will instantiate a process component in order
     * to call service asynchronously allowing to wait for response.
     *
     * See more on https://symfony.com/doc/current/components/process.html
     *
     * @param string $service
     * @param string $method
     * @param array $arguments
     * @return Process
     */
    public function getProcessInstance(
        string $service,
        string $method,
        array  $arguments
    ) : Process {
        $command = [
            $this->consolePath,
            self::COMMAND_NAME,
            $service,
            $method,
            '--args=' . $this->escapeShellArguments($arguments)
        ];

        return new Process($command);
    }
}
