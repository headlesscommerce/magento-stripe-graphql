<?php

namespace Headless\StripeGraphQl\Plugin;

use StripeIntegration\Payments\Model\PaymentIntentFactory;
use StripeIntegration\Payments\Model\PaymentMethod;
use Magento\Payment\Model\InfoInterface;

class PreventCapture
{
    public function __construct(PaymentIntentFactory $paymentIntentFactory)
    {
        $this->paymentIntentFactory = $paymentIntentFactory;
    }

    /**
     * Prevent capture of payment already captured
     */
    public function aroundCapture(PaymentMethod $subject, callable $proceed, InfoInterface $payment, $amount)
    {
        // Only prevent capture if not an admin order
        if ($payment->getAdditionalInformation('payment_location')) {
            return $proceed($payment, $amount);
        }

        // We reverse calculate from the Quote ID to update the payment intents table
        // with the order increment ID so that webhooks work
        if (!$payment->getOrder() || !$payment->getOrder()->getQuoteId()) {
            return $proceed($payment, $amount);
        }

        $order = $payment->getOrder();

        $paymentIntentModel = $this->paymentIntentFactory->create()->load($order->getQuoteId(), 'quote_id');
        $paymentIntentModel->setPmId($payment->getAdditionalInformation('token'));
        $paymentIntentModel->setOrderId($order->getId());
        $paymentIntentModel->setOrderIncrementId($order->getIncrementId());
        $paymentIntentModel->setCustomerId($order->getCustomerId());
        $paymentIntentModel->save();

        return null;
    }
}
