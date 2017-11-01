<?php

namespace Tests\Feature\Backstage;

use App\Concert;
use App\User;
use ConcertFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class ViewConcertListTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function guestsCannotViewAPromotersConcertList()
    {
        $response = $this->get('/backstage/concerts');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /** @test */
    public function promotersCanOnlyViewAListOfTheirOwnConcerts()
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();

        $publishedConcertA = ConcertFactory::createPublished(['user_id' => $user->id]);
        $publishedConcertB = ConcertFactory::createPublished(['user_id' => $user->id]);
        $publishedConcertC = ConcertFactory::createPublished(['user_id' => $otherUser->id]);

        $unpublishedConcertA = ConcertFactory::createUnpublished(['user_id' => $user->id]);
        $unpublishedConcertB = ConcertFactory::createUnpublished(['user_id' => $user->id]);
        $unpublishedConcertC = ConcertFactory::createUnpublished(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/backstage/concerts');

        $response->assertStatus(200);

        $response->data('publishedConcerts')->assertEquals([
            $publishedConcertA,
            $publishedConcertB
        ]);

        $response->data('unpublishedConcerts')->assertEquals([
            $unpublishedConcertA,
            $unpublishedConcertB
        ]);
    }

}
