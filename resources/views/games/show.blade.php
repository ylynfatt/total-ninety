@extends('app')

@section('content')
<div class="mb-4 flex justify-between items-center">
    <h2 class="text-4xl font-extrabold">Gamecast</h2>
</div>
<div class="max-w-7xl max-auto">
    <div class="flex justify-between my-12 border border-gray-200 p-8 rounded-lg shadow-md">
        <div class="w-1/3 text-center">
            <h3 class="text-2xl font-bold">{{ $game->homeTeam->name }}</h3>
            <p class="text-md text-gray-500">Home Team</p>
        </div>
        <div class="w-1/3 text-center">
            <p class="text-md">{{ $game->match_date }}</p>
            <p class="text-sm">{{ $game->location }}</p>
        </div>
        <div class="w-1/3 text-center">
            <h3 class="text-2xl font-bold">{{ $game->awayTeam->name }}</h3>
            <p class="text-md text-gray-500">Away Team</p>
        </div>
    </div>
</div>
@endsection
