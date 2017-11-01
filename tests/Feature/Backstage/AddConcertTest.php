<?php

namespace Tests\Feature\Backstage;

use App\Concert;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AddConcertTest extends TestCase
{
    use DatabaseMigrations;

    private function validParams($overrides = [])
    {
        return array_merge([
            'title' => 'No Warning',
            'subtitle' => 'with Cruel Hand and Backtrack',
            'additional_information' => "You must be 19 years of age to attend this concert.",
            'date' => '2017-11-18',
            'time' => '8:00pm',
            'venue' => 'The Mosh Pit',
            'venue_address' => '123 Fake St.',
            'city' => 'Laraville',
            'state' => 'ON',
            'zip' => '12345',
            'ticket_price' => '32.50',
            'ticket_quantity' => '75',
        ], $overrides);
    }

    /**
     * @test
     */
    public function promotersCanViewTheAddConcertForm()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get('/backstage/concerts/new');

        $response->assertStatus(200);
    }

    /**
     * @test
     */
    public function guestsCannotViewTheAddConcertForm()
    {
        $response = $this->get('/backstage/concerts/new');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /** @test */
    public function addingAValidConcert()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->post('/backstage/concerts', [
            'title' => 'No Warning',
            'subtitle' => 'with Cruel Hand and Backtrack',
            'additional_information' => "You must be 19 years of age to attend this concert.",
            'date' => '2017-11-18',
            'time' => '8:00pm',
            'venue' => 'The Mosh Pit',
            'venue_address' => '123 Fake St.',
            'city' => 'Laraville',
            'state' => 'ON',
            'zip' => '12345',
            'ticket_price' => '32.50',
            'ticket_quantity' => '75',
        ]);

        tap(Concert::first(), function ($concert) use ($response, $user) {
            $response->assertStatus(302);
            $response->assertRedirect('/backstage/concerts');

            $this->assertTrue($concert->user->is($user));
            $this->assertFalse($concert->isPublished());
            $this->assertEquals('No Warning', $concert->title);
            $this->assertEquals('with Cruel Hand and Backtrack', $concert->subtitle);
            $this->assertEquals("You must be 19 years of age to attend this concert.", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-11-18 8:00pm'), $concert->date);
            $this->assertEquals('The Mosh Pit', $concert->venue);
            $this->assertEquals('123 Fake St.', $concert->venue_address);
            $this->assertEquals('Laraville', $concert->city);
            $this->assertEquals('ON', $concert->state);
            $this->assertEquals('12345', $concert->zip);
            $this->assertEquals(3250, $concert->ticket_price);
            $this->assertEquals(75, $concert->ticket_quantity);
            $this->assertEquals(0, $concert->ticketsRemaining());
        });
    }

    /** @test */
    public function guestsCannotAddANewConcert()
    {
        $response = $this->post('/backstage/concerts', $this->validParams());

        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function titleIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->from('/backstage/concerts/new')
            ->post('/backstage/concerts', $this->validParams([
                'title' => '',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('title');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function subtitleIsOptional()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
            ->post('/backstage/concerts', $this->validParams([
                'subtitle' => '',
            ]));

        tap(Concert::first(), function ($concert) use ($response, $user) {
            $response->assertStatus(302);
            $response->assertRedirect('/backstage/concerts');

            $this->assertTrue($concert->user->is($user));
            $this->assertNull($concert->subtitle);
        });
    }

    /** @test */
    public function additionalInformationIsOptional()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->post('/backstage/concerts', $this->validParams([
            'additional_information' => "",
        ]));

        tap(Concert::first(), function ($concert) use ($response, $user) {
            $response->assertStatus(302);
            $response->assertRedirect('/backstage/concerts');

            $this->assertTrue($concert->user->is($user));
            $this->assertNull($concert->additional_information);
        });
    }

    /** @test */
    public function dateIsRequired()
    {
        $user = factory(User::class)->create();
        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'date' => '',
        ]));
        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('date');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function dateMustBeAValidDate()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'date' => 'not a date',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('date');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function timeIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'time' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('time');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function timeMustBeAValidTime()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'time' => 'not-a-time',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('time');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function venueIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'venue' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('venue');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function venueAddressIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'venue_address' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('venue_address');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function cityIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'city' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('city');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function stateIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'state' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('state');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function zipIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'zip' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('zip');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketPriceIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_price' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_price');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketPriceMustBeNumeric()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_price' => 'not a price',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_price');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketPriceMustBeAtLeast5()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_price' => '4.99',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_price');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketQuantityIsRequired()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_quantity' => '',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_quantity');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketQuantityMustBeNumeric()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_quantity' => 'not a number',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_quantity');
        $this->assertEquals(0, Concert::count());
    }

    /** @test */
    public function ticketQuantityMustBeAtLeast1()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->from('/backstage/concerts/new')->post('/backstage/concerts', $this->validParams([
            'ticket_quantity' => '0',
        ]));

        $response->assertRedirect('/backstage/concerts/new');
        $response->assertSessionHasErrors('ticket_quantity');
        $this->assertEquals(0, Concert::count());
    }
}
