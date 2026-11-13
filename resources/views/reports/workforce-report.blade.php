<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 34px 40px 52px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f5f9; color: #475569; text-align: left; font-size: 7.5px; text-transform: uppercase;
         letter-spacing: .3px; padding: 6px; border-bottom: 1px solid #cbd5e1; }
    td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; vertical-align: middle; word-wrap: break-word; }
    .plain td { border: none; padding: 0; }
    .info td { border: none; padding: 2px 0; font-size: 8.5px; vertical-align: top; }
    .info .k { color: #64748b; font-weight: bold; }
    .num { text-align: right; }
    .muted { color: #64748b; }
    .small { font-size: 7.5px; }
    .title-bar { background: #1F4E79; color: #ffffff; font-size: 10px; font-weight: bold; padding: 6px 9px;
                 margin-top: 16px; letter-spacing: .3px; }
    .sub { font-size: 9px; font-weight: bold; color: #1F4E79; margin: 10px 0 4px 0; }
    .bar-wrap { background: #e2e8f0; height: 7px; }
    .bar { background: #1F4E79; height: 7px; }
    .card { background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 6px; text-align: center; }
    .card .v { font-size: 16px; font-weight: bold; color: #1F4E79; }
    .card .l { font-size: 7px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; margin-top: 2px; }
    .sign td { border: none; padding: 0 14px 0 0; }
    .line { border-bottom: 1px solid #1e293b; height: 30px; }
    .nobr { page-break-inside: avoid; }
    .pagebreak { page-break-before: always; }
</style>
</head>
<body>

{{-- ═════════════ HEADER ═════════════ --}}
<table class="plain">
    <tr>
        <td style="width:16%; text-align:center;">
            <div style="border:2px solid #1F4E79; color:#1F4E79; font-weight:bold; font-size:14px; padding:10px 0;">PESO</div>
        </td>
        <td style="text-align:center;">
            <div class="small">Republic of the Philippines</div>
            <div class="small">Province of Catanduanes</div>
            <div style="font-size:10px; font-weight:bold; margin-top:3px;">PUBLIC EMPLOYMENT SERVICE OFFICE</div>
            <div style="font-size:14px; font-weight:bold; color:#1F4E79; margin-top:8px;">WORKFORCE REGISTRY REPORT</div>
        </td>
        <td style="width:16%;"></td>
    </tr>
</table>

<table class="info" style="margin-top:12px;">
    <tr>
        <td class="k" style="width:16%;">Generated</td>
        <td style="width:34%;">{{ $r['generatedAt'] }}</td>
        <td class="k" style="width:16%;">Prepared by</td>
        <td style="width:34%;">{{ $r['preparedBy'] }}</td>
    </tr>
    <tr>
        <td class="k">Registrations covered</td>
        <td>{{ $r['coverage'] }}</td>
        <td class="k">Filters applied</td>
        <td>{{ $r['filters'] }}</td>
    </tr>
</table>

{{-- ═════════════ 1. SUMMARY ═════════════ --}}
<div class="title-bar">1. SUMMARY</div>
<table class="plain" style="margin-top:8px;">
    <tr>
        <td style="width:25%; padding-right:6px;">
            <div class="card"><div class="v">{{ number_format($r['total']) }}</div><div class="l">Total registrants</div></div>
        </td>
        <td style="width:25%; padding-right:6px;">
            <div class="card"><div class="v">{{ number_format($r['male']) }}</div><div class="l">Male</div></div>
        </td>
        <td style="width:25%; padding-right:6px;">
            <div class="card"><div class="v">{{ number_format($r['female']) }}</div><div class="l">Female</div></div>
        </td>
        <td style="width:25%;">
            <div class="card"><div class="v">{{ $r['avgAge'] ?? 'NA' }}</div><div class="l">Average age</div></div>
        </td>
    </tr>
</table>

{{-- ═════════════ 2. REGISTRANT PROFILE ═════════════ --}}
<div class="title-bar">2. REGISTRANT PROFILE</div>

<div class="sub">Registration status</div>
@include('reports.partials.distribution', ['rows' => $r['status'], 'label' => 'Status'])

<div class="sub">Sex</div>
@include('reports.partials.distribution', ['rows' => $r['sex'], 'label' => 'Sex'])

<div class="sub">Civil status</div>
@include('reports.partials.distribution', ['rows' => $r['civil'], 'label' => 'Civil status'])

<div class="sub">Age group</div>
@include('reports.partials.distribution', ['rows' => $r['age'], 'label' => 'Age group'])

<div class="sub">Highest educational attainment</div>
@include('reports.partials.distribution', ['rows' => $r['education'], 'label' => 'Education level'])

<div class="sub">Persons with disability (PWD)</div>
@include('reports.partials.distribution', ['rows' => $r['disabilities'], 'label' => 'Disability type'])

{{-- ═════════════ 3. SKILLS ═════════════ --}}
<div class="title-bar">3. SKILLS (TOP 20)</div>
<p class="small muted" style="margin-top:6px;">Each skill is counted once per applicant. Share is the percentage of all registrants in this report.</p>
@include('reports.partials.distribution', ['rows' => $r['skills'], 'label' => 'Skill'])

{{-- ═════════════ 4. BARANGAY ═════════════ --}}
<div class="title-bar">4. REGISTRANTS BY BARANGAY</div>
@include('reports.partials.distribution', ['rows' => $r['barangays'], 'label' => 'Barangay'])

{{-- ═════════════ 5. TREND ═════════════ --}}
<div class="title-bar">5. REGISTRATION TREND</div>
@include('reports.partials.distribution', ['rows' => $r['trend'], 'label' => 'Month registered'])

{{-- ═════════════ 6. APPLICANT LIST (new page) ═════════════ --}}
<div class="title-bar pagebreak">6. APPLICANT LIST</div>
<table style="margin-top:8px;">
    <thead>
        <tr>
            <th style="width:4%;">No.</th>
            <th style="width:16%;">Reference ID</th>
            <th style="width:20%;">Full name</th>
            <th style="width:6%;">Sex</th>
            <th style="width:5%;" class="num">Age</th>
            <th style="width:14%;">Barangay</th>
            <th style="width:16%;">Highest education</th>
            <th style="width:9%;">Status</th>
            <th style="width:10%;">Registered</th>
        </tr>
    </thead>
    <tbody>
        @forelse($r['list'] as $row)
            <tr>
                <td>{{ $row['no'] }}</td>
                <td>{{ $row['ref'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['sex'] }}</td>
                <td class="num">{{ $row['age'] }}</td>
                <td>{{ $row['barangay'] }}</td>
                <td>{{ $row['education'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['registered'] }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No registrations match the selected filters.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ═════════════ 7. CERTIFICATION ═════════════ --}}
<div class="title-bar">7. CERTIFICATION</div>
<p style="font-size:8.5px; margin-top:8px; line-height:1.5;">
    This report was generated from the PESO Catanduanes workforce registry on the date shown above. Figures count
    registrants that match the filters listed in this report. "Not specified" means no value was recorded, and
    "NA" means the value is not held in the registry. Personal data is provided for official employment-service
    purposes only, in accordance with RA 10173.
</p>

<table class="sign" style="margin-top:30px;">
    <tr>
        <td style="width:33%;">
            <div class="line"></div>
            <div class="small">Prepared by</div>
            <div class="small" style="font-weight:bold;">{{ $r['preparedBy'] }}</div>
        </td>
        <td style="width:33%;">
            <div class="line"></div>
            <div class="small">Reviewed by</div>
        </td>
        <td style="width:34%;">
            <div class="line"></div>
            <div class="small">Noted by (PESO Manager)</div>
        </td>
    </tr>
</table>

</body>
</html>
