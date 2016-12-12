<?php

namespace App\Http\Controllers;

use App\Concert;
use Illuminate\Http\Request;

class ConcertsController extends Controller
{
    function show($id)
    {
        $concert = Concert::find($id);

        return view('concerts.show', compact('concert'));
    }
}
