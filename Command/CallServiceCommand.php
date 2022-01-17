<?php

namespace Krlove\AsyncServiceCallBundle\Command;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CallServiceCommand
 * @package Krlove\AsyncServiceCallBundle\Command
 */
class CallServiceCommand extends Command implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    protected $container;

    /** @var LoggerInterface */
    protected $logger;

    /** @var string */
    protected $serviceId;

    /** @var string */
    protected $serviceMethod;

    /** @var array|string */
    protected $serviceArgs;

    /** @var string|null */
    protected $id;

    /**
     * CallServiceCommand constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger    = $logger;
        $this->id        = uniqid();

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('krlove:service:call')
            ->setDescription('Calls a service method with arguments')
            ->addArgument('service', InputArgument::REQUIRED, 'Service ID')
            ->addArgument('method', InputArgument::REQUIRED, 'Method to call on the service')
            ->addOption('args', null, InputOption::VALUE_OPTIONAL, 'Arguments to supply to the method');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) : ?int
    {
        $result = null;
        $elapsedTime = null;

        try {
            $this->serviceId     = $input->getArgument('service');
            $this->serviceMethod = $input->getArgument('method');
            $args                = $input->getOption('args');

            if ($args !== null) {
                $this->serviceArgs = unserialize(base64_decode($args));
            }

            $service = $this->container->get($this->serviceId);

            $this->log(Logger::INFO);

            if (\is_string($this->serviceArgs) && \is_array(json_decode($this->serviceArgs, true))) {
                $this->serviceArgs = json_decode($this->serviceArgs);
            }

            if (!method_exists($service, $this->serviceMethod)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Method %s doesn\'t exist on class %s',
                        $this->serviceMethod,
                        get_class($service)
                    )
                );
            }

            $start = microtime(true);

            if ($this->serviceArgs) {
                $result = call_user_func_array([$service, $this->serviceMethod], $this->serviceArgs);
            } else {
                $result = call_user_func([$service, $this->serviceMethod]);
            }

            if (\is_array($result) || \is_object($result)) {
                $result = json_encode($result);
            }

            $elapsedTime = microtime(true) - $start;
        } catch (\Exception $e) {
            $this->log(Logger::ERROR, $e->getMessage());

            $output->writeln($result);

            return -1;
        }

        $this->log(
            Logger::INFO,
            [
                'result'         => $result,
                'execution time' => $elapsedTime
            ]
        );

        $output->writeln($result);

        return 0;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null) : void
    {
        $this->container = $container;
    }

    /**
     * @param string $type
     * @param mixed|null $value
     */
    public function log(string $type, $value = null)
    {
        if ($value === null) {
            $value = [];
        }

        $array = [
            'id'        => $this->id,
            'serviceId' => $this->serviceId,
            'method'    => $this->serviceMethod,
            'arguments' => $this->serviceArgs
        ];

        switch ($type) {
            case Logger::INFO:
                $this->logger->info(
                    "{$this->getName()}",
                    array_merge($array, $value)
                );
                break;

            case Logger::ERROR:
                $this->logger->error(
                    "{$this->getName()}",
                    array_merge($array, ['error' => $value])
                );
        }
    }

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

}