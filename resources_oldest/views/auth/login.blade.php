<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Connect — Staff Login</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body style="background:#f0f6fb">
<div class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="card border-0 shadow" style="width:100%;max-width:420px">
        <div class="card-header text-center py-4" style="background:#1F4E79">
            <h4 class="text-white fw-bold mb-0">&#128200; PESO Connect</h4>
            <small class="text-white-50">Catanduanes PESO Staff Portal</small>
        </div>
        <div class="card-body p-4">
            @if($errors->any())
                <div class="alert alert-danger py-2">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn w-100 text-white fw-bold"
                        style="background:#1F4E79">Login</button>
            </form>
        </div>
        <div class="card-footer text-center text-muted small py-3">
            <a href="{{ route('register') }}" class="text-decoration-none">
                Resident? Register your skills here &rarr;
            </a>
        </div>
    </div>
</div>
</body>
</html>
