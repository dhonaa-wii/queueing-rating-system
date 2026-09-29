<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panelist Sign-off Sheet &middot; {{ $category->name }}</title>
    {{-- Standalone print page (not the admin layout) so the sheet prints as
         plain paper — always light, no navigation chrome. Reports embeds this
         same page in an iframe (?embed=1) for its preview modal, which drops
         the toolbar and prints through the frame. --}}
    <style>
        :root {
            --ink: #1F1D1A;
            --muted: #6B6560;
            --rule: #D9D2CA;
            --rule-soft: #ECE6DF;
            --accent: #C1712E;
            --paper: #FFFFFF;
            --desk: #F1ECE6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--desk);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(6px);
            border-bottom: 1px solid var(--rule-soft);
        }

        .toolbar-title { font-weight: 600; font-size: 14px; }

        .toolbar-actions { display: flex; gap: 8px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--rule);
            background: var(--paper);
            color: var(--ink);
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn svg { width: 15px; height: 15px; }

        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto 48px;
            padding: 16mm 14mm;
            background: var(--paper);
            box-shadow: 0 2px 6px rgba(43, 41, 38, 0.06), 0 16px 40px rgba(43, 41, 38, 0.10);
            border-radius: 4px;
        }

        .letterhead {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--ink);
        }

        .letterhead-text { font-size: 12px; line-height: 1.3; }

        .letterhead-logo { width: 62px; height: 62px; object-fit: contain; flex: 0 0 auto; }

        .letterhead-text div:first-child { font-weight: 700; font-size: 13px; }

        .sheet-heading { margin: 22px 0 18px; text-align: center; }

        .eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .sheet-heading h1 { margin: 4px 0 2px; font-size: 20px; letter-spacing: -0.01em; }

        .sheet-heading p { margin: 0; color: var(--muted); }

        .meta {
            display: grid;
            grid-template-columns: repeat(var(--meta-cols, 4), minmax(0, 1fr));
            border: 1px solid var(--rule);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .meta div { padding: 10px 12px; border-right: 1px solid var(--rule-soft); }

        .meta div:last-child { border-right: 0; }

        .meta dt {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta dd { margin: 2px 0 0; font-weight: 600; font-size: 13px; }

        table { width: 100%; border-collapse: collapse; }

        thead th {
            padding: 9px 10px;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: left;
            color: var(--muted);
            border-top: 1px solid var(--ink);
            border-bottom: 1px solid var(--ink);
        }

        tbody td {
            padding: 14px 10px;
            border-bottom: 1px solid var(--rule-soft);
            vertical-align: middle;
        }

        tbody tr { page-break-inside: avoid; break-inside: avoid; }

        .col-num { width: 28px; color: var(--muted); }

        .col-center { text-align: center; }

        .col-nowrap { white-space: nowrap; }

        .panelist-name { font-weight: 600; }

        .panelist-affiliation { font-size: 11px; color: var(--muted); }

        .groups-count { font-size: 15px; font-weight: 700; }

        .col-groups { width: 90px; }

        .col-signature { width: 34%; }

        /* Same bordered grid as the recommendations summary. */
        table.grid { border: 1px solid var(--rule); }
        table.grid thead th { background: #F3EEE8; border: 1px solid var(--rule); }
        table.grid tbody td { border: 1px solid var(--rule); }

        .signature-line { height: 30px; border-bottom: 1px solid var(--ink); }

        .empty-row { text-align: center; color: var(--muted); padding: 28px 10px; }

        .sheet-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            font-size: 11px;
            color: var(--muted);
        }

        @page { size: A4 portrait; margin: 14mm 12mm; }

        @media print {
            body { background: var(--paper); font-size: 12px; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; border-radius: 0; }
        }

        @media (max-width: 860px) {
            .sheet { width: auto; min-height: 0; margin: 16px; padding: 20px; }
            .meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .meta div:nth-child(2n) { border-right: 0; }
            .meta div { border-bottom: 1px solid var(--rule-soft); }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    @unless ($embed ?? false)
        <div class="toolbar">
            <span class="toolbar-title">Panelist Sign-off Sheet</span>
            <div class="toolbar-actions">
                <a href="{{ route('admin.reports.grades', $category) }}" class="btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    Back
                </a>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print
                </button>
            </div>
        </div>
    @endunless

    <main class="sheet">
        @if ($letterhead && $letterhead->hasContent())
            <header class="letterhead">
                @if ($letterhead->logo_path)
                    <img src="{{ Storage::url($letterhead->logo_path) }}" alt="" class="letterhead-logo">
                @endif
                <div class="letterhead-text">
                    @foreach ([$letterhead->line_1, $letterhead->line_2, $letterhead->line_3, $letterhead->line_4] as $line)
                        @if ($line)
                            <div>{{ $line }}</div>
                        @endif
                    @endforeach
                </div>
                @if ($letterhead->secondary_logo_path)
                    <img src="{{ Storage::url($letterhead->secondary_logo_path) }}" alt="" class="letterhead-logo">
                @endif
            </header>
        @endif

        <section class="sheet-heading">
            <div class="eyebrow">Panelist Sign-off Sheet</div>
            <h1>{{ $category->name }}</h1>
            <p>{{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }} &middot; {{ $category->college->name ?? '—' }}</p>
        </section>

        @php
            $dateLabel = $sheet->start
                ? ($sheet->start->isSameDay($sheet->end)
                    ? $sheet->start->format('M j, Y')
                    : $sheet->start->format('M j, Y') . ' – ' . $sheet->end->format('M j, Y'))
                : '—';

            $metaCells = [
                ['Presentation Date', $dateLabel],
                ['Days', $sheet->day_count],
                ['Rooms', $sheet->rooms->implode(', ') ?: '—'],
                ['Total Panelists', $sheet->panelist_count],
                ['Groups Evaluated', $sheet->group_count],
            ];
        @endphp

        <dl class="meta" style="--meta-cols: {{ count($metaCells) }};">
            @foreach ($metaCells as [$label, $value])
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th class="col-num col-center">#</th>
                        <th>Panelist</th>
                        <th class="col-center col-groups">Groups</th>
                        <th class="col-signature">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sheet->rows as $row)
                        <tr>
                            <td class="col-num col-center">{{ $loop->iteration }}</td>
                            <td class="panelist-name">{{ $row->name }}</td>
                            <td class="col-center"><span class="groups-count">{{ $row->group_count }}</span></td>
                            <td class="col-signature"><div class="signature-line"></div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-row">No evaluations have been submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="sheet-footer">
            <span>{{ $category->name }}</span>
            <span>Generated {{ now()->format('M j, Y g:i A') }}</span>
        </footer>
    </main>
</body>
</html>
