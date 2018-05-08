<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ForceStripeAccount;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForceStripeAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function usersWithoutAStripeAccountAreForcedToConnectWithStripe()
    {
        $user = factory(User::class)->create([
            'stripe_account_id' => null,
        ]);

        $this->be($user);

        $middleware = new ForceStripeAccount;

        $response = $middleware->handle(new Request, function ($request) {
            $this->fail('Next middlware was called when it should not have been.');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('backstage.stripe-connect.connect'), $response->getTargetUrl());
    }

    /** @test */
    public function usersWithAStripeAccountCanContinue()
    {
        $user = factory(User::class)->create([
            'stripe_account_id' => 'test_sk_acct_1234',
        ]);

        $this->be($user);

        $middleware = new ForceStripeAccount;

        $request = new Request;
        $next = new class

        {
            public $called = false;

            public function __invoke($request)
            {
                $this->called = true;
                return $request;
            }
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame($response, $request);
    }

    /** @test */
    public function middlewareIsAppliedToAllBackstageRoutes()
    {
        $routes = [
            'backstage.concerts.index',
            'backstage.concerts.new',
            'backstage.concerts.store',
            'backstage.concerts.edit',
            'backstage.concerts.update',
            'backstage.published-concerts.store',
            'backstage.published-concert-orders.index',
            'backstage.concert-messages.new',
            'backstage.concert-messages.store',
        ];

        foreach ($routes as $route) {
            $this->assertContains(
                ForceStripeAccount::class,
                Route::getRoutes()->getByName($route)->gatherMiddleware()
            );
        }
    }

}
