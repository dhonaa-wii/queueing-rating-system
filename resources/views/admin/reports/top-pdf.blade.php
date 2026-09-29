<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @php
        $isGroups = $type === 'groups';
        $heading = $isGroups ? 'Top 5 Best Groups' : 'Top 5 Best Presenters';
    @endphp
    <title>{{ $category->name }} — {{ $heading }}</title>
    {{-- Rendered by Dompdf (CSS 2.1 only), same chrome as grades-pdf. --}}
    <style>
        @page { margin: 14mm 12mm 20mm 12mm; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
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

        .heading { text-align: center; margin-bottom: 14px; }
        .eyebrow { font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #C1712E; }
        .heading h1 { margin: 3px 0 2px; font-size: 15px; }
        .heading .sub { color: #6B6560; }
        .heading .filters { margin-top: 3px; color: #6B6560; font-size: 8.5px; }

        table.top thead { display: table-header-group; }
        table.top th {
            padding: 7px 6px;
            text-align: left;
            font-size: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6B6560;
            border-top: 1px solid #1F1D1A;
            border-bottom: 1px solid #1F1D1A;
        }
        table.top td { padding: 8px 6px; border-bottom: 1px solid #E8E1D9; vertical-align: middle; }
        table.top tr { page-break-inside: avoid; }
        table.top .rank { width: 7%; text-align: center; }
        table.top .num { width: 14%; text-align: right; }
        table.top td.rank, table.top td.num { font-weight: bold; font-size: 12px; }
        table.top .ref { color: #C1712E; font-size: 8px; font-weight: bold; letter-spacing: 0.5px; }
        table.top .name { font-weight: bold; }
        .muted { color: #6B6560; font-size: 8.5px; }
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
        <div class="eyebrow">{{ $isGroups ? 'Ranked by Group Grade' : 'Ranked by Individual Grade Avg.' }}</div>
        <h1>{{ $heading }}</h1>
        <div class="sub">{{ $category->name }} &middot; {{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }}</div>
        <div class="filters">Generated {{ $generatedAt }}</div>
    </div>

    <table class="top">
        <thead>
            <tr>
                <th class="rank">Rank</th>
                <th>{{ $isGroups ? 'Group' : 'Presenter' }}</th>
                <th>{{ $isGroups ? 'Members' : 'Project Title' }}</th>
                <th class="num">{{ $isGroups ? 'Group Grade' : 'Individual Avg.' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $item)
                <tr>
                    <td class="rank">{{ $item->rank }}</td>
                    <td>
                        @if ($isGroups)
                            <div class="ref">{{ $item->group_reference }}</div>
                            <div class="name">{{ $item->project_title ?? '—' }}</div>
                        @else
                            <div class="ref">{{ $item->group_reference }}@if ($item->section) &middot; {{ $item->section }}@endif</div>
                            <div class="name">{{ $item->name }}</div>
                        @endif
                    </td>
                    <td class="muted">{{ $isGroups ? $item->members->implode(', ') : ($item->project_title ?? '—') }}</td>
                    <td class="num">{{ number_format($isGroups ? $item->group_grade : $item->individual_avg, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">Nothing to rank yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
