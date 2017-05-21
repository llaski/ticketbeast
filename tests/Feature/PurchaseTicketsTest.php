<?php

namespace Tests\Feature;

use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Concert;
use App\Facades\OrderConfirmationNumber;
use App\Facades\TicketCode;
use App\Mail\OrderConfirmationEmail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseTicketsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp()
    {
        parent::setUp();

        $this->paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->paymentGateway);

        Mail::fake();
    }

    private function orderTickets($concert, $params)
    {
        $savedRequest = $this->app['request'];

        $this->response = $this->json('POST', "/concerts/{$concert->id}/orders", $params);

        $this->app['request'] = $savedRequest;
    }

    private function assertValidationError($field)
    {
        $this->assertResponseStatus(422);
        $this->assertArrayHasKey($field, $this->decodeResponseJson());
    }

    private function assertResponseStatus($status)
    {
        $this->response->assertStatus($status);
    }

    private function seeJsonSubset($data)
    {
        $this->response->assertJson($data);
    }

    private function decodeResponseJson()
    {
        return $this->response->decodeResponseJson();
    }

    /**
     * @test
     */
    function customer_can_purchase_tickets_to_a_published_concert()
    {
        $this->disableExceptionHandling();

        OrderConfirmationNumber::shouldReceive('generate')->andReturn('ORDERCONFIRMATION1234');
        TicketCode::shouldReceive('generateFor')->andReturn('TICKETCODE1', 'TICKETCODE2', 'TICKETCODE3');

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 3999])->addTickets(3);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertResponseStatus(201);

        $this->seeJsonSubset([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'email' => 'larry@thenycgolfer.com',
            'amount' => 11997,
            'tickets' => [
                ['code' => 'TICKETCODE1'],
                ['code' => 'TICKETCODE2'],
                ['code' => 'TICKETCODE3']
            ]
        ]);

        $order = $concert->ordersFor('larry@thenycgolfer.com')->first();

        $this->assertEquals(11997, $this->paymentGateway->totalCharges());
        $this->assertTrue($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(3, $order->ticketQuantity());

        Mail::assertSent(OrderConfirmationEmail::class, function($mail) use ($order) {
            return $mail->hasTo('larry@thenycgolfer.com')
                && $mail->order->id === $order->id;
        });
    }

    /**
     * @test
     */
    function email_is_required_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertValidationError('email');
    }

    /**
     * @test
     */
    function email_must_be_valid_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'not-an-email-address',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertValidationError('email');
    }

    /**
     * @test
     */
    function ticket_quantity_is_required_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /**
     * @test
     */
    function ticket_quantity_must_be_at_least_1_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'ticket_quantity' => 0,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /**
     * @test
     */
    function payment_token_is_required_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'ticket_quantity' => 3
        ]);

        $this->assertValidationError('payment_token');
    }

    /**
     * @test
     */
    function an_order_is_not_created_if_payment_fails()
    {
        $this->disableExceptionHandling();

        $concert = factory(Concert::class)->states('published')->create([
            'ticket_price' => 3250
        ])->addTickets(3);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => 'invalid-payment-token'
        ]);

        $this->assertResponseStatus(422);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(3, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    function cannot_purchase_tickets_to_unpublished_concert()
    {
        $concert = factory(Concert::class)->states('unpublished')->create()->addTickets(3);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertResponseStatus(404);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
    }

    /**
     * @test
     */
    function cannot_purchase_more_tickets_than_remain()
    {
        $concert = factory(Concert::class)->states('published')->create()->addTickets(50);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 51,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertResponseStatus(422);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
        $this->assertEquals(50, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    function cannot_purchase_tickets_another_customer_is_already_trying_to_purchase()
    {
        $this->disableExceptionHandling();

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 1200])->addTickets(3);

        $this->paymentGateway->beforeFirstCharge(function($paymentGateway) use ($concert) {

            $this->orderTickets($concert, [
                'email' => 'personB@thenycgolfer.com',
                'ticket_quantity' => 1,
                'payment_token' => $this->paymentGateway->getValidTestToken()
            ]);

            $this->assertResponseStatus(422);
            $this->assertFalse($concert->hasOrderFor('personB@thenycgolfer.com'));
            $this->assertEquals(0, $this->paymentGateway->totalCharges());
        });

        $this->orderTickets($concert, [
            'email' => 'personA@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertEquals(3600, $this->paymentGateway->totalCharges());
        $this->assertTrue($concert->hasOrderFor('personA@thenycgolfer.com'));
        $this->assertEquals(3, $concert->ordersFor('personA@thenycgolfer.com')->first()->ticketQuantity());
    }
}