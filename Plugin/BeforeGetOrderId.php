<?php

namespace Headless\StripeGraphQl\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use StripeIntegration\Payments\Helper\Webhooks;

class BeforeGetOrderId
{
    public function __construct(
        private readonly CollectionFactory $salesOrderCollectionFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteManagement $quoteManagement,
        private readonly OrderSender $orderSender,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Try and convert Cart ID to Order ID.
     * If no order exists for a paid charge, convert the active quote to an order.
     */
    public function beforeGetOrderIdFromObject(Webhooks $subject, array $object, $includeMultishipping = false)
    {
        if (!empty($object['description']) && preg_match('/Cart\s([0-9]*)\s/', $object['description'], $matches)) {
            $quoteId = $matches[1];

            $order = $this->salesOrderCollectionFactory->create()
                ->addFieldToFilter('quote_id', $quoteId)
                ->getFirstItem();

            if ($order && $order->getId()) {
                $object['metadata']['Order #'] = $order->getIncrementId();
                return [$object, $includeMultishipping];
            }

            // No order found — if the charge is paid, attempt to convert the quote to an order
            if (!empty($object['paid']) && $object['object'] == 'charge' && $object['status'] == 'succeeded') {
                $newOrder = $this->convertQuoteToOrder($quoteId);
                if ($newOrder) {
                    $object['metadata']['Order #'] = $newOrder->getIncrementId();
                    return [$object, $includeMultishipping];
                }
            }
        }

        return null;
    }

    /**
     * Load an active quote by ID and submit it as a Magento order.
     *
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function convertQuoteToOrder(string $quoteId)
    {
        try {
            $quote = $this->cartRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->warning(
                sprintf('Stripe webhook: Quote %s not found: %s', $quoteId, $e->getMessage())
            );
            return null;
        }

        if (!$quote->getIsActive()) {
            $this->logger->info(
                sprintf('Stripe webhook: Quote %s is not active, cannot convert to order.', $quoteId)
            );
            return null;
        }

        $this->logger->info(
            sprintf('Stripe webhook: Converting active quote %s to order (paid charge detected).', $quoteId)
        );

        try {
            $quote->collectTotals();

            /** @var \Magento\Sales\Api\Data\OrderInterface $newOrder */
            $newOrder = $this->quoteManagement->submit($quote);

            if (!$newOrder || !$newOrder->getEntityId()) {
                $this->logger->error(
                    sprintf('Stripe webhook: QuoteManagement::submit() returned no order for quote %s.', $quoteId)
                );
                return null;
            }

            $this->sendOrderEmail($newOrder);

            $this->logger->info(
                sprintf(
                    'Stripe webhook: Successfully created order %s from quote %s.',
                    $newOrder->getIncrementId(),
                    $quoteId
                )
            );

            return $newOrder;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Stripe webhook: Failed to convert quote %s to order: %s',
                    $quoteId,
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Send the new order confirmation email and add a status history comment.
     */
    private function sendOrderEmail(\Magento\Sales\Api\Data\OrderInterface $order): void
    {
        $baseComment = 'Order created from quote by Stripe webhook (paid charge with no matching order).';

        try {
            $this->orderSender->send($order);
            $order->addCommentToStatusHistory($baseComment . ' Confirmation email sent.')
                ->setIsCustomerNotified(true);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Stripe webhook: Failed to send order email for order %s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                )
            );
            $order->addCommentToStatusHistory($baseComment . ' Failed to send confirmation email.');
        }

        $order->save();
    }
}
