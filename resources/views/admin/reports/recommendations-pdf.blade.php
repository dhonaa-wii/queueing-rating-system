<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $category->name }} — Recommendations Summary</title>
    {{-- Rendered by Dompdf (CSS 2.1 only), same conventions as
         grades-pdf.blade.php: tables and literal colours, a fixed footer that
         repeats on every page, @page's bottom margin keeping rows clear of it. --}}
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
        table.rec td { padding: 6px 6px; border: 1px solid #CFC7BD; vertical-align: top; }
        table.rec tr { page-break-inside: avoid; }

        .seq { text-align: center; font-weight: bold; }
        .ref { font-weight: bold; color: #C1712E; }
        .title { font-weight: bold; margin: 1px 0 3px; }
        .muted { color: #9A948E; }
        .attempt { color: #6B6560; font-size: 8px; }
        .comment { margin-bottom: 4px; }
        .panelist { font-weight: bold; }
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
        <div class="eyebrow">Summary of Recommendations &amp; Comments</div>
        <h1>{{ $category->name }}</h1>
        <div class="sub">{{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }} &middot; {{ $category->college->name ?? '—' }}</div>
        <div class="filters">Generated {{ $generatedAt }}</div>
    </div>

    <div class="count">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('group', $rows->count()) }} completed</div>

    <table class="rec">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">#</th>
                <th style="width: 14%;">Submitted</th>
                <th style="width: 32%;">Proponents</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="seq">{{ $row->sequence }}</td>
                    <td>
                        {{ $row->completed_at?->format('M j, Y') ?? '—' }}<br>
                        <span class="muted">{{ $row->completed_at?->format('g:i A') }}</span>
                    </td>
                    <td>
                        <span class="ref">{{ $row->group_reference }}</span>
                        @if ($row->attempt_number > 1)
                            <span class="attempt">Attempt {{ $row->attempt_number }}</span>
                        @endif
                        @if ($row->project_title)
                            <div class="title">{{ $row->project_title }}</div>
                        @endif
                        @foreach ($row->proponents as $proponent)
                            <div>{{ $proponent->name }} <span class="muted">&middot; {{ $proponent->section ?? '—' }}</span></div>
                        @endforeach
                    </td>
                    <td>
                        @forelse ($row->comments as $comment)
                            <div class="comment"><span class="panelist">{{ $comment->panelist }}:</span> {{ $comment->comment }}</div>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">No presentations have been completed yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
