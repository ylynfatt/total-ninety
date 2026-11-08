<?php

namespace App\Http\Controllers;

use App\Actions\BuildDashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildDashboardOverview $overview): Response
    {
        return Inertia::render('Dashboard', [
            'dateLabel' => today()->isoFormat('dddd, MMMM D, YYYY'),
            'timezone' => config('app.timezone'),
            'overview' => Inertia::optional(fn (): array => $overview->execute($request->user())),
        ]);
    }
}
