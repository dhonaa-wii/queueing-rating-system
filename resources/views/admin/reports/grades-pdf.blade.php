<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $category->name }} — Generated Grades</title>
    {{-- Rendered by Dompdf (CSS 2.1 only: no flex, grid or custom properties),
         so layout is tables and colours are literals. The footer is
         position: fixed, which Dompdf repeats on every page; @page's bottom
         margin is what keeps the table clear of it. --}}
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

        .count { margin-bottom: 4px; font-size: 8.5px; color: #6B6560; }

        table.grades thead { display: table-header-group; }
        table.grades th {
            padding: 6px 6px;
            text-align: left;
            font-size: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6B6560;
            border-top: 1px solid #1F1D1A;
            border-bottom: 1px solid #1F1D1A;
        }
        table.grades td { padding: 6px 6px; border-bottom: 1px solid #E8E1D9; vertical-align: middle; }
        table.grades tr { page-break-inside: avoid; }

        table.grades .num { text-align: right; width: 10%; }
        table.grades .center { text-align: center; }
        .muted { color: #9A948E; }
        .pass { color: #2F7A4B; font-weight: bold; }
        .fail { color: #B3372F; font-weight: bold; }
        .paid { color: #2F7A4B; }
        .attempt { color: #6B6560; font-size: 8px; }
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
        <div class="eyebrow">Generated Grades</div>
        <h1>{{ $category->name }}</h1>
        <div class="sub">{{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }} &middot; {{ $category->college->name ?? '—' }}</div>
        <div class="filters">{{ trim(($filterLabel ? $filterLabel . '   ·   ' : '') . 'Generated ' . $generatedAt) }}</div>
    </div>

    <div class="count">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('student', $rows->count()) }}</div>

    <table class="grades">
        <thead>
            <tr>
                <th>Name</th>
                <th>Section</th>
                <th>Track</th>
                @foreach ($columns as $key)
                    <th class="{{ in_array($key, ['individual_avg', 'group_grade', 'total'], true) ? 'num' : (in_array($key, ['group', 'remarks'], true) ? '' : 'center') }}">{{ $columnLabels[$key] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['section'] ?? '—' }}</td>
                    <td>{{ $row['track_name'] ?? '—' }}</td>
                    @foreach ($columns as $key)
                        @switch($key)
                            @case('group')
                                <td>{{ $row['group_reference'] }}</td>
                                @break
                            @case('individual_avg')
                            @case('group_grade')
                            @case('total')
                                <td class="num">{{ $row[$key] !== null ? number_format($row[$key], 2) : '—' }}</td>
                                @break
                            @case('remarks')
                                <td>
                                    @if ($row['outcome'])
                                        <span class="{{ $row['outcome_failed'] ? 'fail' : 'pass' }}">{{ $row['outcome'] }}</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                @break
                            @case('attempt')
                                <td class="center">{{ $row['attempt_number'] }}</td>
                                @break
                            @default
                                <td class="center">
                                    @if ($row['payment'][(int) substr($key, 4)] ?? false)
                                        <span class="paid">Paid</span>
                                    @else
                                        <span class="muted">Unpaid</span>
                                    @endif
                                </td>
                        @endswitch
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + count($columns) }}" class="empty">No completed groups match this filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
