@php
    $na = fn ($v) => filled($v) ? $v : 'NA';
    $box = fn ($on) => $on ? '☒' : '☐';

    // Disabilities are stored as an array of labels. These must match Profile::DISABILITIES.
    $disabilities = $applicant->disabilities ?? [];
    $hasDis = fn ($label) => in_array($label, $disabilities, true);

    // Education: one row on the paper form, chosen by the stored highest level
    $edu = $applicant->education;
    $eduRow = match ($edu?->highest_level) {
        'Elementary'                                          => 'elem',
        'High School', 'Senior High School'                   => 'sec',
        'Vocational/Technical', 'College Undergraduate',
        'College Graduate'                                    => 'ter',
        'Post-Graduate'                                       => 'grad',
        default                                               => null,
    };

    $trainings = $applicant->trainings->values();
    $works     = $applicant->workExperiences->values();
    $certs     = $applicant->certifications->values();

    // Show at least the paper's number of lines, and more if the applicant has more entries.
    // Nothing stored is dropped.
    $trainingRows = max(4, $trainings->count());
    $certRows     = max(3, $certs->count());
    $workRows     = max(5, $works->count());

    // Checklist items printed on the paper form
    $otherSkillLabels = ['Auto Mechanic','Beautician','Carpentry Work','Computer Literate','Domestic Chores','Driver',
        'Electrician','Embroidery','Gardening','Masonry','Painter/Artist','Painting Jobs',
        'Photography','Plumbing','Sewing Dresses','Stenography','Tailoring'];
    $checklistLower = array_map('mb_strtolower', $otherSkillLabels);

    // Stored skills that match a checklist item (case-insensitive)
    $storedLower = $applicant->skills->map(fn ($s) => mb_strtolower($s->name))->all();

    // Stored skills not on the checklist go into "Others", so no stored skill is lost
    $unmatchedSkills = $applicant->skills
        ->filter(fn ($s) => ! in_array(mb_strtolower($s->name), $checklistLower, true))
        ->pluck('name');
    $othersText = collect([$applicant->other_skills])
        ->merge($unmatchedSkills)
        ->filter()
        ->implode(', ');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { border: 1px solid #000; padding: 7px 8px; vertical-align: top; word-wrap: break-word; overflow: hidden; }
    .head td { border: none; text-align: center; padding: 4px; }
    .sec { background: #d9d9d9; font-weight: bold; font-size: 11px; padding: 6px 8px; letter-spacing: .3px; }
    .label { font-size: 8px; color: #444; display: block; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .2px; }
    .val { font-size: 11px; display: block; min-height: 16px; }
    .small { font-size: 8.5px; line-height: 1.45; }
    .gap { margin-top: 14px; }
    .nob td { border: none; padding: 4px 6px; }
    .sig td { border: none; padding: 0; }
    .sigline { border-bottom: 1px solid #000; height: 28px; }
    .newpage { page-break-before: always; }
</style>
</head>
<body>

{{-- ═════════════════ PAGE 1 ═════════════════ --}}

<table>
    <tr class="head">
        <td style="width:20%; border:1px solid #000; text-align:center;">
            <b>NSRP Form 1</b><br>September 2020
        </td>
        <td style="border:1px solid #000; text-align:center;">
            Republic of the Philippines<br>
            Department of Labor and Employment<br>
            <b style="font-size:12px;">NATIONAL SKILLS REGISTRATION PROGRAM</b><br>
            <b style="font-size:12px;">JOBSEEKER REGISTRATION FORM</b>
        </td>
    </tr>
</table>

<table class="gap">
    <tr><td class="sec" colspan="4">I. PERSONAL INFORMATION</td></tr>

    <tr>
        <td colspan="2"><span class="label">Surname</span><span class="val">{{ $na($applicant->last_name) }}</span></td>
        <td><span class="label">First Name</span><span class="val">{{ $na($applicant->first_name) }}</span></td>
        <td><span class="label">Middle Name</span><span class="val">{{ $na($applicant->middle_name) }}</span></td>
    </tr>

    <tr>
        <td colspan="2"><span class="label">Date of Birth (mm/dd/yyyy)</span>
            <span class="val">{{ $applicant->birthdate?->format('m/d/Y') ?? 'NA' }}</span></td>
        <td colspan="2"><span class="label">Suffix</span><span class="val">NA</span></td>
    </tr>

    <tr>
        <td colspan="2">
            <span class="label">Sex</span>
            <span class="val">{{ $box($applicant->sex === 'Male') }} Male &nbsp;&nbsp; {{ $box($applicant->sex === 'Female') }} Female</span>
        </td>
        <td colspan="2"><span class="label">Religion</span><span class="val">NA</span></td>
    </tr>

    <tr>
        <td colspan="2">
            <span class="label">Civil Status</span>
            <span class="val">
                {{ $box($applicant->civil_status === 'Single') }} Single &nbsp;&nbsp;
                {{ $box($applicant->civil_status === 'Married') }} Married &nbsp;&nbsp;
                {{ $box($applicant->civil_status === 'Widowed') }} Widowed &nbsp;&nbsp;
                {{ $box($applicant->civil_status === 'Separated') }} Separated
            </span>
        </td>
        <td colspan="2"><span class="label">TIN</span><span class="val">NA</span></td>
    </tr>

    <tr><td class="sec" colspan="4">PRESENT ADDRESS</td></tr>
    <tr>
        <td colspan="4"><span class="label">House No. / Street / Village</span><span class="val">{{ $na($applicant->address) }}</span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="label">Barangay</span><span class="val">{{ $na($applicant->barangay?->name) }}</span></td>
        <td colspan="2"><span class="label">Municipality / City</span><span class="val">{{ $na($applicant->barangay?->municipality?->name) }}</span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="label">Province</span><span class="val">Catanduanes</span></td>
        <td colspan="2"><span class="label">Height (ft.)</span><span class="val">NA</span></td>
    </tr>

    <tr>
        <td colspan="4">
            <span class="label">Disability</span>
            <span class="val">
                @foreach(['Visual','Speech','Mental','Hearing','Physical','Others'] as $d)
                    {{ $box($hasDis($d)) }} {{ $d }} &nbsp;&nbsp;&nbsp;
                @endforeach
            </span>
        </td>
    </tr>

    <tr>
        <td colspan="2"><span class="label">Contact Number(s)</span><span class="val">{{ $na($applicant->contact_number) }}</span></td>
        <td colspan="2"><span class="label">E-mail</span><span class="val">{{ $na($applicant->email) }}</span></td>
    </tr>
</table>

<table class="gap">
    <tr><td class="sec" colspan="2">II. JOB PREFERENCE</td></tr>
    <tr>
        <td><span class="label">Preferred Occupation</span><span class="val">NA</span></td>
        <td><span class="label">Preferred Work Location</span><span class="val">NA</span></td>
    </tr>
</table>

<table class="gap">
    <tr><td class="sec" colspan="5">III. LANGUAGE / DIALECT PROFICIENCY</td></tr>
    <tr>
        <td style="width:28%;"><b>Language / Dialect</b></td>
        <td style="width:18%;"><b>Read</b></td>
        <td style="width:18%;"><b>Write</b></td>
        <td style="width:18%;"><b>Speak</b></td>
        <td style="width:18%;"><b>Understand</b></td>
    </tr>
    @foreach(['English', 'Filipino', 'Mandarin'] as $lang)
        <tr>
            <td>{{ $lang }}</td><td>NA</td><td>NA</td><td>NA</td><td>NA</td>
        </tr>
    @endforeach
</table>

<table class="gap">
    <tr><td class="sec" colspan="4">IV. EDUCATIONAL BACKGROUND</td></tr>
    <tr>
        <td style="width:24%;"><b>Level</b></td>
        <td style="width:40%;"><b>Course</b></td>
        <td style="width:18%;"><b>Year Graduated</b></td>
        <td style="width:18%;"><b>Level Reached / Year Last Attended</b></td>
    </tr>
    @foreach(['elem' => 'Elementary', 'sec' => 'Secondary', 'ter' => 'Tertiary', 'grad' => 'Graduate Studies / Post-graduate'] as $key => $label)
        <tr>
            <td>{{ $label }}</td>
            @if($eduRow === $key)
                <td>{{ $na($edu->course_program) }}</td>
                <td>{{ $na($edu->year_graduated) }}</td>
                <td>NA</td>
            @else
                <td></td><td></td><td></td>
            @endif
        </tr>
    @endforeach
</table>

<table class="gap">
    <tr><td class="sec" colspan="5">V. TECHNICAL / VOCATIONAL AND OTHER TRAINING</td></tr>
    <tr>
        <td style="width:34%;"><b>Training / Vocational Course</b></td>
        <td style="width:12%;"><b>Hours</b></td>
        <td style="width:24%;"><b>Training Institution</b></td>
        <td style="width:15%;"><b>Skills Acquired</b></td>
        <td style="width:15%;"><b>Certificates Received</b></td>
    </tr>
    @for($i = 0; $i < $trainingRows; $i++)
        @php $t = $trainings[$i] ?? null; @endphp
        <tr>
            <td>{{ $i + 1 }}. {{ $na($t?->title) }}</td>
            <td>NA</td>
            <td>{{ $na($t?->provider) }}</td>
            <td>NA</td>
            <td>NA</td>
        </tr>
    @endfor
</table>

{{-- ═════════════════ PAGE 2 ═════════════════ --}}
<div class="newpage">

<table>
    <tr><td class="sec" colspan="4">VI. ELIGIBILITY / PROFESSIONAL LICENSE</td></tr>
    <tr>
        <td style="width:40%;"><b>Eligibility (Civil Service)</b></td>
        <td style="width:20%;"><b>Date Taken</b></td>
        <td style="width:24%;"><b>Professional License (PRC)</b></td>
        <td style="width:16%;"><b>Valid Until</b></td>
    </tr>
    @for($i = 0; $i < $certRows; $i++)
        @php $c = $certs[$i] ?? null; @endphp
        <tr>
            <td>{{ $i + 1 }}. {{ $na($c?->name) }}</td>
            <td>{{ $na($c?->year_issued) }}</td>
            <td>NA</td>
            <td>{{ $na($c?->expiry_year) }}</td>
        </tr>
    @endfor
</table>

<table class="gap">
    <tr><td class="sec" colspan="4">VII. WORK EXPERIENCE (most recent first)</td></tr>
    <tr>
        <td style="width:34%;"><b>Company Name</b></td>
        <td style="width:30%;"><b>Position</b></td>
        <td style="width:21%;"><b>Duration</b></td>
        <td style="width:15%;"><b>Status</b></td>
    </tr>
    @for($i = 0; $i < $workRows; $i++)
        @php $w = $works[$i] ?? null; @endphp
        <tr>
            <td>{{ $na($w?->company_name) }}</td>
            <td>{{ $na($w?->position) }}</td>
            <td>{{ $na($w?->duration) }}</td>
            <td>NA</td>
        </tr>
    @endfor
</table>

<table class="gap">
    <tr><td class="sec" colspan="3">VIII. OTHER SKILLS ACQUIRED WITHOUT CERTIFICATE</td></tr>
    <tr><td colspan="3">
        <table class="nob">
            @foreach(array_chunk($otherSkillLabels, 3) as $row)
                <tr>
                    @foreach($row as $label)
                        <td>{{ $box(in_array(mb_strtolower($label), $storedLower, true)) }} {{ $label }}</td>
                    @endforeach
                    @for($p = count($row); $p < 3; $p++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
        <div style="margin-top:8px;">{{ $box(filled($othersText)) }} Others: {{ $na($othersText) }}</div>
    </td></tr>
</table>

<table class="gap">
    <tr><td class="sec">CERTIFICATION / AUTHORIZATION</td></tr>
    <tr><td class="small">
        This is to certify that all data/information that I have provided in this form are true to the best of my knowledge.
        This is also to authorize DOLE to include my profile in the PESO Employment Information System and use my personal
        information for employment facilitation. I am also aware that DOLE is not obliged to seek employment on my behalf.
    </td></tr>
    <tr><td>
        <table class="sig">
            <tr>
                <td style="width:60%; padding-right:30px;"><div class="sigline"></div><span class="small">Signature of Applicant</span></td>
                <td style="width:40%;"><div class="sigline"></div><span class="small">Date</span></td>
            </tr>
        </table>
    </td></tr>
    <tr><td class="small">
        <b>FOR USE OF PESO ONLY. DO NOT WRITE BELOW THIS LINE.</b><br><br>
        Referred to: {{ $box(false) }} SPES &nbsp;&nbsp; {{ $box(false) }} DILEEP &nbsp;&nbsp; {{ $box(false) }} GIP &nbsp;&nbsp;
        {{ $box(false) }} TESDA Training &nbsp;&nbsp; {{ $box(false) }} TUPAD &nbsp;&nbsp; {{ $box(false) }} JobStart
        <br><br>
        <table class="sig">
            <tr>
                <td style="width:55%; padding-right:30px;"><div class="sigline"></div><span class="small">Assessed by (signature over printed name)</span></td>
                <td style="width:45%;"><div class="sigline"></div><span class="small">Date</span></td>
            </tr>
        </table>
    </td></tr>
</table>

</div>

</body>
</html>