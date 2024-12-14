@extends('app')

@section('content')
    <h2 class="mb-4 text-lg">{{ $team->name }}</h2>
    <p>Year Founded: {{ $team->year_founded }}</p>
    <p>Home Ground: {{ $team->home_ground }}</p>
    <p class="mt-4">
        <a href="{{ route('teams.index') }}" class="px-3 py-2 text-sm font-medium text-white inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg text-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>

            Back
        </a>
    </p>
@endsection
