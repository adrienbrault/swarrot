<?php

namespace Swarrot\Processor\SignalHandler;

use Swarrot\Broker\Message;
use Swarrot\Processor\ProcessorInterface;
use Swarrot\Processor\ConfigurableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SignalHandlerProcessor implements ConfigurableInterface
{
    /**
     * @var boolean
     */
    protected static $shouldExit = false;

    /**
     * @var ProcessorInterface
     */
    protected $processor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ProcessorInterface $processor Processor
     * @param LoggerInterface    $logger    Logger
     */
    public function __construct(ProcessorInterface $processor, LoggerInterface $logger = null)
    {
        $this->processor = $processor;
        $this->logger    = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'signal_handler_signals' => array(SIGTERM, SIGINT, SIGQUIT)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function process(Message $message, array $options)
    {
        if (!extension_loaded('pcntl')) {
            if (null !== $this->logger) {
                $this->logger->warning(
                    '[SignalHandler] The SignalHandlerProcessor needs the pcntl extension to work'
                );
            }

            return $this->processor->process($message, $options);
        }

        $signals = isset($options['signal_handler_signals']) ? $options['signal_handler_signals'] : array();
        foreach ($signals as $signal) {
            pcntl_signal($signal, function () {
                SignalHandlerProcessor::$shouldExit = true;
            });
        }

        $return = $this->processor->process($message, $options);

        if ($this->shouldStop()) {
            return false;
        }

        return $return;
    }

    /**
     * shouldStop
     *
     * @return boolean
     */
    protected function shouldStop()
    {
        pcntl_signal_dispatch();

        $signals = isset($options['signal_handler_signals']) ? $options['signal_handler_signals'] : array();
        foreach ($signals as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }

        if ($this::$shouldExit) {
            if (null !== $this->logger) {
                $this->logger->info('[SignalHandler] Signal received. Stop consumer now.');
            }

            return true;
        }

        return false;
    }
}
