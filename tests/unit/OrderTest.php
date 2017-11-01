<?php

namespace Tests\Unit;

use App\Billing\Charge;
use App\Order;
use App\Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Mockery;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    public function creatingAnOrderFromTicketsEmailAndAmountAndCharge()
    {
        $charge = new Charge([
            'amount' => 3600,
            'card_last_four' => '1234',
        ]);

        $tickets = collect([
            Mockery::spy(Ticket::class),
            Mockery::spy(Ticket::class),
            Mockery::spy(Ticket::class),
        ]);

        $order = Order::forTickets($tickets, 'john@example.com', $charge);

        $this->assertEquals('john@example.com', $order->email);
        $this->assertEquals(3600, $order->amount);
        $this->assertEquals('1234', $order->card_last_four);
        $tickets->each->shouldHaveReceived('claimFor', [$order]);

    }

    /**
     * @test
     */
    public function retrievingAnOrderByConfirmationNumber()
    {
        $order = factory(Order::class)->create([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
        ]);

        $foundOrder = Order::findByConfirmationNumber('ORDERCONFIRMATION1234');

        $this->assertEquals($order->id, $foundOrder->id);
    }

    /**
     * @test
     */
    public function retrievingANonexistentOrderByConfirmationNumberThrowsAnException()
    {
        $this->expectException(ModelNotFoundException::class);
        Order::findByConfirmationNumber('NONEXISTENTCONFIRMATION1234');
    }

    /**
     * @test
     */
    public function convertingToAnArray()
    {
        $order = factory(Order::class)->create([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'email' => 'jane@example.com',
            'amount' => 6000,
        ]);

        $order->tickets()->saveMany([
            factory(Ticket::class)->create(['code' => 'TICKET1']),
            factory(Ticket::class)->create(['code' => 'TICKET2']),
            factory(Ticket::class)->create(['code' => 'TICKET3']),
        ]);

        $result = $order->toArray();

        $this->assertEquals([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'email' => 'jane@example.com',
            'ticket_quantity' => 3,
            'amount' => 6000,
            'tickets' => [
                ['code' => 'TICKET1'],
                ['code' => 'TICKET2'],
                ['code' => 'TICKET3'],
            ],
        ], $result);
    }
}
