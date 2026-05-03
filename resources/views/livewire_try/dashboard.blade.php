<div>
   <x-slot name="title">Dashboard</x-slot>

    <h5 class="fw-bold mb-4" style="color:#1F4E79">&#128200; Dashboard Overview</h5>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #1F4E79 !important">
                <div class="fs-1 fw-bold" style="color:#1F4E79">{{ $stats['total_applicants'] }}</div>
                <div class="text-muted small">Total Registered Applicants</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #70AD47 !important">
                <div class="fs-1 fw-bold text-success">{{ $stats['this_month'] }}</div>
                <div class="text-muted small">Registrations This Month</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #FFC000 !important">
                <div class="fs-1 fw-bold text-warning">{{ $stats['pending_duplicates'] }}</div>
                <div class="text-muted small">Pending Duplicate Flags</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #FF0000 !important">
                <div class="fs-1 fw-bold text-danger">{{ $stats['flagged'] }}</div>
                <div class="text-muted small">Flagged Applicants</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <a href="{{ route('applicants') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#128101; Applicant Management</h6>
                    <p class="text-muted small mb-0">Search, filter, edit, and manage all registered applicants.</p>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('analytics') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#128200; Workforce Analytics</h6>
                    <p class="text-muted small mb-0">Interactive charts for skills, education, barangay and trends.</p>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('reports') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#128196; Report Generation</h6>
                    <p class="text-muted small mb-0">Export DOLE BLE-compliant CSV/Excel reports with filters.</p>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('duplicates') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#9888; Duplicate Review</h6>
                    <p class="text-muted small mb-0">Review and resolve flagged duplicate registrations.</p>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('skills-gap') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#128269; Skills Gap Analysis</h6>
                    <p class="text-muted small mb-0">Identify underrepresented PQF skill clusters in Catanduanes.</p>
                </div>
            </a>
        </div>
        @if(auth()->user()->isAdmin())
        <div class="col-md-4">
            <a href="{{ route('admin.users') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h6 class="fw-bold" style="color:#1F4E79">&#9881; User Management</h6>
                    <p class="text-muted small mb-0">Create and manage PESO staff accounts and roles.</p>
                </div>
            </a>
        </div>
        @endif
    </div>
</div>
