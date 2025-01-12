<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Total Ninety</title>
  @vite('resources/css/app.css')
</head>
<body>
    <header class="p-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="mb-4 text-4xl font-extrabold leading-none tracking-tight text-gray-900 md:text-5xl lg:text-6xl">⚽️ Total Ninety</h1>
            <nav class="flex gap-4 bg-slate-500 rounded-md p-2">
                <a href="{{ route('teams.index') }}" class="text-white py-1 px-2 hover:bg-slate-600 hover:rounded-md">Teams</a>
                <a href="{{ route('games.index') }}" class="text-white py-1 px-2 hover:bg-slate-600 hover:rounded-md">Games</a>
            </nav>
        </div>
    </header>
    <main class="max-w-4xl mx-auto">
        @if (session('status'))
            <div class="alert alert-success flex gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>

                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
