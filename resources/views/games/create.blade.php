@extends('app')

@section('content')
<h2 class="mb-3">Add Game</h2>
<form action="{{ route('games.store') }}" method="post">
    @csrf

    <div class="mb-5">
        <label for="home_team" class="block mb-2 text-sm font-medium text-gray-900">Home Team</label>
        <select name="home_team" id="home_team" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            @foreach ($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-5">
        <label for="away_team" class="block mb-2 text-sm font-medium text-gray-900">Away Team</label>
        <select name="away_team" id="away_team" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            @foreach ($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>
        </select>
    </div>
    <div class="mb-5">
        <label for="match_date" class="block mb-2 text-sm font-medium text-gray-900">Match Date</label>
        <input type="date" name="match_date" id="match_date" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="1870" />
    </div>
    <div class="mb-5">
        <label for="location" class="block mb-2 text-sm font-medium text-gray-900">Location</label>
        <input type="text" name="location" id="location" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" />
    </div>
    <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center">Add Team</button>
</form>
@endsection
