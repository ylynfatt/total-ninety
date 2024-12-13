@extends('app')

@section('content')
    <h2 class="mb-4 text-lg">Teams</h2>
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
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $team->name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $team->year_founded }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $team->home_ground }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                <a href="#" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-blue-600 hover:text-blue-800 focus:outline-none focus:text-blue-800 disabled:opacity-50 disabled:pointer-events-none dark:text-blue-500 dark:hover:text-blue-400 dark:focus:text-blue-400">Edit</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
@endsection
