<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $category->name }} — Panelist Sign-off Sheet</title>
    {{-- Rendered by Dompdf (CSS 2.1 only), same conventions as
         recommendations-pdf.blade.php: tables and literal colours, a fixed
         footer that repeats on every page. --}}
    <style>
        @page { margin: 14mm 12mm 20mm 12mm; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #1F1D1A;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -12mm;
            text-align: center;
            font-size: 8px;
            color: #9A948E;
        }

        table { width: 100%; border-collapse: collapse; }

        .letterhead { margin-bottom: 14px; border-bottom: 2px solid #1F1D1A; }
        .letterhead td { padding: 0 0 8px; vertical-align: middle; text-align: center; }
        .letterhead .logo-cell { width: 66px; }
        .letterhead img { width: 58px; height: 58px; }
        .letterhead .line { font-size: 9px; line-height: 1.35; }
        .letterhead .line-first { font-size: 10.5px; font-weight: bold; }

        .heading { text-align: center; margin-bottom: 12px; }
        .eyebrow { font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #C1712E; }
        .heading h1 { margin: 3px 0 2px; font-size: 15px; }
        .heading .sub { color: #6B6560; }
        .heading .filters { margin-top: 3px; color: #6B6560; font-size: 8.5px; }

        table.meta { margin-bottom: 12px; border: 1px solid #CFC7BD; }
        table.meta td { width: 20%; padding: 6px 7px; vertical-align: top; border: 1px solid #CFC7BD; }
        table.meta .label { font-size: 7.5px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; color: #6B6560; }
        table.meta .value { margin-top: 2px; font-size: 9.5px; font-weight: bold; }

        table.rec { border: 1px solid #CFC7BD; }
        table.rec thead { display: table-header-group; }
        table.rec th {
            padding: 6px 6px;
            text-align: left;
            font-size: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6B6560;
            background: #F3EEE8;
            border: 1px solid #CFC7BD;
        }
        table.rec td { padding: 9px 6px; border: 1px solid #CFC7BD; vertical-align: middle; }
        table.rec tr { page-break-inside: avoid; }

        .seq { text-align: center; font-weight: bold; }
        .name { font-weight: bold; }
        .groups { text-align: center; font-weight: bold; font-size: 10px; }
        .sign { height: 20px; border-bottom: 1px solid #1F1D1A; }
        .empty { text-align: center; padding: 22px 6px; color: #6B6560; }
    </style>
</head>
<body>
    <div class="footer">{{ $footerText }}</div>

    @if ($letterhead)
        <table class="letterhead">
            <tr>
                <td class="logo-cell">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
                <td>
                    @foreach (array_values(array_filter([$letterhead->line_1, $letterhead->line_2, $letterhead->line_3, $letterhead->line_4])) as $line)
                        <div class="line {{ $loop->first ? 'line-first' : '' }}">{{ $line }}</div>
                    @endforeach
                </td>
                <td class="logo-cell">@if ($secondaryLogo)<img src="{{ $secondaryLogo }}" alt="">@endif</td>
            </tr>
        </table>
    @endif

    <div class="heading">
        <div class="eyebrow">Panelist Sign-off Sheet</div>
        <h1>{{ $category->name }}</h1>
        <div class="sub">{{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }} &middot; {{ $category->college->name ?? '—' }}</div>
        <div class="filters">Generated {{ $generatedAt }}</div>
    </div>

    @php
        $dateLabel = $sheet->start
            ? ($sheet->start->isSameDay($sheet->end)
                ? $sheet->start->format('M j, Y')
                : $sheet->start->format('M j, Y') . ' – ' . $sheet->end->format('M j, Y'))
            : '—';
    @endphp

    <table class="meta">
        <tr>
            <td><div class="label">Presentation Date</div><div class="value">{{ $dateLabel }}</div></td>
            <td><div class="label">Days</div><div class="value">{{ $sheet->day_count }}</div></td>
            <td><div class="label">Rooms</div><div class="value">{{ $sheet->rooms->implode(', ') ?: '—' }}</div></td>
            <td><div class="label">Total Panelists</div><div class="value">{{ $sheet->panelist_count }}</div></td>
            <td><div class="label">Groups Evaluated</div><div class="value">{{ $sheet->group_count }}</div></td>
        </tr>
    </table>

    <table class="rec">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th>Panelist</th>
                <th style="width: 14%; text-align: center;">Groups</th>
                <th style="width: 34%;">Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sheet->rows as $row)
                <tr>
                    <td class="seq">{{ $loop->iteration }}</td>
                    <td class="name">{{ $row->name }}</td>
                    <td class="groups">{{ $row->group_count }}</td>
                    <td><div class="sign"></div></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">No evaluations have been submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
