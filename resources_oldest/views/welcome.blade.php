<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Connect — Catanduanes Province</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body style="background:#f0f6fb">
<nav class="navbar navbar-dark" style="background:#1F4E79">
    <div class="container">
        <span class="navbar-brand fw-bold">&#128200; PESO Connect — Catanduanes</span>
        <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Staff Login</a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <div class="display-5 fw-bold mb-3" style="color:#1F4E79">&#128200;</div>
            <h1 class="fw-bold mb-3" style="color:#1F4E79">PESO Connect</h1>
            <p class="lead text-muted mb-4">
                The online workforce skills registration and analytics platform of the
                <strong>Public Employment Service Office of Catanduanes Province</strong>.
            </p>
            <a href="{{ route('register') }}" class="btn btn-lg text-white fw-bold px-5 py-3"
               style="background:#1F4E79">
                Register Your Skills Now &rarr;
            </a>
            <p class="mt-3 text-muted small">Free to register. No account needed.</p>
        </div>
    </div>

    <div class="row g-4 mt-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100">
                <div class="fs-1 mb-2">&#9989;</div>
                <h6 class="fw-bold">Online Registration</h6>
                <p class="text-muted small mb-0">Submit your personal info and workforce skills in minutes — no office visit needed.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100">
                <div class="fs-1 mb-2">&#128196;</div>
                <h6 class="fw-bold">Your Reference ID</h6>
                <p class="text-muted small mb-0">After registering, you receive a unique reference ID to track your application.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100">
                <div class="fs-1 mb-2">&#128274;</div>
                <h6 class="fw-bold">Data Privacy Protected</h6>
                <p class="text-muted small mb-0">Your data is protected under RA 10173 (Data Privacy Act of 2012).</p>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-4 text-muted small mt-4" style="border-top:1px solid #dee2e6">
    PESO Connect &copy; {{ date('Y') }} — Catanduanes PESO | Department of Labor and Employment
</footer>
</body>
</html>
