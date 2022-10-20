<?php

namespace Headless\StripeGraphQl\Plugin;

use StripeIntegration\Payments\Helper\Webhooks;

class BeforeGetOrderId
{
    /**
     * Force lookup of PaymentIntent by Transaction ID
     */
    public function beforeGetOrderIdFromObject(Webhooks $subject, array $object, $includeMultishipping = false)
    {
        if (!empty($object['payment_intent'])) {
            $includeMultishipping = true;
            return [$object, $includeMultishipping];
        }

        return null;
    }
}



