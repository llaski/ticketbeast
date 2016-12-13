<?php

use App\Concert;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

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

        $this->visit('/concerts/' . $concert->id);

        $this->see('The White Lies');
        $this->see('New Album Tour');
        $this->see('January 10th, 2017');
        $this->see('8:00pm');
        $this->see('$32.50');
        $this->see('Webster Hall');
        $this->see('19 9th Street');
        $this->see('New York, NY 10001');
        $this->see('For tickets, call (555) 555-5555');
    }

    /**
     * @test
     */
    function user_cannot_view_unpublished_concert_listings()
    {
        $concert = factory(Concert::class)->states('unpublished')->create();

        $this->get('/concerts/' . $concert->id);

        $this->assertResponseStatus(404);
    }
}
