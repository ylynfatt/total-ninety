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
            <h1 class="text-2xl mb-4">Total Ninety</h1>
        </div>
    </header>
    <main class="max-w-4xl mx-auto">
        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
