<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeagueRequest;
use App\Http\Requests\UpdateLeagueRequest;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeaguesController extends Controller
{
    /**
     * Display a listing of public leagues plus any leagues owned by the
     * authenticated user.
     */
    public function index(): Response
    {
        $user = request()->user();

        $leagues = League::query()
            ->where(function ($query) use ($user) {
                $query->where('is_public', true);

                if ($user !== null) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->latest()
            ->get(['id', 'user_id', 'name', 'slug', 'description', 'country', 'is_public']);

        return Inertia::render('Leagues/Index', [
            'leagues' => $leagues,
        ]);
    }

    /**
     * Show the form for creating a new league.
     */
    public function create(): Response
    {
        $this->authorize('create', League::class);

        return Inertia::render('Leagues/Create');
    }

    /**
     * Store a newly created league owned by the authenticated user.
     */
    public function store(StoreLeagueRequest $request): RedirectResponse
    {
        $league = League::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('leagues.show', $league)
            ->with('status', "League \"{$league->name}\" created.");
    }

    /**
     * Display the specified league.
     */
    public function show(League $league): Response
    {
        $this->authorize('view', $league);

        $league->load('seasons');

        return Inertia::render('Leagues/Show', [
            'league' => $league,
            'can' => [
                'update' => request()->user()?->can('update', $league) ?? false,
                'delete' => request()->user()?->can('delete', $league) ?? false,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified league.
     */
    public function edit(League $league): Response
    {
        $this->authorize('update', $league);

        return Inertia::render('Leagues/Edit', [
            'league' => $league,
        ]);
    }

    /**
     * Update the specified league.
     */
    public function update(UpdateLeagueRequest $request, League $league): RedirectResponse
    {
        $league->update($request->validated());

        return redirect()
            ->route('leagues.show', $league)
            ->with('status', "League \"{$league->name}\" updated.");
    }

    /**
     * Remove the specified league.
     */
    public function destroy(League $league): RedirectResponse
    {
        $this->authorize('delete', $league);

        $name = $league->name;
        $league->delete();

        return redirect()
            ->route('leagues.index')
            ->with('status', "League \"{$name}\" deleted.");
    }
}
