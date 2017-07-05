<?php

namespace Tests\Feature\Backstage;

use App\Concert;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EditConcertTest extends TestCase
{
    use DatabaseMigrations;

    private function validParams($overrides = [])
    {
        return array_merge([
            'title' => 'New Band Title',
            'subtitle' => 'New Band Subtitle',
            'date' => '2018-12-12',
            'time' => '8:00pm',
            'venue' => 'New Venue',
            'venue_address' => 'New Venue Address',
            'city' => 'New City',
            'state' => 'New State',
            'zip' => '99999',
            'additional_information' => 'New additional info',
            'ticket_price' => '72.50',
            'ticket_quantity' => '10',
        ], $overrides);
    }

    /**
     * @test
     */
    public function promotersCanViewTheEditFormForTheirOwnUnpublishedConcerts()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create(['user_id' => $user->id]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)->get("/backstage/concerts/{$concert->id}/edit");

        $response->assertStatus(200);
        $this->assertTrue($response->original->getData()['concert']->is($concert));
    }

    /**
     * @test
     */
    public function promotersCanNotViewTheEditFormForTheirOwnPublishedConcerts()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->states('published')->create(['user_id' => $user->id]);
        $this->assertTrue($concert->isPublished());

        $response = $this->actingAs($user)->get("/backstage/concerts/{$concert->id}/edit");

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function promotersCanNotViewTheEditFormForOtherConcerts()
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $concert = factory(Concert::class)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/backstage/concerts/{$concert->id}/edit");

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function promotersCanNotEditTheirOwnPublishedConcerts()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->states('published')->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertTrue($concert->isPublished());

        $response = $this->actingAs($user)->patch("/backstage/concerts/{$concert->id}", $this->validParams());

        $response->assertStatus(403);

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /**
     * @test
     */
    public function promotersSeeA404WhenAttemptingToViewTheEditFormForAConcertThatDoesNotExist()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get("/backstage/concerts/1231/edit");

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function guestsAreAskedToLoginWhenAttemptingToViewTheEditFormForAnyConcert()
    {
        $concert = factory(Concert::class)->create();

        $response = $this->get("/backstage/concerts/{$concert->id}/edit");

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * @test
     */
    public function guestsAreAskedToLoginWhenAttemptingToViewTheEditFormForAConcertThatDoesNotExist()
    {
        $response = $this->get("/backstage/concerts/123/edit");

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /** @test */
    public function promotersCanEditTheirOwnUnpublishedConcerts()
    {
        $this->disableExceptionHandling();

        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)->patch("/backstage/concerts/{$concert->id}", $this->validParams());

        $response->assertRedirect("/backstage/concerts");

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('New Band Title', $concert->title);
            $this->assertEquals('New Band Subtitle', $concert->subtitle);
            $this->assertEquals("New additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2018-12-12 8:00pm'), $concert->date);
            $this->assertEquals('New Venue', $concert->venue);
            $this->assertEquals('New Venue Address', $concert->venue_address);
            $this->assertEquals('New City', $concert->city);
            $this->assertEquals('New State', $concert->state);
            $this->assertEquals('99999', $concert->zip);
            $this->assertEquals(7250, $concert->ticket_price);
            $this->assertEquals(10, $concert->ticket_quantity);
        });
    }

    /** @test */
    public function promotersCanNotEditOtherPromotersUnpublishedConcerts()
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $otherUser->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)->patch("/backstage/concerts/{$concert->id}", $this->validParams());

        $response->assertStatus(404);

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function guestsCanNotEditConcerts()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->states('published')->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);

        $response = $this->patch("/backstage/concerts/{$concert->id}", $this->validParams());

        $response->assertRedirect('/login');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function titleIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'title' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('title');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function subtitleIsOptional()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'subtitle' => '',
            ]));

        $response->assertRedirect("/backstage/concerts");

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('New Band Title', $concert->title);
            $this->assertNull($concert->subtitle);
            $this->assertEquals("New additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2018-12-12 8:00pm'), $concert->date);
            $this->assertEquals('New Venue', $concert->venue);
            $this->assertEquals('New Venue Address', $concert->venue_address);
            $this->assertEquals('New City', $concert->city);
            $this->assertEquals('New State', $concert->state);
            $this->assertEquals('99999', $concert->zip);
            $this->assertEquals(7250, $concert->ticket_price);
            $this->assertEquals(10, $concert->ticket_quantity);
        });
    }

    /** @test */
    public function additionalInformationIsOptional()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'additional_information' => '',
            ]));

        $response->assertRedirect("/backstage/concerts");

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('New Band Title', $concert->title);
            $this->assertEquals("New Band Subtitle", $concert->subtitle);
            $this->assertNull($concert->additional_information);
            $this->assertEquals(Carbon::parse('2018-12-12 8:00pm'), $concert->date);
            $this->assertEquals('New Venue', $concert->venue);
            $this->assertEquals('New Venue Address', $concert->venue_address);
            $this->assertEquals('New City', $concert->city);
            $this->assertEquals('New State', $concert->state);
            $this->assertEquals('99999', $concert->zip);
            $this->assertEquals(7250, $concert->ticket_price);
            $this->assertEquals(10, $concert->ticket_quantity);
        });
    }

    /** @test */
    public function dateIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'date' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('date');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function dateMustBeAValidDate()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'date' => 'not a date',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('date');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function timeIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'time' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('time');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function timeMustBeAValidTime()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'time' => 'not-a-time',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('time');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function venueIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'venue' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('venue');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function venueAddressIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'venue_address' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('venue_address');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function cityIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'city' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('city');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function stateIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'state' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('state');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function zipIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'zip' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('zip');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketPriceIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_price' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_price');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketPriceMustBeNumeric()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_price' => 'not a price',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_price');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketPriceMustBeAtLeast5()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_price' => '4.99',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_price');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketQuantityIsRequired()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_quantity' => '',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_quantity');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketQuantityMustBeNumeric()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_quantity' => 'not a number',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_quantity');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }

    /** @test */
    public function ticketQuantityMustBeAtLeast1()
    {
        $user = factory(User::class)->create();
        $concert = factory(Concert::class)->create([
            'user_id' => $user->id,
            'title' => 'Old Band Title',
            'subtitle' => 'Old Band Subtitle',
            'date' => Carbon::parse('2017-01-01 1:00pm'),
            'venue' => 'Old Venue',
            'venue_address' => 'Old Venue Address',
            'city' => 'Old City',
            'state' => 'Old State',
            'zip' => '00000',
            'additional_information' => 'Old additional info',
            'ticket_price' => 2000,
            'ticket_quantity' => 5,
        ]);
        $this->assertFalse($concert->isPublished());

        $response = $this->actingAs($user)
            ->from("/backstage/concerts/{$concert->id}/edit")
            ->patch("/backstage/concerts/{$concert->id}", $this->validParams([
                'ticket_quantity' => '0',
            ]));

        $response->assertRedirect("/backstage/concerts/{$concert->id}/edit");
        $response->assertSessionHasErrors('ticket_quantity');

        tap($concert->fresh(), function ($concert) use ($user) {
            $this->assertEquals('Old Band Title', $concert->title);
            $this->assertEquals('Old Band Subtitle', $concert->subtitle);
            $this->assertEquals("Old additional info", $concert->additional_information);
            $this->assertEquals(Carbon::parse('2017-01-01 1:00pm'), $concert->date);
            $this->assertEquals('Old Venue', $concert->venue);
            $this->assertEquals('Old Venue Address', $concert->venue_address);
            $this->assertEquals('Old City', $concert->city);
            $this->assertEquals('Old State', $concert->state);
            $this->assertEquals('00000', $concert->zip);
            $this->assertEquals(2000, $concert->ticket_price);
        });
    }
}
