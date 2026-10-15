<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Recommendation Form — {{ $form->group_reference }}</title>
    {{-- One group's Recommendation Form, laid out like the college's printed
         paper (user-directed 2026-10-15). Served two ways from this one view:
         rendered by Dompdf for Export (CSS 2.1 only: tables, literal colours)
         and as HTML for Print, where $forPrint zeroes the page margin so the
         browser adds no header/footer of its own and the body pads instead. --}}
    @php
        $forPrint = $forPrint ?? false;
        $category = $form->category;
        $lines = array_values(array_filter([$letterhead?->line_1, $letterhead?->line_2, $letterhead?->line_3, $letterhead?->line_4]));
        $logo = $letterhead?->logoDataUri('logo_path');
        $secondaryLogo = $letterhead?->logoDataUri('secondary_logo_path');
        $researcherRows = max(3, $form->researchers->count());
        $subtitle = implode(' – ', array_filter([
            $category->subject_or_research_type,
            $category->semester->name ?? null,
            ($category->academicYear->name ?? null) ? 'SY ' . $category->academicYear->name : null,
        ]));
        $signers = $form->signers->isNotEmpty()
            ? $form->signers
            : collect([(object) ['name' => null, 'is_chair' => false], (object) ['name' => null, 'is_chair' => true], (object) ['name' => null, 'is_chair' => false]]);
    @endphp
    <style>
        @if ($forPrint)
            @page { size: A4 portrait; margin: 0; }
            body { padding: 12mm 14mm; }
        @else
            @page { margin: 12mm 14mm; }
        @endif

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: #111;
            background: #fff;
        }

        table { width: 100%; border-collapse: collapse; }

        .letterhead td { text-align: center; vertical-align: middle; padding: 0 0 6px; }
        .letterhead .logo-cell { width: 70px; }
        .letterhead img { width: 60px; height: 60px; }
        .letterhead .line { font-size: 9.5px; line-height: 1.35; }
        .letterhead .line-first { font-size: 10.5px; font-weight: bold; }

        .rule { border-top: 1.5px solid #111; border-bottom: 1px solid #111; height: 3px; margin-bottom: 10px; }

        .heading { text-align: center; margin-bottom: 10px; }
        .heading h1 { margin: 0; font-size: 14px; letter-spacing: 0.5px; }
        .heading .sub { font-size: 10.5px; font-weight: bold; margin-top: 2px; }
        .heading .sub-2 { font-size: 9.5px; margin-top: 1px; }

        table.info td { border: 1.2px solid #111; padding: 3px 6px; vertical-align: middle; }
        table.info .label { width: 17%; text-align: center; }
        table.info .field-name { font-weight: normal; }

        table.rec { margin-top: 12px; }
        table.rec th, table.rec td { border: 1.2px solid #111; padding: 3px 6px; }
        table.rec th { text-align: left; font-size: 10px; border-bottom: 2.5px solid #111; }
        table.rec .no { width: 7%; text-align: center; }
        table.rec td { height: 17px; vertical-align: top; }
        table.rec tr { page-break-inside: avoid; }

        .assessed { margin-top: 14px; border-top: 1.5px solid #111; padding-top: 6px; page-break-inside: avoid; }
        .assessed-title { text-align: center; text-decoration: underline; margin-bottom: 4px; }
        table.signers td { border: 1.2px solid #111; padding: 30px 8px 6px; text-align: center; vertical-align: bottom; }
        .signer-name { font-weight: bold; min-height: 12px; }
        .signer-line { border-top: 1px solid #111; margin: 2px 6px 2px; }
        .signer-role { font-size: 9px; }
        .signer-hint { font-size: 8.5px; text-decoration: underline; }
    </style>
</head>
<body>
    @if ($letterhead)
        <table class="letterhead">
            <tr>
                <td class="logo-cell">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
                <td>
                    @foreach ($lines as $line)
                        <div class="line {{ $loop->first ? 'line-first' : '' }}">{{ $line }}</div>
                    @endforeach
                </td>
                <td class="logo-cell">@if ($secondaryLogo)<img src="{{ $secondaryLogo }}" alt="">@endif</td>
            </tr>
        </table>
        <div class="rule"></div>
    @endif

    <div class="heading">
        <h1>RECOMMENDATION FORM</h1>
        @if ($subtitle !== '')
            <div class="sub">({{ $subtitle }})</div>
        @endif
        <div class="sub-2">({{ $category->name }})</div>
    </div>

    <table class="info">
        <tr>
            <td class="label" rowspan="3">Research</td>
            <td colspan="2">Title: {{ $form->title }}</td>
        </tr>
        <tr><td colspan="2">Respondent:</td></tr>
        <tr><td colspan="2">Address:</td></tr>
        @for ($i = 0; $i < $researcherRows; $i++)
            <tr>
                @if ($i === 0)
                    <td class="label" rowspan="{{ $researcherRows }}">Researcher/s</td>
                @endif
                <td colspan="2">{{ $i + 1 }}. {{ $form->researchers[$i] ?? '' }}</td>
            </tr>
        @endfor
        <tr>
            <td class="label">Course</td>
            <td style="width: 53%;"></td>
            <td>Date of Defense: {{ $form->defended_at?->format('F j, Y') }}</td>
        </tr>
    </table>

    <table class="rec">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Recommendations</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $form->lines; $i++)
                <tr>
                    <td class="no">{{ isset($form->recommendations[$i]) ? $i + 1 : '' }}</td>
                    <td>{{ $form->recommendations[$i] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="assessed">
        <div class="assessed-title">Assessed/Recommended by:</div>
        <table class="signers">
            <tr>
                @foreach ($signers as $signer)
                    <td style="width: {{ round(100 / $signers->count(), 2) }}%;">
                        <div class="signer-name">{{ $signer->name }}</div>
                        <div class="signer-line"></div>
                        <div class="signer-role">({{ $signer->is_chair ? 'Panel Chairman' : 'Panel Member' }})</div>
                        <div class="signer-hint">(Signature over Printed Name)</div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</body>
</html>
