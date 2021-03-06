<?php

namespace Oro\Bundle\IntegrationBundle\Provider;

use Psr\Log\LoggerAwareInterface;

use Symfony\Component\HttpFoundation\ParameterBag;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Reader\IteratorBasedReader;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Logger\LoggerStrategy;
use Oro\Bundle\BatchBundle\Step\StepExecutionAwareInterface;

abstract class AbstractConnector extends IteratorBasedReader implements ConnectorInterface, StepExecutionAwareInterface
{
    /** @var TransportInterface */
    protected $transport;

    /** @var Channel */
    protected $channel;

    /** @var ParameterBag */
    protected $transportSettings;

    /** @var LoggerStrategy */
    protected $logger;

    /** @var ConnectorContextMediator */
    protected $contextMediator;

    /**
     * @param ContextRegistry          $contextRegistry
     * @param LoggerStrategy           $logger
     * @param ConnectorContextMediator $contextMediator
     */
    public function __construct(
        ContextRegistry $contextRegistry,
        LoggerStrategy $logger,
        ConnectorContextMediator $contextMediator
    ) {
        parent::__construct($contextRegistry);
        $this->logger          = $logger;
        $this->contextMediator = $contextMediator;
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeFromContext(ContextInterface $context)
    {
        $this->transport = $this->contextMediator->getTransport($context);
        $this->channel   = $this->contextMediator->getChannel($context);

        $this->validateConfiguration();
        $this->transport->init($this->channel->getTransport());
        $this->setSourceIterator($this->getConnectorSource());

        if ($this->getSourceIterator() instanceof LoggerAwareInterface) {
            $this->getSourceIterator()->setLogger($this->logger);
        }
    }

    /**
     * Validates initialization
     * Basically added to be overridden in child classes
     *
     * @throws \LogicException
     */
    protected function validateConfiguration()
    {
        if (!$this->transport instanceof TransportInterface) {
            throw new \LogicException('Could not retrieve transport from context');
        }
    }

    /**
     * Return source iterator to read from
     *
     * @return \Iterator
     */
    abstract protected function getConnectorSource();
}
