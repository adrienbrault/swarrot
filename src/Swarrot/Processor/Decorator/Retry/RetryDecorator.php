<?php

namespace Swarrot\Processor\Decorator\Retry;

use Swarrot\Processor\ProcessorInterface;
use Swarrot\Processor\ConfigurableInterface;
use Swarrot\Broker\Message;
use Psr\Log\LoggerInterface;
use Swarrot\Processor\Decorator\DecoratorInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Swarrot\Broker\MessagePublisher\MessagePublisherInterface;

class RetryDecorator implements DecoratorInterface, ConfigurableInterface
{
    protected $publisher;
    protected $logger;

    public function __construct(MessagePublisherInterface $publisher, LoggerInterface $logger = null)
    {
        $this->publisher = $publisher;
        $this->logger    = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function decorate(ProcessorInterface $processor, Message $message, array $options)
    {
        try {
            return $processor->process($message, $options);
        } catch (\Exception $e) {
            $properties = $message->getProperties();

            $attempts = 0;
            if (isset($properties['headers']['swarrot_retry_attempts'])) {
                $attempts = $properties['headers']['swarrot_retry_attempts'];
            }
            $attempts++;

            if ($attempts > $options['retry_attempts']) {
                if (null !== $this->logger) {
                    $this->logger->warning(sprintf(
                        '[Retry] Stop attempting to process message after %d attempts',
                        $attempts
                    ));
                }

                throw $e;
            }

            $properties = $message->getProperties();
            $properties['headers']['swarrot_retry_attempts'] = $attempts;
            $message = new Message($message->getBody(), $properties);

            $key = str_replace('%attempt%', $attempts, $options['retry_key_pattern']);

            if (null !== $this->logger) {
                $this->logger->warning(
                    sprintf(
                        '[Retry] An exception occurred. Republish message for the %d times (key: %s)',
                        $attempts,
                        $key
                    ),
                    array(
                        'exception' => $e,
                    )
                );
            }

            $this->publisher->publish($message, $key);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults(array(
                'retry_attempts' => 3,
            ))
            ->setRequired(array(
                'retry_key_pattern'
            ))
            ->setAllowedTypes(array(
                'retry_attempts' => 'integer',
                'retry_key_pattern' => 'string',
            ))
        ;
    }
}
