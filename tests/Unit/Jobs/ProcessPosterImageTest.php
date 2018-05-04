<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessPosterImage;
use ConcertFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessPosterImageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function itResizesThePosterImageTo600pxWide()
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'posters/example-poster.png',
            file_get_contents(base_path('tests/__fixtures__/full-size-poster.png'))
        );

        $concert = ConcertFactory::createUnpublished([
            'poster_image_path' => 'posters/example-poster.png',
        ]);

        ProcessPosterImage::dispatch($concert);

        $resizedImage = Storage::disk('public')->get('posters/example-poster.png');
        list($width, $height) = getimagesizefromstring($resizedImage);

        $this->assertEquals(600, $width);
        $this->assertEquals(776, $height);
    }

    /** @test */
    public function itOptimizesThePosterImage()
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'posters/example-poster.png',
            file_get_contents(base_path('tests/__fixtures__/small-unoptimized-poster.png'))
        );

        $concert = ConcertFactory::createUnpublished([
            'poster_image_path' => 'posters/example-poster.png',
        ]);

        ProcessPosterImage::dispatch($concert);

        $originalSize = filesize(base_path('tests/__fixtures__/small-unoptimized-poster.png'));
        $optimizedImageSize = Storage::disk('public')->size('posters/example-poster.png');

        $this->assertLessThan($originalSize, $optimizedImageSize);

        $controlImageContents = file_get_contents(base_path('tests/__fixtures__/optimized-poster.png'));
        $optimizedImageContents = Storage::disk('public')->get('posters/example-poster.png');

        $this->assertEquals($controlImageContents, $optimizedImageContents);
    }
}
