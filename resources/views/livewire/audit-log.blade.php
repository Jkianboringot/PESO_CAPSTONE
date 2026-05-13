<div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">

    {{-- Dark header bar --}}
     <div class="px-5 py-3 flex items-center justify-between" style="background: #1a2035;">
        <span class="text-sm font-semibold text-white">
            <i class="fas fa-table mr-2 opacity-70"></i>Audit Logs
        </span>
      
    </div> 

    {{-- Column headers --}}
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">When</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">User</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">Action</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">Type</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">Details</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                        style="color: #64748b; font-size: 10px;">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y" style="border-color: #f1f5f9;">
                @forelse($auditLogs as $log)
                <tr class="hover:bg-slate-50 transition-colors">

                    <td class="px-4 py-3 font-medium" style="color: #1e293b;">
                        <div class="fw-semibold text-inv-dark lh-sm">
                            {{ $log->created_at->format('M d, Y') }}
                        </div>
                        <small class="text-muted">
                            {{ $log->created_at->format('g:i A') }}
                        </small>

                    </td>
                    <td class="px-4 py-3" style="color: #475569;">
                        {{ $log->user?->name ?? 'System' }}
                    </td>

                    <td class="px-4 py-3">
                        <code class="text-xs px-1.5 py-0.5 rounded font-mono"
                            style="background: #f1f5f9; color: #1F4E79;">  {{ ucfirst($log->action) }}</code>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs px-1.5 py-0.5 rounded font-mono"
                            style="background: #f1f5f9; color: #1F4E79;">  {{ class_basename($log->model) }}</code>
                    </td>
                    <td class="px-4 py-3">
                        @php $changes = $log->enriched_changes; @endphp

                        @if(isset($changes))
                        @foreach($changes as $product)
                        <div class="activity-product-block mb-2">
                            <div class="fw-semibold text-inv-dark small lh-sm">
                                {{ $product['before'] }}
                            </div>
                         <div class="fw-semibold text-inv-dark small lh-sm">
                                {{ $product['after'] }}
                            </div>
                        </div>
                        @endforeach
                        @endif
                    </td>
                 
                    <td class="px-4 py-3" style="color: #475569;">{{ $log->ip_address ?? 'Unknown' }}</td>
                  
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center" style="color: #94a3b8;">
                        <i class="fas fa-search text-3xl mb-3 block opacity-30"></i>
                        No Logs found matching your search
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
                <div class="px-4 py-3 border-top">
                    {{ $auditLogs->links() }}
                </div>

</div>