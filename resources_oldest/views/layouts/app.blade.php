<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Connect — {{ $title ?? '' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#1F4E79">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">&#128200; PESO Connect</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('applicants') ? 'active' : '' }}"
                       href="{{ route('applicants') }}">Applicants</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}"
                       href="{{ route('analytics') }}">Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('reports') ? 'active' : '' }}"
                       href="{{ route('reports') }}">Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('skills-gap') ? 'active' : '' }}"
                       href="{{ route('skills-gap') }}">Skills Gap</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative {{ request()->routeIs('duplicates') ? 'active' : '' }}"
                       href="{{ route('duplicates') }}">
                        Duplicates
                        @php $pending = \App\Models\DuplicateFlag::pending()->count(); @endphp
                        @if($pending)
                            <span class="badge bg-danger ms-1">{{ $pending }}</span>
                        @endif
                    </a>
                </li>
                @if(auth()->user()->isAdmin())
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}"
                       href="{{ route('admin.users') }}">Users</a>
                </li>
                @endif
            </ul>
            <div class="d-flex align-items-center text-white me-3">
                {{ auth()->user()->name }}
                <span class="badge ms-2" style="background:#2E74B5">{{ auth()->user()->role?->name }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-light btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 px-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    {{ $slot }}
</main>

@livewireScripts
</body>
</html>
