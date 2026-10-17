<div>
    <x-slot name="title">Workforce Analytics</x-slot>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-chart-bar mr-2" style="color: #2563eb;"></i>
                Workforce Analytics Dashboard
            </h1>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background: #1F4E79;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Registered (Filtered)
                </p>
                <p class="text-4xl font-bold leading-none">{{ $chartData['totals']['total'] }}</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-users"></i></div>
        </div>
        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background: #2e7d32;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Registrations This Month</p>
                <p class="text-4xl font-bold leading-none">{{ $chartData['totals']['thisMonth'] }}</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-calendar-check"></i></div>
        </div>
    </div>

    {{-- ===================== FILTER BAR ===================== --}}
    {{-- No ".live" on any input: values are only sent when Filter is clicked --}}
    <div class="bg-white rounded-xl border mb-6 p-4" style="border-color: #e2e8f0;">
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 items-end">

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Skills
                    Category</label>
                <select wire:model="filterCategory"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Categories</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide"
                    style="color: #374151;">Barangay</label>
                <select wire:model="filterBarangay"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Barangays</option>
                    @foreach($barangays as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Education
                    Level</label>
                <select wire:model="filterEdLevel"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Levels</option>
                    @foreach($educationLevels as $l)
                        <option>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide"
                    style="color: #374151;">Sex</label>
                <select wire:model="filterSex"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All</option>
                    <option>Male</option>
                    <option>Female</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide"
                    style="color: #374151;">From</label>
                <input type="date" wire:model="filterFrom" max="{{ now()->toDateString() }}"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">To</label>
                <input type="date" wire:model="filterTo" max="{{ now()->toDateString() }}"
                    class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
            </div>

        </div>

        {{-- Filter / Clear buttons --}}

        <div class="flex justify-end mt-3 pt-3 border-t gap-2" style="border-color: #f1f5f9;">
            <button type="button" wire:click="clearFilters"
                class="text-xs font-semibold px-4 py-2 rounded-lg border transition-colors hover:bg-slate-50"
                style="color: #64748b; border-color: #d1d5db;">
                <i class="fas fa-times mr-1"></i> Clear
            </button>

            <button type="button" wire:click="applyFilters" wire:loading.attr="data-loading" wire:target="applyFilters"
                class="filter-btn text-xs font-semibold px-5 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
                style="background: #1a2035;">
                <span class="lbl-idle"><i class="fas fa-filter mr-1.5"></i> Filter</span>
                <span class="lbl-busy"><i class="fas fa-spinner fa-spin mr-1.5"></i> Filtering...</span>
            </button>
        </div>
    </div>

    {{-- ===================== CHART GRID ===================== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

        {{-- Chart A: Horizontal Bar — Skills --}}
        <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
            <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Top Skills by Registrant Count</h6>
            <p class="text-xs mb-0.5" style="color: #64748b;">Most common skills across all filtered applicants</p>
            <p class="text-xs mb-3" style="color: #94a3b8;">Type: Horizontal Bar Chart</p>
            <canvas id="skillsChart" height="220"></canvas>
        </div>

        {{-- Chart B: Donut — Education --}}
        <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
            <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Educational Attainment Distribution</h6>
            <p class="text-xs mb-0.5" style="color: #64748b;">Breakdown by highest education level completed</p>
            <p class="text-xs mb-3" style="color: #94a3b8;">Type: Donut / Doughnut Chart</p>
            <canvas id="eduChart" height="220"></canvas>
        </div>

    </div>

    {{-- ===================== REGISTRATION TREND ===================== --}}
    <div class="bg-white rounded-xl border p-5 mb-6" style="border-color: #e2e8f0;">
        <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Registration Trend</h6>
        <p class="text-xs mb-0.5" style="color: #64748b;">
            New registrations per month
            @if($filterFrom || $filterTo) (selected range) @else (last 12 months) @endif
        </p>
        <p class="text-xs mb-3" style="color: #94a3b8;">Type: Line Chart</p>
        <canvas id="trendChart" height="90"></canvas>
    </div>

    {{-- ===================== DEMOGRAPHICS ===================== --}}
    <div class="mb-3">
        <h2 class="text-sm font-bold uppercase tracking-wide" style="color: #1e293b;">
            <i class="fas fa-people-group mr-2" style="color: #2563eb;"></i> Demographics
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">

        {{-- Chart D: Donut — Sex --}}
        <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
            <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Sex</h6>
            <p class="text-xs mb-0.5" style="color: #64748b;">Filtered applicants by sex</p>
            <p class="text-xs mb-3" style="color: #94a3b8;">Type: Donut / Doughnut Chart</p>
            <canvas id="sexChart" height="220"></canvas>
        </div>

        {{-- Chart E: Donut — Civil Status --}}
        <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
            <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Civil Status</h6>
            <p class="text-xs mb-0.5" style="color: #64748b;">Filtered applicants by civil status</p>
            <p class="text-xs mb-3" style="color: #94a3b8;">Type: Donut / Doughnut Chart</p>
            <canvas id="civilChart" height="220"></canvas>
        </div>

        {{-- Chart F: Vertical Bar — Age Groups --}}
        <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
            <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Age Groups</h6>
            <p class="text-xs mb-0.5" style="color: #64748b;">Filtered applicants by age bracket</p>
            <p class="text-xs mb-3" style="color: #94a3b8;">Type: Vertical Bar Chart</p>
            <canvas id="ageChart" height="220"></canvas>
        </div>

    </div>

    {{-- ===================== BARANGAY (not affected by filters) ===================== --}}
    {{-- Chart C: Vertical Bar — Barangay --}}
    <div class="bg-white rounded-xl border p-5" style="border-color: #e2e8f0;">
        <h6 class="font-bold text-sm mb-0.5" style="color: #1e293b;">Registrants by Barangay (Top 20)</h6>
        <p class="text-xs mb-0.5" style="color: #64748b;">Distribution of applicants per barangay</p>
        <p class="text-xs mb-3" style="color: #94a3b8;">Type: Vertical Bar Chart</p>
        <canvas id="barangayChart" height="100"></canvas>
    </div>

    {{-- Footer Note --}}
    <div class="text-center mt-4">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>

    <style>
        /* Idle: show "Filter". Loading: show "Filtering..." and lock the button. */
        .filter-btn .lbl-busy {
            display: none;
        }

        .filter-btn[data-loading] .lbl-idle {
            display: none;
        }

        .filter-btn[data-loading] .lbl-busy {
            display: inline;
        }

        .filter-btn[data-loading] {
            opacity: .6;
            pointer-events: none;
        }

        .filter-btn {
            min-width: 8.5rem;
        }
    </style>

    <script>
        const initialData = @json($chartData);
        let charts = {};

        function renderCharts(data) {
            ['skillsChart', 'eduChart', 'barangayChart', 'sexChart', 'civilChart', 'ageChart', 'trendChart'].forEach(id => {
                if (charts[id]) charts[id].destroy();
            });

            // Chart A: Horizontal Bar — sorted DESC, accent blue bars
            charts.skillsChart = new Chart(document.getElementById('skillsChart'), {
                type: 'bar',
                data: {
                    labels: data.skills.labels,
                    datasets: [{
                        label: 'Applicants',
                        data: data.skills.data,
                        backgroundColor: '#2563eb',
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.raw} applicants` } }
                    },
                    scales: {
                        x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });

            // Chart B: Donut with right-side legend
            charts.eduChart = new Chart(document.getElementById('eduChart'), {
                type: 'doughnut',
                data: {
                    labels: data.education.labels,
                    datasets: [{
                        data: data.education.data,
                        backgroundColor: ['#1F4E79', '#2563eb', '#60a5fa', '#bfdbfe', '#2e7d32', '#f59e0b', '#e65100'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { font: { size: 11 }, padding: 12, boxWidth: 12 }
                        }
                    },
                    cutout: '60%',
                }
            });

            // Chart C: Vertical bar (multicolor bars per barangay)
            const barangayColors = (data.barangay.labels || []).map((_, i) => {
                const palette = ['#1F4E79', '#2563eb', '#2e7d32', '#00796b', '#e65100', '#ca8a04', '#7c3aed', '#db2777'];
                return palette[i % palette.length];
            });
            charts.barangayChart = new Chart(document.getElementById('barangayChart'), {
                type: 'bar',
                data: {
                    labels: data.barangay.labels,
                    datasets: [{
                        label: 'Applicants',
                        data: data.barangay.data,
                        backgroundColor: barangayColors,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.raw} applicants` } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } }
                    }
                }
            });

            // Chart D: Sex donut
            charts.sexChart = new Chart(document.getElementById('sexChart'), {
                type: 'doughnut',
                data: {
                    labels: data.sex.labels,
                    datasets: [{
                        data: data.sex.data,
                        backgroundColor: ['#2563eb', '#db2777', '#94a3b8'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11 }, padding: 12, boxWidth: 12 }
                        }
                    },
                    cutout: '60%',
                }
            });

            // Chart E: Civil status donut
            charts.civilChart = new Chart(document.getElementById('civilChart'), {
                type: 'doughnut',
                data: {
                    labels: data.civilStatus.labels,
                    datasets: [{
                        data: data.civilStatus.data,
                        backgroundColor: ['#1F4E79', '#2e7d32', '#f59e0b', '#7c3aed'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11 }, padding: 12, boxWidth: 12 }
                        }
                    },
                    cutout: '60%',
                }
            });

            // Chart F: Age groups vertical bar
            charts.ageChart = new Chart(document.getElementById('ageChart'), {
                type: 'bar',
                data: {
                    labels: data.ageGroups.labels,
                    datasets: [{
                        label: 'Applicants',
                        data: data.ageGroups.data,
                        backgroundColor: '#00796b',
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.raw} applicants` } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, precision: 0 } }
                    }
                }
            });

            // Chart G: Registration trend line
            charts.trendChart = new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [{
                        label: 'Registrations',
                        data: data.trend.data,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#2563eb',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.raw} registrations` } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, precision: 0 } }
                    }
                }
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