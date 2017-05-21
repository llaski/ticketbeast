<?php

namespace Tests\Feature;

use App\Concert;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ViewConcertListingTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    function user_can_view_a_published_concert_listing()
    {
        $concert = factory(Concert::class)->states('published')->create([
            'title' => 'The White Lies',
            'subtitle' => 'New Album Tour',
            'date' => Carbon::parse('January 10th, 2017 8:00pm'),
            'ticket_price' => 3250,
            'venue' => 'Webster Hall',
            'venue_address' => '19 9th Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
            'additional_information' => 'For tickets, call (555) 555-5555'
        ]);

        $response = $this->get('/concerts/' . $concert->id);

        $response->assertStatus(200);
        $response->assertSee('The White Lies');
        $response->assertSee('New Album Tour');
        $response->assertSee('January 10th, 2017');
        $response->assertSee('8:00pm');
        $response->assertSee('$32.50');
        $response->assertSee('Webster Hall');
        $response->assertSee('19 9th Street');
        $response->assertSee('New York, NY 10001');
        $response->assertSee('For tickets, call (555) 555-5555');
    }

    /**
     * @test
     */
    function user_cannot_view_unpublished_concert_listings()
    {
        $concert = factory(Concert::class)->states('unpublished')->create();

        $response = $this->get('/concerts/' . $concert->id);

        $response->assertStatus(404);
    }
}
