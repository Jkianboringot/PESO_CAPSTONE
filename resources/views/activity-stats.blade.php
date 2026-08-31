<!DOCTYPE html>
<html>
<head>
    <title>Activity Stats</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

    <h1 class="text-2xl font-bold mb-6">Activity Overview</h1>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Job Portal Views</p>
            <p class="text-2xl font-bold">{{ $total_job_portal_views }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Unique Visitors</p>
            <p class="text-2xl font-bold">{{ $unique_visitors }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Views Today</p>
            <p class="text-2xl font-bold">{{ $views_today }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Successful Logins</p>
            <p class="text-2xl font-bold text-green-600">{{ $login_success }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Failed Logins</p>
            <p class="text-2xl font-bold text-red-600">{{ $login_failed }}</p>
        </div>
    </div>

    <h2 class="text-lg font-semibold mb-3">Recent Activity</h2>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left p-3">Time</th>
                    <th class="text-left p-3">Source</th>
                    <th class="text-left p-3">Account</th>
                    <th class="text-left p-3">IP Address</th>
                    <th class="text-left p-3">Browser</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 whitespace-nowrap">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                        <td class="p-3">
                            @if ($log->event === 'job_portal_view')
                                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">Job Portal Visit</span>
                            @elseif ($log->event === 'login_success')
                                <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Login Success</span>
                            @elseif ($log->event === 'login_failed')
                                <span class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">Login Failed</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if ($log->event === 'login_failed')
                                {{ $log->meta['attempted_login'] ?? '—' }}
                            @elseif ($log->user)
                                {{ $log->user->email }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3">{{ $log->ip_address }}</td>
                        <td class="p-3 max-w-xs truncate" title="{{ $log->user_agent }}">{{ $log->user_agent }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>