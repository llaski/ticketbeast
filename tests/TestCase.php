<?php

namespace Tests;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    protected function setUp()
    {
        parent::setUp();
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        TestResponse::macro('data', function ($key) {
            return $this->original->getData()[$key];
        });

        TestResponse::macro('assertViewIs', function ($name) {
            Assert::assertEquals($name, $this->original->name());
        });

        EloquentCollection::macro('assertContains', function ($value) {
            Assert::assertTrue($this->contains($value), "Failed asserting that the collection contains the specified value.");
        });

        EloquentCollection::macro('assertNotContains', function ($value) {
            Assert::assertFalse($this->contains($value), "Failed asserting that the collection does not contain the specified value.");
        });

        EloquentCollection::macro('assertEquals', function ($items) {
            Assert::assertEquals(count($this), count($items));

            $this->zip($items)->each(function ($pair) {
                list($a, $b) = $pair;
                Assert::assertTrue($a->is($b));
            });
        });
    }

    // public function from(string $url)
    // {
    //     session()->setPreviousUrl(url($url));

    //     return $this;
    // }

    protected function disableExceptionHandling()
    {
        $this->app->instance(ExceptionHandler::class, new class extends Handler
        {
            public function __construct()
            {}
            public function report(Exception $e)
            {}
            public function render($request, Exception $e)
            {
                throw $e;
            }
        });
    }

    protected function deh()
    {
        $this->disableExceptionHandling();
    }
}
