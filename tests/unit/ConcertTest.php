<?php

namespace Tests\Unit;

use App\Concert;
use App\Exceptions\NotEnoughTicketsException;
use App\Order;
use App\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ConcertTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    public function canGetFormattedDate()
    {
        $concert = factory(Concert::class)->make([
            'date' => Carbon::parse('2017-01-10 8:00pm'),
        ]);

        $this->assertEquals('January 10th, 2017', $concert->formatted_date);
    }

    /**
     * @test
     */
    public function canGetFormattedStartTime()
    {
        $concert = factory(Concert::class)->make([
            'date' => Carbon::parse('2017-01-10 17:00:00'),
        ]);

        $this->assertEquals('5:00pm', $concert->formatted_start_time);
    }

    /**
     * @test
     */
    public function canGetTicketPriceInDollars()
    {
        $concert = factory(Concert::class)->make([
            'ticket_price' => 4250,
        ]);

        $this->assertEquals('$42.50', $concert->ticket_price_in_dollars);
    }

    /**
     * @test
     */
    public function concertsWithAPublishedAtDateArePublished()
    {
        $publishedConcertA = factory(Concert::class)->create([
            'published_at' => Carbon::parse('-1 week'),
        ]);

        $publishedConcertB = factory(Concert::class)->create([
            'published_at' => Carbon::parse('-2 week'),
        ]);

        $publishedConcertC = factory(Concert::class)->create([
            'published_at' => null,
        ]);

        $publishedConcerts = Concert::published()->get();

        $this->assertTrue($publishedConcerts->contains($publishedConcertA));
        $this->assertTrue($publishedConcerts->contains($publishedConcertB));
        $this->assertFalse($publishedConcerts->contains($publishedConcertC));
    }

    /** @test */
    public function concertsCanBePublished()
    {
        $concert = factory(Concert::class)->create([
            'published_at' => null,
            'ticket_quantity' => 5,
        ]);

        $this->assertFalse($concert->isPublished());
        $this->assertEquals(0, $concert->ticketsRemaining());

        $concert->publish();

        $this->assertTrue($concert->isPublished());
        $this->assertEquals(5, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function canAddTickets()
    {
        $concert = factory(Concert::class)->create();

        $concert->addTickets(50);

        $this->assertEquals(50, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function ticketsRemainingDoesNotIncludeTicketsAssociatedWithAnOrder()
    {
        $concert = factory(Concert::class)->create();

        $concert->tickets()->saveMany(factory(Ticket::class, 3)->create(['order_id' => 1]));
        $concert->tickets()->saveMany(factory(Ticket::class, 2)->create(['order_id' => null]));

        $this->assertEquals(2, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function ticketsSoldOnlyIncludesTicketsAssociatedWithAnOrder()
    {
        $concert = factory(Concert::class)->create();

        $concert->tickets()->saveMany(factory(Ticket::class, 3)->create(['order_id' => 1]));
        $concert->tickets()->saveMany(factory(Ticket::class, 2)->create(['order_id' => null]));

        $this->assertEquals(3, $concert->ticketsSold());
    }

    /**
     * @test
     */
    public function totalTicketsIncludesAllTickets()
    {
        $concert = factory(Concert::class)->create();

        $concert->tickets()->saveMany(factory(Ticket::class, 3)->create(['order_id' => 1]));
        $concert->tickets()->saveMany(factory(Ticket::class, 2)->create(['order_id' => null]));

        $this->assertEquals(5, $concert->totalTickets());
    }

    /**
     * @test
     */
    public function calculateThePercentageOfTicketsSold()
    {
        $concert = factory(Concert::class)->create();

        $concert->tickets()->saveMany(factory(Ticket::class, 2)->create(['order_id' => 1]));
        $concert->tickets()->saveMany(factory(Ticket::class, 5)->create(['order_id' => null]));

        $this->assertEquals(28.57, $concert->percentSoldOut());
    }

    /** @test */
    public function calculatingTheRevenueInDollars()
    {
        $concert = factory(Concert::class)->create();
        $orderA = factory(Order::class)->create(['amount' => 3850]);
        $orderB = factory(Order::class)->create(['amount' => 9625]);

        $concert->tickets()->saveMany(factory(Ticket::class, 2)->create(['order_id' => $orderA->id]));
        $concert->tickets()->saveMany(factory(Ticket::class, 5)->create(['order_id' => $orderB->id]));

        $this->assertEquals(134.75, $concert->revenueInDollars());
    }

    /**
     * @test
     */
    public function tryingToReserveMoreTicketsThanRemainThrowsAnException()
    {
        $concert = factory(Concert::class)->create()->addTickets(10);

        try {
            $reservation = $concert->reserveTickets(11, 'jane@example.com');
        } catch (NotEnoughTicketsException $e) {
            $this->assertFalse($concert->hasOrderFor('jane@example.com'));
            $this->assertEquals(10, $concert->ticketsRemaining());
            return;
        }

        $this->fail('Order succeeded even though there were not enough tickets.');
    }

    /**
     * @test
     */
    public function canReserveAvailableTickets()
    {
        $concert = factory(Concert::class)->create()->addTickets(5);
        $this->assertEquals(5, $concert->ticketsRemaining());

        $reservation = $concert->reserveTickets(3, 'john@example.com');

        $this->assertCount(3, $reservation->tickets());
        $this->assertEquals('john@example.com', $reservation->email());
        $this->assertEquals(2, $concert->ticketsRemaining());
    }

    /**
     * @test
     */
    public function cannotReserveTicketsThatHaveAlreadyBeenPurchased()
    {
        $concert = factory(Concert::class)->create()->addTickets(3);
        $order = factory(Order::class)->create();
        $order->tickets()->saveMany($concert->tickets->take(2));

        try {
            $concert->reserveTickets(2, 'jane@example.com');
        } catch (NotEnoughTicketsException $e) {
            $this->assertEquals(1, $concert->ticketsRemaining());
            return;
        }

        $this->fail('Reserving tickets succeeded even though tickets were already sold.');
    }

    /**
     * @test
     */
    public function cannotReserveTicketsThatHaveAlreadyBeenReserved()
    {
        $concert = factory(Concert::class)->create()->addTickets(3);
        $concert->reserveTickets(2, 'jack@example.com');

        try {
            $concert->reserveTickets(2, 'jack@example.com');
        } catch (NotEnoughTicketsException $e) {
            $this->assertEquals(1, $concert->ticketsRemaining());
            return;
        }

        $this->fail('Reserving tickets succeeded even though tickets were already reserved.');
    }
}
