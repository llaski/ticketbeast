<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublishedConcertsController extends Controller
{
    public function store(Request $request)
    {
        $concert = Auth::user()->concerts()->findOrFail(request('concert_id'));

        if ($concert->isPublished()) {
            abort(422);
        }

        $concert->publish();

        return redirect('/backstage/concerts');
    }
}
