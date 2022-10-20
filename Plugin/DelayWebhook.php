<?php

namespace Headless\StripeGraphQl\Plugin;

use StripeIntegration\Payments\Observer\WebhooksObserver;
use Magento\Framework\Event\Observer;

class DelayWebhook
{
    /**
     * Delay processing of charge.succeeded event
     */
    public function beforeExecute(WebhooksObserver $subject, Observer $observer)
    {
        $eventName = $observer->getEvent()->getName();

        if ($eventName == 'stripe_payments_webhook_charge_succeeded') {
            sleep(10);
        }

        return null;
    }
}
