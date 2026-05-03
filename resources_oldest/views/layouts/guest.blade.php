<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Connect — Catanduanes Province</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body style="background:#f0f6fb">
<nav class="navbar navbar-dark" style="background:#1F4E79">
    <div class="container">
        <span class="navbar-brand fw-bold">&#128200; PESO Connect — Catanduanes</span>
        <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Staff Login</a>
    </div>
</nav>

<main class="container py-4">
    {{ $slot }}
</main>

<footer class="text-center py-3 text-muted small">
    Your personal data is collected under Republic Act No. 10173 (Data Privacy Act of 2012)
    for workforce planning purposes of Catanduanes PESO.
</footer>

@livewireScripts
</body>
</html>
