<?php

namespace Tests\Feature;

use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Concert;
use App\Facades\OrderConfirmationNumber;
use App\Facades\TicketCode;
use App\Mail\OrderConfirmationEmail;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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
        $this->assertArrayHasKey($field, $this->decodeResponseJson()['errors']);
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
    public function customerCanPurchaseTicketsToAPublishedConcert()
    {
        $this->withoutExceptionHandling();

        OrderConfirmationNumber::shouldReceive('generate')->andReturn('ORDERCONFIRMATION1234');
        TicketCode::shouldReceive('generateFor')->andReturn('TICKETCODE1', 'TICKETCODE2', 'TICKETCODE3');

        $user = factory(User::class)->create([
            'stripe_account_id' => 'test_acct_1234',
        ]);

        $concert = \ConcertFactory::createPublished([
            'ticket_price' => 3999,
            'ticket_quantity' => 3,
            'user_id' => $user,
        ]);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertResponseStatus(201);

        $this->seeJsonSubset([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'email' => 'larry@thenycgolfer.com',
            'amount' => 11997,
            'tickets' => [
                ['code' => 'TICKETCODE1'],
                ['code' => 'TICKETCODE2'],
                ['code' => 'TICKETCODE3'],
            ],
        ]);

        $order = $concert->ordersFor('larry@thenycgolfer.com')->first();

        $this->assertEquals(11997, $this->paymentGateway->totalChargesFor('test_acct_1234'));
        $this->assertTrue($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(3, $order->ticketQuantity());

        Mail::assertSent(OrderConfirmationEmail::class, function ($mail) use ($order) {
            return $mail->hasTo('larry@thenycgolfer.com')
            && $mail->order->id === $order->id;
        });
    }

    /**
     * @test
     */
    public function emailIsRequiredToPurchaseTickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('email');
    }

    /**
     * @test
     */
    public function emailMustBeValidToPurchaseTickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'not-an-email-address',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('email');
    }

    /**
     * @test
     */
    public function ticketQuantityIsRequiredToPurchaseTickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /**
     * @test
     */
    public function ticketQuantityMustBeAtLeast1ToPurchaseTickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'ticket_quantity' => 0,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /**
     * @test
     */
    public function paymentTokenIsRequiredToPurchaseTickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'jon@example.com',
            'ticket_quantity' => 3,
        ]);

        $this->assertValidationError('payment_token');
    }

    /**
     * @test
     */
    public function anOrderIsNotCreatedIfPaymentFails()
    {
        $this->withoutExceptionHandling();

        $concert = factory(Concert::class)->states('published')->create([
            'ticket_price' => 3250,
        ])->addTickets(3);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => 'invalid-payment-token',
        ]);

        $this->assertResponseStatus(422);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(3, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function cannotPurchaseTicketsToUnpublishedConcert()
    {
        $concert = factory(Concert::class)->states('unpublished')->create()->addTickets(3);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertResponseStatus(404);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
    }

    /**
     * @test
     */
    public function cannotPurchaseMoreTicketsThanRemain()
    {
        $concert = factory(Concert::class)->states('published')->create()->addTickets(50);

        $this->orderTickets($concert, [
            'email' => 'larry@thenycgolfer.com',
            'ticket_quantity' => 51,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertResponseStatus(422);
        $this->assertFalse($concert->hasOrderFor('larry@thenycgolfer.com'));
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
        $this->assertEquals(50, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function cannotPurchaseTicketsAnotherCustomerIsAlreadyTryingToPurchase()
    {
        $this->withoutExceptionHandling();

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 1200])->addTickets(3);

        $this->paymentGateway->beforeFirstCharge(function ($paymentGateway) use ($concert) {

            $this->orderTickets($concert, [
                'email' => 'personB@thenycgolfer.com',
                'ticket_quantity' => 1,
                'payment_token' => $this->paymentGateway->getValidTestToken(),
            ]);

            $this->assertResponseStatus(422);
            $this->assertFalse($concert->hasOrderFor('personB@thenycgolfer.com'));
            $this->assertEquals(0, $this->paymentGateway->totalCharges());
        });

        $this->orderTickets($concert, [
            'email' => 'personA@thenycgolfer.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertEquals(3600, $this->paymentGateway->totalCharges());
        $this->assertTrue($concert->hasOrderFor('personA@thenycgolfer.com'));
        $this->assertEquals(3, $concert->ordersFor('personA@thenycgolfer.com')->first()->ticketQuantity());
    }
}
