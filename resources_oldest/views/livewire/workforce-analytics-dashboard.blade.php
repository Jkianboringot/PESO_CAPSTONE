<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#128200; Workforce Analytics Dashboard</h5>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #1F4E79 !important">
            <div class="fs-1 fw-bold" style="color:#1F4E79">{{ $chartData['totals']['total'] }}</div>
            <div class="text-muted small">Total Registered (Filtered)</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-top:4px solid #70AD47 !important">
            <div class="fs-1 fw-bold text-success">{{ $chartData['totals']['thisMonth'] }}</div>
            <div class="text-muted small">Registrations This Month</div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card border-0 shadow-sm mb-4 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-bold">Skills Category</label>
            <select wire:model.live="filterCategory" class="form-select form-select-sm">
                <option value="">All Categories</option>
                @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Barangay</label>
            <select wire:model.live="filterBarangay" class="form-select form-select-sm">
                <option value="">All Barangays</option>
                @foreach($barangays as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Education Level</label>
            <select wire:model.live="filterEdLevel" class="form-select form-select-sm">
                <option value="">All Levels</option>
                @foreach($educationLevels as $l)<option>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Sex</label>
            <select wire:model.live="filterSex" class="form-select form-select-sm">
                <option value="">All</option>
                <option>Male</option><option>Female</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">From</label>
            <input type="date" wire:model.live="filterFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">To</label>
            <input type="date" wire:model.live="filterTo" class="form-control form-control-sm">
        </div>
    </div>
</div>

{{-- Chart Grid --}}
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3">
            <h6 class="fw-bold mb-2">Top Skills by Registrant Count</h6>
            <canvas id="skillsChart" height="220"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3">
            <h6 class="fw-bold mb-2">Educational Attainment Distribution</h6>
            <canvas id="eduChart" height="220"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3">
            <h6 class="fw-bold mb-2">Registrants by Barangay (Top 20)</h6>
            <canvas id="barangayChart" height="220"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3">
            <h6 class="fw-bold mb-2">Monthly Registration Trend (Last 12 Months)</h6>
            <canvas id="trendChart" height="220"></canvas>
        </div>
    </div>
</div>

<script>
const initialData = @json($chartData);
let charts = {};

function renderCharts(data) {
    ['skillsChart','eduChart','barangayChart','trendChart'].forEach(id => {
        if (charts[id]) charts[id].destroy();
    });

    charts.skillsChart = new Chart(document.getElementById('skillsChart'), {
        type: 'bar',
        data: { labels: data.skills.labels, datasets: [{
            label: 'Applicants', data: data.skills.data, backgroundColor: '#2E74B5',
        }]},
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
    });

    charts.eduChart = new Chart(document.getElementById('eduChart'), {
        type: 'doughnut',
        data: { labels: data.education.labels, datasets: [{
            data: data.education.data,
            backgroundColor: ['#1F4E79','#2E74B5','#BDD7EE','#DEEAF1','#70AD47','#FFC000','#FF0000'],
        }]},
        options: { responsive: true }
    });

    charts.barangayChart = new Chart(document.getElementById('barangayChart'), {
        type: 'bar',
        data: { labels: data.barangay.labels, datasets: [{
            label: 'Applicants', data: data.barangay.data, backgroundColor: '#70AD47',
        }]},
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    charts.trendChart = new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: data.trend.labels, datasets: [{
            label: 'Registrations', data: data.trend.data,
            borderColor: '#1F4E79', backgroundColor: 'rgba(30,78,121,0.1)', tension: 0.3, fill: true
        }]},
        options: { responsive: true }
    });
}

document.addEventListener('livewire:initialized', () => {
    renderCharts(initialData);
    Livewire.on('refresh-charts', (event) => {
        renderCharts(event.charts);
    });
});
</script>
</div>
