<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Assignment\OrderStatusUpdateCli\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\State as AppState;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Psr\Log\LoggerInterface;

/**
 * Class UpdateOrderStatus
 * A CLI command to update the status of a specific order.
 */
class UpdateOrderStatus extends Command
{
    /**
     * Argument constants
     */
    private const ORDER_ID_ARGUMENT = 'order_id';
    private const NEW_STATUS_ARGUMENT = 'new_status';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AppState
     */
    private $appState;

    /**
     * @var OrderConfig
     */
    private $orderConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * UpdateOrderStatus constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderConfig $orderConfig
     * @param AppState $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderConfig $orderConfig,
        AppState $appState,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderConfig = $orderConfig;
        $this->appState = $appState;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * Configures the command, its arguments, and description.
     */
    protected function configure()
    {
        $this->setName('sales:order:status')
            ->setDescription('Updates the status for a given order ID.')
            ->addArgument(
                self::ORDER_ID_ARGUMENT,
                InputArgument::REQUIRED,
                'The ID of the order to update.'
            )
            ->addArgument(
                self::NEW_STATUS_ARGUMENT,
                InputArgument::REQUIRED,
                'The new status for the order (e.g., "processing", "complete").'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     * Executes the command logic.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We need to set the area code to 'adminhtml' to perform admin-level actions.
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Exception $e) {
            // Area code may already be set, which is fine.
        }

        $orderId = $input->getArgument(self::ORDER_ID_ARGUMENT);
        $newStatus = $input->getArgument(self::NEW_STATUS_ARGUMENT);

        // --- Validation Step 1: Check if the provided status is valid in Magento ---
        $validStatuses = $this->orderConfig->getStatuses();
        if (!isset($validStatuses[$newStatus])) {
            $output->writeln("<error>Error: Invalid status '{$newStatus}'.</error>");
            $output->writeln('Available statuses are: ' . implode(', ', array_keys($validStatuses)));
            return Cli::RETURN_FAILURE; // Correctly indicate failure
        }

        try {
            // --- Load the Order ---
            $order = $this->orderRepository->get($orderId);

            // --- Update the status and add a history comment ---
            $order->setStatus($newStatus);

            // --- Save the Order ---
            $this->orderRepository->save($order);

            $output->writeln("<info>Success: Order ID {$orderId} status has been updated to '{$newStatus}'.</info>");
            return Cli::RETURN_SUCCESS; // Indicate success

        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $output->writeln("<error>Error: Order with ID '{$orderId}' not found.</error>");
            return Cli::RETURN_FAILURE;
        } catch (\Exception $e) {
            $output->writeln("<error>An unexpected error occurred: {$e->getMessage()}</error>");
            $this->logger->critical($e);
            return Cli::RETURN_FAILURE;
        }
    }
}

