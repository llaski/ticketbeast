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
    function user_can_view_a_concert_listing()
    {
        //Arrange
        //Create a concert
        $concert = Concert::create([
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

        //Act
        //View concert listing
        $this->visit('/concerts/' . $concert->id);

        //Assert
        //Verify we can see concert details†
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
}
