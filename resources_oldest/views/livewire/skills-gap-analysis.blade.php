<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#128269; Skills Gap Analysis</h5>

<div class="card border-0 shadow-sm mb-4 p-3">
    <div class="row align-items-center">
        <div class="col-md-4">
            <label class="form-label fw-semibold small">Gap Threshold (applicants below this = skill gap)</label>
            <div class="d-flex align-items-center gap-2">
                <input type="number" wire:model.live="threshold" class="form-control form-control-sm" style="width:90px" min="1">
                <span class="text-muted small">applicants</span>
            </div>
        </div>
        <div class="col-md-8 text-md-end mt-2 mt-md-0">
            <span class="badge bg-danger me-2">&#9660; {{ $gapSkills->count() }} Gap Skills</span>
            <span class="badge bg-success">&#9650; {{ $surplusSkills->count() }} Adequate Skills</span>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Skills Gap Table --}}
    <div class="col-md-6">
        <div class="card border-danger border-0 shadow-sm">
            <div class="card-header fw-bold text-white" style="background:#C00000">
                &#9660; Skills Gap (Below {{ $threshold }} Registrants)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px; overflow-y:auto">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr><th>Skill</th><th>Category</th><th>Registrants</th></tr>
                        </thead>
                        <tbody>
                            @forelse($gapSkills as $row)
                            <tr>
                                <td>{{ $row->skill }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $row->category }}</span></td>
                                <td><span class="badge bg-danger">{{ $row->applicant_count }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No gap skills found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Surplus Skills --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-bold text-white" style="background:#70AD47">
                &#9650; Adequate Skills ({{ $threshold }}+ Registrants)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px; overflow-y:auto">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr><th>Skill</th><th>Category</th><th>Registrants</th></tr>
                        </thead>
                        <tbody>
                            @forelse($surplusSkills as $row)
                            <tr>
                                <td>{{ $row->skill }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $row->category }}</span></td>
                                <td><span class="badge bg-success">{{ $row->applicant_count }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Category Totals --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header fw-bold py-2" style="background:#f8f9fa">Skills by Category (Total Registrations)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 small">
                <thead class="table-light">
                    <tr><th class="ps-3">Category</th><th>Total Skill Registrations</th><th>Bar</th></tr>
                </thead>
                <tbody>
                    @php $max = $categoryTotals->max('total') ?: 1; @endphp
                    @foreach($categoryTotals as $cat)
                    <tr>
                        <td class="ps-3">{{ $cat->name }}</td>
                        <td><strong>{{ $cat->total }}</strong></td>
                        <td style="width:50%">
                            <div class="progress" style="height:12px">
                                <div class="progress-bar" style="width:{{ ($cat->total / $max) * 100 }}%; background:#1F4E79"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
