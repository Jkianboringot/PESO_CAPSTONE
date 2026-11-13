@php
    $logoPath = public_path('pesoLogoLogin.png');
    $logo = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px 48px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #000; line-height: 1.3; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; word-wrap: break-word; }
    th { font-size: 8px; font-weight: bold; text-align: center; }
    td.num { text-align: center; font-size: 8px; }
    td.lbl { font-size: 8.5px; }
    .noborder td { border: none; padding: 0; }
    .small { font-size: 7.5px; color: #333; }
    .note { font-size: 7.5px; color: #333; margin-top: 8px; line-height: 1.4; }
    .sigline { border-bottom: 1px solid #000; height: 26px; }
    .sign td { border: none; padding: 0 12px 0 0; }
    .section-row td { background: #f1f5f9; }
</style>
</head>
<body>

{{-- ═════════════ HEADER BLOCK ═════════════ --}}
<table>
    <tr>
        <td style="width:20%; text-align:center;">
            @if($logo)<img src="{{ $logo }}" alt="PESO" style="height:44px;"><br>@endif
            <b style="font-size:8px;">PESO MONTHLY REPORT</b>
        </td>
        <td style="width:58%; text-align:center;">
            Republic of the Philippines<br>
            Department of Labor and Employment<br>
            <b style="font-size:10px;">PUBLIC EMPLOYMENT SERVICE OFFICE</b><br>
            Province of Catanduanes &nbsp;&nbsp; LGU: ______________________
        </td>
        <td style="width:22%; text-align:center;">
            <div class="small">Reference Month / Year</div>
            <b style="font-size:10px;">{{ $monthYear }}</b>
        </td>
    </tr>
    <tr>
        <td colspan="3"><b>Name and Address of PESO:</b> PESO, Province of Catanduanes</td>
    </tr>
    <tr>
        <td colspan="3" class="small">Period covered: {{ $periodLabel }} &nbsp;&nbsp;|&nbsp;&nbsp; Generated: {{ $generatedAt }}</td>
    </tr>
</table>

{{-- ═════════════ MAIN GRID ═════════════ --}}
<table style="margin-top:8px;">
    <thead>
        <tr>
            <th rowspan="2" style="width:34%; text-align:left;">PROGRAM</th>
            <th colspan="3">CURRENT REPORTING PERIOD</th>
            <th colspan="3">TOTAL as of this Period</th>
        </tr>
        <tr>
            <th style="width:11%;">MALE</th>
            <th style="width:11%;">FEMALE</th>
            <th style="width:11%;">TOTAL</th>
            <th style="width:11%;">MALE</th>
            <th style="width:11%;">FEMALE</th>
            <th style="width:11%;">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr @if($row['values'] === null) class="section-row" @endif>
                <td class="lbl"
                    style="padding-left: {{ 5 + $row['level'] * 12 }}px; font-weight: {{ $row['values'] === null ? 'bold' : 'normal' }};">
                    {{ $row['label'] }}
                </td>
                @if($row['values'] === null)
                    <td colspan="6"></td>
                @else
                    @foreach($row['values'] as $v)
                        <td class="num">{{ number_format($v) }}</td>
                    @endforeach
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

<p class="note">
    Only items recorded in the PESO registry are shown. Counts cover active applicants that match the filters on
    the report screen. Section B counts each applicant once per skill, split by sex.
</p>

{{-- ═════════════ SIGNATURES ═════════════ --}}
<table class="sign" style="margin-top:26px;">
    <tr>
        <td style="width:33%;">
            <div class="sigline"></div>
            <div class="small">Prepared by: {{ $preparedBy }}</div>
        </td>
        <td style="width:33%;">
            <div class="sigline"></div>
            <div class="small">Reviewed by</div>
        </td>
        <td style="width:34%;">
            <div class="sigline"></div>
            <div class="small">Noted by (PESO Manager)</div>
        </td>
    </tr>
</table>

</body>
</html>
