<div class="bg-white rounded-xl border overflow-hidden mt-5" style="border-color:#e2e8f0;">
    <div class="px-5 py-3 border-b" style="background:#f8fafc; border-color:#e2e8f0;">
        <span class="text-sm font-bold" style="color:#1e293b;">Applicants ({{ $applicants->total() }})</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead style="background:#f8fafc;">
                <tr class="text-left text-xs uppercase tracking-wide" style="color:#374151;">
                    <th class="px-4 py-2">Reference ID</th>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Barangay</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Registered</th>
                    <th class="px-4 py-2 text-right">Form</th>
                </tr>
            </thead>
            <tbody>
                @forelse($applicants as $a)
                <tr class="border-t" style="border-color:#e2e8f0;">
                    <td class="px-4 py-2 font-mono text-xs">{{ $a->reference_id }}</td>
                    <td class="px-4 py-2">{{ $a->full_name }}</td>
                    <td class="px-4 py-2">{{ $a->barangay?->name }}</td>
                    <td class="px-4 py-2">{{ $a->status }}</td>
                    <td class="px-4 py-2">{{ $a->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('reports.applicant-form', $a) }}" target="_blank"
                           class="inline-block text-xs font-semibold px-3 py-1.5 rounded-lg text-white hover:opacity-90"
                           style="background:#1F4E79;">
                            <i class="fas fa-file-lines mr-1"></i> View Form
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center" style="color:#64748b;">No applicants match these filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t" style="border-color:#e2e8f0;">
        {{ $applicants->links() }}
    </div>
</div>
