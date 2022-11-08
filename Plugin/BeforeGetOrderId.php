<?php

namespace Headless\StripeGraphQl\Plugin;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use StripeIntegration\Payments\Helper\Webhooks;

class BeforeGetOrderId
{
    protected $salesOrderCollectionFactory;

    public function __construct(CollectionFactory $salesOrderCollectionFactory)
    {
        $this->salesOrderCollectionFactory = $salesOrderCollectionFactory;
    }

    /**
     * Try and convert Cart ID to Order ID
     */
    public function beforeGetOrderIdFromObject(Webhooks $subject, array $object, $includeMultishipping = false)
    {
        if (!empty($object['description']) && preg_match('/Cart\s([0-9]*)\s/', $object['description'], $matches)) {
            if ($order = $this->salesOrderCollectionFactory->create()->addFieldToFilter('quote_id', $matches[1])->getFirstItem()) {
                $object['metadata']['Order #'] = $order->getIncrementId();
                return [$object, $includeMultishipping];
            }
        }

        return null;
    }
}
