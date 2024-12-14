@extends('app')

@section('content')
<h2 class="mb-3">Edit Team</h2>
<form action="{{ route('teams.update', [$team->id]) }}" method="post">
    @csrf
    @method('PUT')

    <div class="mb-5">
        <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Team Name</label>
        <input type="text" name="name" id="name" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" value="{{ $team->name }}" />
    </div>
    <div class="mb-5">
        <label for="acronym" class="block mb-2 text-sm font-medium text-gray-900">Acronym/Short name</label>
        <input type="text" name="acronym" id="acronym" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" value="{{ $team->acronym }}" />
    </div>
    <div class="mb-5">
        <label for="year_founded" class="block mb-2 text-sm font-medium text-gray-900">Year Founded</label>
        <input type="number" name="year_founded" id="year_founded" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="1870"  value="{{ $team->year_founded }}" />
    </div>
    <div class="mb-5">
        <label for="home_ground" class="block mb-2 text-sm font-medium text-gray-900">Home Ground</label>
        <input type="text" name="home_ground" id="home_ground" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" value="{{ $team->home_ground }}" />
    </div>
    <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center">Update Team</button>
</form>
@endsection
