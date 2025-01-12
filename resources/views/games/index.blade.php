@extends('app')

@section('content')
<div class="mb-4 flex justify-between items-center">
    <h2 class="text-4xl font-extrabold">Games</h2>
    <a href="{{ route('games.create') }}" class="px-3 py-2 text-sm font-medium text-white inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>

        Add Game
    </a>
</div>
<div class="max-w-7xl max-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
        <thead>
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Home Team</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Date</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Away Team</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Location</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
        @foreach ($games as $game)
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                {{ $game->homeTeam->name }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $game->match_date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $game->awayTeam->name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $game->location }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
