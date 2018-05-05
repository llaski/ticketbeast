<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ConnectWithStripe extends DuskTestCase
{

    /** @test */
    public function connectingAStripeAccountSuccessfully()
    {
        $user = factory(User::class)->create([
            'stripe_account_id' => null,
            'stripe_access_token' => null,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/backstage/stripe-connect/authorize')
                ->assertUrlIs('https://connect.stripe.com/oauth/authorize')
                ->assertQueryStringHas('response_type', 'code')
                ->assertQueryStringHas('scope', 'read_write')
                ->assertQueryStringHas('client_id', config('services.stripe.client_id'));
        });
    }

}
