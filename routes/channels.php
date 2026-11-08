<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{gameId}.owner.{ownerId}', function (User $user, int $gameId, int $ownerId): bool {
    $game = Game::query()->with(['stage.season.league', 'season.league'])->find($gameId);
    $league = $game?->stage?->season?->league ?? $game?->season?->league;

    return $user->id === $ownerId && $league?->user_id === $user->id;
});
