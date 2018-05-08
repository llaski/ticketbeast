<?php

namespace Tests\Unit\Billing;

use App\Billing\StripePaymentGateway;
use Tests\TestCase;

/**
 * @group integration
 */
class StripePaymentGatewayTest extends TestCase
{
    use PaymentGatewayContractTests;

    protected function getPaymentGateway()
    {
        return new StripePaymentGateway(config('services.stripe.secret'));
    }

    /** @test */
    public function ninetyPercentOfPaymentIsTransferredToDestinationAccount()
    {
        $paymentGateway = new StripePaymentGateway(config('services.stripe.secret'));

        $paymentGateway->charge(5000, $paymentGateway->getValidTestToken(), env('STRIPE_TEST_PROMOTER_ID'));

        $lastStripeCharge = \Stripe\Charge::all(
            ['limit' => 1],
            ['api_key' => config('services.stripe.secret')]
        )['data'][0];

        $this->assertEquals(5000, $lastStripeCharge['amount']);
        $this->assertEquals(env('STRIPE_TEST_PROMOTER_ID'), $lastStripeCharge['destination']);

        $transfer = \Stripe\Transfer::retrieve($lastStripeCharge['transfer'], ['api_key' => config('services.stripe.secret')]);

        $this->assertEquals(4500, $transfer['amount']);
    }

}
