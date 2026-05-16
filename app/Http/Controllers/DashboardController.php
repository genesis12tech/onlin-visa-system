<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $profile = $request->user()->applicantProfile;

        return view('pages.dashboard', compact('profile'));
    }
}
