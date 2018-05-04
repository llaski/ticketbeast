<?php

namespace Tests\Feature;

use App\Concert;
use App\Order;
use App\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ViewOrderTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    public function user_can_view_their_order_confirmation()
    {
        $this->withoutExceptionHandling();

        //Setup data
        $concert = factory(Concert::class)->create([
            'date' => Carbon::parse('2017-02-02 20:00:00')
        ]);

        $order = factory(Order::class)->create([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'card_last_four' => '1881',
            'amount' => 8500
        ]);
        $ticketA = factory(Ticket::class)->create([
            'concert_id' => $concert->id,
            'order_id' => $order->id,
            'code' => 'TICKETCODE123'
        ]);
        $ticketB = factory(Ticket::class)->create([
            'concert_id' => $concert->id,
            'order_id' => $order->id,
            'code' => 'TICKETCODE456'
        ]);

        //Visit order confirmation page
        $response = $this->get("/orders/ORDERCONFIRMATION1234");

        $response->assertStatus(200);
        $response->assertViewHas('order', function($viewOrder) use ($order) {
            return $viewOrder->id === $order->id;
        });
        $response->assertSee('ORDERCONFIRMATION1234');
        $response->assertSee('$85.00');
        $response->assertSee('**** **** **** 1881');
        $response->assertSee('TICKETCODE123');
        $response->assertSee('TICKETCODE456');

        $response->assertSee('2017-02-02 20:00');
    }
}