@extends('app')

@section('content')
    <div class="mb-4 flex justify-between items-center">
        <h2 class="text-4xl font-extrabold">Teams</h2>
        <a href="{{ route('teams.create') }}" class="px-3 py-2 text-sm font-medium text-white inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg text-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>

            Add Team
        </a>
    </div>
    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
        <thead>
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Team</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Year Founded</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">Home Ground</th>
                <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
        @foreach ($teams as $team)
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                <a href="{{ route('teams.show', [$team->id]) }}">{{ $team->name }}</a>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $team->year_founded }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $team->home_ground }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                <a href="{{ route('teams.edit', [$team->id]) }}" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-blue-600 hover:text-blue-800 focus:outline-none focus:text-blue-800 disabled:opacity-50 disabled:pointer-events-none dark:text-blue-500 dark:hover:text-blue-400 dark:focus:text-blue-400">Edit</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
@endsection
