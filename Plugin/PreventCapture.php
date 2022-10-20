<?php

namespace Headless\StripeGraphQl\Plugin;

use StripeIntegration\Payments\Model\PaymentMethod;
use Magento\Payment\Model\InfoInterface;

class PreventCapture
{
    /**
     * Prevent capture of payment already captured
     */
    public function aroundCapture(PaymentMethod $subject, callable $proceed, InfoInterface $payment, $amount)
    {
        // we assume this will not be present for GraphQL orders
        if ($payment->getAdditionalInformation('payment_location')) {
            return $proceed($payment, $amount);
        }

        return null;
    }
}
