<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#128196; Report Generation</h5>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-bold py-3" style="background:#f8f9fa">
        Configure Report Parameters
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date From</label>
                <input type="date" wire:model="dateFrom" class="form-control @error('dateFrom') is-invalid @enderror">
                @error('dateFrom')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date To</label>
                <input type="date" wire:model="dateTo" class="form-control @error('dateTo') is-invalid @enderror">
                @error('dateTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Barangay</label>
                <select wire:model="barangayId" class="form-select">
                    <option value="">All Barangays</option>
                    @foreach($barangays as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Skill Category</label>
                <select wire:model="categoryId" class="form-select">
                    <option value="">All Categories</option>
                    @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Education Level</label>
                <select wire:model="educLevel" class="form-select">
                    <option value="">All Levels</option>
                    @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $l)
                        <option>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Sex</label>
                <select wire:model="sex" class="form-select">
                    <option value="">All</option>
                    <option>Male</option><option>Female</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Export Format</label>
                <select wire:model="format" class="form-select">
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="csv">CSV (.csv)</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center py-3">
        <small class="text-muted">Report columns are aligned with DOLE BLE submission format.</small>
        <button wire:click="generate" class="btn fw-semibold text-white px-4" style="background:#1F4E79">
            <span wire:loading wire:target="generate">&#8987; Generating...</span>
            <span wire:loading.remove wire:target="generate">&#8659; Download Report</span>
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header fw-bold py-2" style="background:#f8f9fa">Report Columns Included</div>
    <div class="card-body">
        <div class="row g-1 small text-muted">
            @foreach(['Reference ID','Last Name','First Name','Middle Name','Birthdate','Age','Sex','Civil Status','Contact Number','Email','Address','Barangay','Municipality','Highest Education','Course/Program','Skills','Skill Categories','Registration Status','Date Registered'] as $col)
                <div class="col-md-3"><span class="badge bg-light text-dark border">{{ $col }}</span></div>
            @endforeach
        </div>
    </div>
</div>
</div>
