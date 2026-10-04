<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Research Presentation Queueing and Rating System</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.theme-head')
    <style>
        :root {
            --font-display: 'Fraunces', Georgia, 'Times New Roman', serif;
            --font-body: 'Work Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        body {
            font-family: var(--font-body);
        }

        h1, h2, h3, .landing-display {
            font-family: var(--font-display);
            letter-spacing: -0.01em;
        }

        .py-6 { padding-top: 5.5rem; padding-bottom: 5.5rem; }

        html { scroll-behavior: smooth; }
        section[id] { scroll-margin-top: 4rem; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }

        .reveal {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity 0.65s ease, transform 0.65s ease;
            transition-delay: var(--reveal-delay, 0s);
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal {
                transition: none;
                opacity: 1;
                transform: none;
            }
        }

        /* ---------- Nav ---------- */
        #site-nav {
            position: sticky;
            top: 0;
            z-index: 40;
            border-bottom: 1px solid transparent;
            background-color: color-mix(in srgb, var(--brand-bg) 88%, transparent);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        #site-nav.is-scrolled {
            border-bottom-color: var(--brand-border);
            box-shadow: var(--brand-shadow);
        }

        .nav-link-quiet {
            color: var(--brand-muted);
            font-size: 0.9rem;
            text-decoration: none;
        }

        .nav-link-quiet:hover {
            color: var(--brand-accent);
        }

        /* ---------- Hero ---------- */
        .hero-section {
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: "";
            position: absolute;
            inset: -10% -10% auto -10%;
            height: 130%;
            background-image: radial-gradient(var(--brand-border) 1px, transparent 1px);
            background-size: 26px 26px;
            -webkit-mask-image: radial-gradient(ellipse 70% 60% at 70% 20%, black 0%, transparent 70%);
            mask-image: radial-gradient(ellipse 70% 60% at 70% 20%, black 0%, transparent 70%);
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
        }

        .hero-section > .container {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--brand-accent);
        }

        .eyebrow::before {
            content: "";
            width: 1.4rem;
            height: 1px;
            background-color: var(--brand-accent);
        }

        .hero-heading {
            font-size: clamp(2.1rem, 4.2vw, 3.4rem);
            font-weight: 600;
            line-height: 1.08;
        }

        .hero-lede {
            color: var(--brand-muted);
            font-size: 1.1rem;
            max-width: 34rem;
        }

        .capability-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .capability-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.7rem;
            border: 1px solid var(--brand-border);
            border-radius: 999px;
            font-size: 0.78rem;
            color: var(--brand-text);
            background-color: var(--brand-surface);
        }

        .capability-chip svg {
            width: 13px;
            height: 13px;
            color: var(--brand-accent);
            flex-shrink: 0;
        }

        .scroll-cue {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--brand-muted);
            font-size: 0.85rem;
            text-decoration: none;
        }

        .scroll-cue:hover {
            color: var(--brand-accent);
        }

        .scroll-cue svg {
            width: 14px;
            height: 14px;
            animation: scroll-cue-bob 1.8s ease-in-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .scroll-cue svg { animation: none; }
        }

        @keyframes scroll-cue-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(4px); }
        }

        /* ---------- Hero visual: queue ticket stack ---------- */
        .hero-visual {
            position: relative;
            height: 21rem;
            max-width: 24rem;
            margin-inline: auto;
        }

        .hero-visual-badge {
            position: absolute;
            top: -0.75rem;
            right: 0.5rem;
            z-index: 4;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background-color: var(--brand-surface);
            border: 1px solid var(--brand-border);
            box-shadow: var(--brand-shadow);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .hero-visual-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--brand-success);
        }

        .queue-ticket {
            position: absolute;
            left: 0;
            right: 0;
            border-radius: 0.85rem;
            border: 1px solid var(--brand-border);
            background-color: var(--brand-surface);
            padding: 1rem 1.1rem;
        }

        .queue-ticket-back {
            top: 3.2rem;
            transform: rotate(-3.5deg);
            opacity: 0.75;
            box-shadow: var(--brand-shadow);
        }

        .queue-ticket-mid {
            top: 1.6rem;
            transform: rotate(2deg);
            opacity: 0.9;
            box-shadow: var(--brand-shadow);
        }

        .queue-ticket-front {
            top: 0;
            border-color: var(--brand-accent);
            box-shadow: 0 8px 28px -8px color-mix(in srgb, var(--brand-accent) 45%, transparent), var(--brand-shadow);
        }

        .queue-ticket-row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .queue-ticket-num {
            font-family: var(--font-display);
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--brand-accent);
            min-width: 2.4rem;
        }

        .queue-ticket-title {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .queue-ticket-sub {
            font-size: 0.76rem;
            color: var(--brand-muted);
        }

        .queue-ticket-badge {
            margin-left: auto;
            font-size: 0.68rem;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-weight: 600;
            white-space: nowrap;
        }

        .queue-ticket-front .queue-ticket-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .queue-ticket-pulse {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--brand-success);
            animation: ticket-pulse 1.6s ease-in-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .queue-ticket-pulse { animation: none; }
        }

        @keyframes ticket-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* ---------- How it works ---------- */
        .process-rail {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            counter-reset: process;
        }

        @media (max-width: 991.98px) {
            .process-rail { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 575.98px) {
            .process-rail { grid-template-columns: 1fr; }
        }

        .process-step {
            position: relative;
            padding-top: 1.75rem;
        }

        .process-step:not(:first-child)::before {
            content: "";
            position: absolute;
            top: 1.35rem;
            right: 100%;
            width: 1.5rem;
            border-top: 1px dashed var(--brand-border);
        }

        @media (max-width: 991.98px) {
            .process-step:nth-child(odd)::before { display: none; }
        }

        @media (max-width: 575.98px) {
            .process-step::before { display: none !important; }
        }

        .process-step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.7rem;
            height: 2.7rem;
            border-radius: 50%;
            border: 1px solid var(--brand-border);
            background-color: var(--brand-surface);
            font-family: var(--font-display);
            font-weight: 600;
            color: var(--brand-accent);
            margin-bottom: 1rem;
        }

        .process-step-title {
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .process-step-text {
            color: var(--brand-muted);
            font-size: 0.9rem;
        }

        /* ---------- Feature bento grid ---------- */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: minmax(9rem, auto);
            gap: 1rem;
        }

        @media (max-width: 767.98px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 575.98px) {
            .bento-grid { grid-template-columns: 1fr; }
        }

        .bento-panel {
            background-color: var(--brand-surface);
            border: 1px solid var(--brand-border);
            border-radius: 1rem;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .bento-panel:hover {
            border-color: var(--brand-accent);
            transform: translateY(-2px);
        }

        .bento-panel-lg {
            grid-column: span 2;
            grid-row: span 2;
        }

        .bento-panel-wide {
            grid-column: span 2;
        }

        @media (max-width: 767.98px) {
            .bento-panel-lg,
            .bento-panel-wide {
                grid-column: span 2;
                grid-row: span 1;
            }
        }

        @media (max-width: 575.98px) {
            .bento-panel-lg,
            .bento-panel-wide {
                grid-column: span 1;
            }
        }

        .bento-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.6rem;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .bento-icon svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        .bento-title {
            font-weight: 700;
            font-size: 1rem;
        }

        .bento-text {
            color: var(--brand-muted);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .bento-visual {
            margin-top: auto;
        }

        /* mini live-queue rows */
        .mini-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: var(--brand-success);
        }

        .mini-queue-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0.65rem;
            border-radius: 0.5rem;
            background-color: var(--brand-surface-alt);
            font-size: 0.76rem;
        }

        .mini-queue-row + .mini-queue-row {
            margin-top: 0.4rem;
        }

        .mini-queue-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* mini calendar dots */
        .mini-cal-row {
            display: flex;
            gap: 0.4rem;
        }

        .mini-cal-cell {
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 0.35rem;
            background-color: var(--brand-surface-alt);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.62rem;
            color: var(--brand-muted);
        }

        .mini-cal-cell.is-active {
            background-color: var(--brand-accent);
            color: var(--brand-accent-contrast);
            font-weight: 700;
        }

        /* mini bar chart */
        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 0.4rem;
            height: 3.2rem;
        }

        .mini-bar {
            flex: 1;
            border-radius: 0.25rem 0.25rem 0 0;
            background-color: var(--brand-accent-tint);
        }

        .mini-bar.is-accent {
            background-color: var(--brand-accent);
        }

        /* mini star scale */
        .mini-star-row {
            display: flex;
            gap: 0.25rem;
        }

        .mini-star-row svg {
            width: 1rem;
            height: 1rem;
            color: var(--brand-accent);
        }

        /* mini bell */
        .mini-bell-wrap {
            position: relative;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.6rem;
            background-color: var(--brand-surface-alt);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-accent);
        }

        .mini-bell-wrap svg {
            width: 1.2rem;
            height: 1.2rem;
        }

        .mini-bell-badge {
            position: absolute;
            top: -0.3rem;
            right: -0.3rem;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 50%;
            background-color: var(--brand-danger);
            color: #FFFFFF;
            font-size: 0.6rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ---------- Presentations ---------- */
        .presentation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 19rem), 1fr));
            gap: 1rem;
        }

        .presentation-card {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            background-color: var(--brand-surface);
            border: 1px solid var(--brand-border);
            border-radius: 1rem;
            padding: 1.25rem;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .presentation-card:hover {
            border-color: var(--brand-accent);
            transform: translateY(-2px);
            box-shadow: var(--brand-shadow);
        }

        .presentation-card.is-live {
            border-color: color-mix(in srgb, var(--brand-success) 55%, var(--brand-border));
        }

        .presentation-state {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
        }

        .presentation-state.tone-success { color: var(--brand-success); background-color: color-mix(in srgb, var(--brand-success) 14%, transparent); }
        .presentation-state.tone-accent  { color: var(--brand-accent);  background-color: var(--brand-accent-tint); }
        .presentation-state.tone-info    { color: var(--brand-info);    background-color: color-mix(in srgb, var(--brand-info) 14%, transparent); }
        .presentation-state.tone-muted   { color: var(--brand-muted);   background-color: var(--brand-surface-alt); }

        .presentation-title {
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 1.05rem;
            line-height: 1.3;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .presentation-meta {
            font-size: 0.82rem;
            color: var(--brand-muted);
            margin: 0;
        }

        .presentation-facts {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            font-size: 0.84rem;
            padding: 0.75rem 0.85rem;
            border-radius: 0.65rem;
            background-color: var(--brand-surface-alt);
        }

        .presentation-fact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .presentation-fact svg {
            width: 0.95rem;
            height: 0.95rem;
            flex-shrink: 0;
            color: var(--brand-accent);
        }

        .presentation-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: auto;
        }

        .presentation-actions .btn {
            flex: 1 1 0;
            white-space: nowrap;
            justify-content: center;
        }

        .presentation-empty {
            text-align: center;
            padding: 2.5rem 1.25rem;
            border: 1px dashed var(--brand-border);
            border-radius: 1rem;
            color: var(--brand-muted);
        }

        .section-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--brand-accent);
            text-decoration: none;
            white-space: nowrap;
        }

        .section-link svg { width: 1rem; height: 1rem; transition: transform 0.2s ease; }
        .section-link:hover svg { transform: translateX(3px); }

        /* ---------- Footer ---------- */
        .site-footer {
            border-top: 1px solid var(--brand-border);
        }

        .footer-brand {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
        }

        .footer-links a {
            color: var(--brand-muted);
            font-size: 0.85rem;
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--brand-accent);
        }

        .landing-brand {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.15;
            text-decoration: none;
            color: var(--brand-text);
        }

        /* Typeface comes from the shared .navbar-brand-mark, same as every
           other layout's ARPQRS. */
        .landing-brand-short {
            font-size: 1.35rem;
            line-height: 1.1;
        }

        .landing-brand-full {
            font-size: 0.72rem;
            color: var(--brand-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: min(26rem, 40vw);
        }

        #site-nav .container {
            flex-wrap: nowrap;
            gap: 0.75rem;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        @media (min-width: 768px) {
            .nav-actions { gap: 1.1rem; }
        }

        /* Icon-only Room Session below 576px so Log in + theme still fit on
           one row at 360px. */
        @media (max-width: 575.98px) {
            .nav-btn-label { display: none; }
            .py-6 { padding-top: 3rem; padding-bottom: 3rem; }
            .hero-lede { font-size: 1rem; }
        }

        /* Bootstrap caps .container at 720px for the whole 768–991px band, so a
           9–10" tablet (roughly 850–960 CSS px in landscape) would render this
           page inside 720px and leave up to 240px of screen unused. Use the
           real width there; 992px and up keeps Bootstrap's own caps. */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .container {
                max-width: none;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav id="site-nav" class="navbar navbar-expand py-2 py-md-3">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ route('landing') }}" class="landing-brand" title="Academic Research Presentation Queueing and Rating System">
                <span class="navbar-brand-mark landing-brand-short">ARPQRS</span>
                <span class="landing-brand-full d-none d-md-block">Academic Research Presentation Queueing and Rating System</span>
            </a>

            <div class="nav-actions">
                <a href="#presentations" class="nav-link-quiet d-none d-lg-inline">Presentations</a>
                <a href="#how-it-works" class="nav-link-quiet d-none d-lg-inline">How It Works</a>
                <a href="{{ route('help') }}" class="nav-link-quiet d-none d-md-inline">Help Center</a>
                <a href="{{ route('room-session.entry') }}" class="btn btn-outline-brand btn-sm" title="Room Session"><x-icon name="monitor" /><span class="nav-btn-label">Room Session</span></a>
                <a href="{{ route('login') }}" class="btn btn-brand btn-sm">Log in</a>

                @include('partials.theme-toggle-button')
            </div>
        </div>
    </nav>

    <header class="hero-section py-6">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-md-7 col-lg-6">
                    <span class="eyebrow mb-3 d-inline-flex">Academic Presentation Operations</span>
                    <h1 class="hero-heading mb-3">Every group in queue. Every panel on schedule. Every score on record.</h1>
                    <p class="hero-lede mb-4">
                        One system runs the full arc of a research presentation day &mdash; group
                        registration, room-by-room queueing, live panel control, and rubric-based
                        evaluation &mdash; for students, panelists, and administrators alike.
                    </p>

                    <div class="capability-chip-row mb-4">
                        <span class="capability-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                            Multi-room queueing
                        </span>
                        <span class="capability-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                            Live panel control
                        </span>
                        <span class="capability-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                            Configurable rubrics
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <a href="#presentations" class="btn btn-brand btn-lg px-4"><x-icon name="search" /> Browse Presentations</a>
                        <a href="{{ route('login') }}" class="btn btn-outline-brand btn-lg px-4">Log in</a>
                        <a href="#how-it-works" class="scroll-cue">
                            How it works
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"></path></svg>
                        </a>
                    </div>
                </div>

                <div class="col-md-5 col-lg-6 d-none d-md-block">
                    <div class="hero-visual reveal" style="--reveal-delay: 0.15s;">
                        <span class="hero-visual-badge"><span class="hero-visual-badge-dot"></span> Room 3B &middot; 09:42 AM</span>

                        <div class="queue-ticket queue-ticket-back">
                            <div class="queue-ticket-row">
                                <span class="queue-ticket-num">017</span>
                                <div>
                                    <div class="queue-ticket-title">CFD-2026-0017</div>
                                    <div class="queue-ticket-sub">Adaptive Irrigation Monitoring</div>
                                </div>
                                <span class="badge-muted-tint queue-ticket-badge">Queued</span>
                            </div>
                        </div>

                        <div class="queue-ticket queue-ticket-mid">
                            <div class="queue-ticket-row">
                                <span class="queue-ticket-num">016</span>
                                <div>
                                    <div class="queue-ticket-title">CFD-2026-0016</div>
                                    <div class="queue-ticket-sub">Campus Asset Tracker</div>
                                </div>
                                <span class="badge-info-tint queue-ticket-badge">Called</span>
                            </div>
                        </div>

                        <div class="queue-ticket queue-ticket-front">
                            <div class="queue-ticket-row">
                                <span class="queue-ticket-num">015</span>
                                <div>
                                    <div class="queue-ticket-title">CFD-2026-0015</div>
                                    <div class="queue-ticket-sub">Disaster Response Coordination App</div>
                                </div>
                                <span class="badge-success-tint queue-ticket-badge"><span class="queue-ticket-pulse"></span> Now Presenting</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="presentations" class="py-6" style="background-color: var(--brand-surface-alt);">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4 reveal">
                <div>
                    <span class="eyebrow mb-3 d-inline-flex">Open &amp; Active</span>
                    <h2 class="h1 mb-0">Presentations</h2>
                </div>
                <a href="{{ route('student.categories.index') }}" class="section-link">
                    View all presentations
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                </a>
            </div>

            @if ($presentations->isEmpty())
                <div class="presentation-empty reveal">
                    <div class="fw-semibold text-body mb-1">No open presentations right now</div>
                </div>
            @else
                <div class="presentation-grid">
                    @foreach ($presentations as $item)
                        @php($category = $item['category'])
                        <article class="presentation-card reveal {{ $item['live'] ? 'is-live' : '' }}" style="--reveal-delay: {{ $loop->index * 0.06 }}s;">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <span class="presentation-state tone-{{ $item['tone'] }}">
                                    @if ($item['live'])<span class="queue-ticket-pulse"></span>@endif
                                    {{ $item['label'] }}
                                </span>
                                <span class="badge badge-brand-tint">{{ $category->presentationMode->name ?? '' }}</span>
                            </div>

                            <div>
                                <h3 class="presentation-title">{{ $category->name }}</h3>
                                <p class="presentation-meta mt-1">
                                    {{ $category->academicYear->name ?? '' }}@if ($category->semester) &middot; {{ $category->semester->name }}@endif
                                    @if ($category->college)<br>{{ $category->college->name }}@endif
                                </p>
                            </div>

                            <div class="presentation-facts">
                                <div class="presentation-fact">
                                    <x-icon name="clock" />
                                    <span>{{ $item['detail'] }}</span>
                                </div>
                                @if ($item['nextDate'] && ! $item['live'] && $item['rank'] !== 3)
                                    <div class="presentation-fact">
                                        <x-icon name="calendar" />
                                        <span>
                                            Presents {{ $item['nextDate']->presentation_date->format('M j, Y') }}
                                            @if ($item['dayCount'] > 1)<span class="text-brand-muted">+{{ $item['dayCount'] - 1 }} more</span>@endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="presentation-actions">
                                @if ($item['canRegister'])
                                    <a href="{{ route('student.categories.registration.create', $category) }}" class="btn btn-brand btn-sm"><x-icon name="user-plus" /> Register</a>
                                @endif
                                <a href="{{ route('student.categories.schedule', $category) }}" class="btn btn-outline-brand btn-sm"><x-icon name="calendar" /> {{ $item['live'] ? 'View Live Queue' : 'View Schedule' }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($presentationTotal > $presentations->count())
                    <div class="text-center mt-4">
                        <a href="{{ route('student.categories.index') }}" class="btn btn-outline-brand">View all {{ $presentationTotal }} presentations <x-icon name="arrow-right" /></a>
                    </div>
                @endif
            @endif
        </div>
    </section>

    <section id="how-it-works" class="py-6">
        <div class="container">
            <div class="row justify-content-between align-items-end mb-5 reveal">
                <div class="col-lg-6">
                    <span class="eyebrow mb-3 d-inline-flex">The Flow</span>
                    <h2 class="h1 mb-0">From registration to results, one continuous line.</h2>
                </div>
            </div>

            <div class="process-rail">
                <div class="process-step reveal" style="--reveal-delay: 0s;">
                    <span class="process-step-number">01</span>
                    <div class="process-step-title">Register</div>
                    <p class="process-step-text">Groups register for a presentation directly, with no account needed.</p>
                </div>
                <div class="process-step reveal" style="--reveal-delay: 0.08s;">
                    <span class="process-step-number">02</span>
                    <div class="process-step-title">Get Queued</div>
                    <p class="process-step-text">When registration closes, every group gets a room, a date, and a place in a fair queue.</p>
                </div>
                <div class="process-step reveal" style="--reveal-delay: 0.16s;">
                    <span class="process-step-number">03</span>
                    <div class="process-step-title">Present</div>
                    <p class="process-step-text">Panels run each room from a live terminal: call, start, pause, and complete as it happens.</p>
                </div>
                <div class="process-step reveal" style="--reveal-delay: 0.24s;">
                    <span class="process-step-number">04</span>
                    <div class="process-step-title">Evaluate</div>
                    <p class="process-step-text">Panelists rate each group on a weighted rubric, and grades and outcomes are recorded.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-6" style="background-color: var(--brand-surface-alt);">
        <div class="container">
            <div class="row justify-content-between align-items-end mb-5 reveal">
                <div class="col-lg-6">
                    <span class="eyebrow mb-3 d-inline-flex">Built For The Whole Day</span>
                    <h2 class="h1 mb-0">One system, every seat in the room.</h2>
                </div>
            </div>

            <div class="bento-grid">
                <div class="bento-panel bento-panel-lg reveal" style="--reveal-delay: 0s;">
                    <div class="bento-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                    </div>
                    <div>
                        <div class="bento-title">Live Queue Monitoring</div>
                        <p class="bento-text">Every room, every group, every status &mdash; watched from one screen as it happens.</p>
                    </div>
                    <div class="bento-visual">
                        <span class="mini-live-badge mb-2 d-inline-flex"><span class="queue-ticket-pulse"></span> LIVE ACROSS 4 ROOMS</span>
                        <div class="mini-queue-row">
                            <span class="mini-queue-dot" style="background-color: var(--brand-success);"></span>
                            Room 1 &middot; Now Presenting &middot; CFD-2026-0004
                        </div>
                        <div class="mini-queue-row">
                            <span class="mini-queue-dot" style="background-color: var(--brand-info);"></span>
                            Room 2 &middot; Called &middot; CFD-2026-0009
                        </div>
                        <div class="mini-queue-row">
                            <span class="mini-queue-dot" style="background-color: var(--brand-muted);"></span>
                            Room 3 &middot; Queued &middot; CFD-2026-0011
                        </div>
                    </div>
                </div>

                <div class="bento-panel bento-panel-wide reveal" style="--reveal-delay: 0.06s;">
                    <div class="bento-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                    </div>
                    <div>
                        <div class="bento-title">Panel Evaluation &amp; Scoring</div>
                        <p class="bento-text">Panelists rate each group on a weighted rubric; grades are computed for them.</p>
                    </div>
                    <div class="bento-visual d-flex align-items-center justify-content-between">
                        <span class="mini-star-row">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                        </span>
                        <span class="badge-brand-tint px-2 py-1 rounded" style="font-size: 0.72rem;">30% Technical Merit</span>
                    </div>
                </div>

                <div class="bento-panel reveal" style="--reveal-delay: 0.12s;">
                    <div class="bento-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                    </div>
                    <div>
                        <div class="bento-title">Multi-Room Scheduling</div>
                        <p class="bento-text">Rooms and dates configured once, filled fairly across every day.</p>
                    </div>
                    <div class="bento-visual mini-cal-row">
                        <span class="mini-cal-cell">M</span>
                        <span class="mini-cal-cell">T</span>
                        <span class="mini-cal-cell is-active">W</span>
                        <span class="mini-cal-cell">T</span>
                        <span class="mini-cal-cell">F</span>
                    </div>
                </div>

                <div class="bento-panel reveal" style="--reveal-delay: 0.18s;">
                    <div class="bento-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    </div>
                    <div>
                        <div class="bento-title">Needs Attention</div>
                        <p class="bento-text">Deferred groups, overdue days, and panel changes, flagged as they happen.</p>
                    </div>
                    <div class="bento-visual">
                        <div class="mini-bell-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <span class="mini-bell-badge">3</span>
                        </div>
                    </div>
                </div>

                <div class="bento-panel bento-panel-wide reveal" style="--reveal-delay: 0.24s;">
                    <div class="bento-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>
                    </div>
                    <div>
                        <div class="bento-title">Reports &amp; Analytics</div>
                        <p class="bento-text">Grades, sign-off sheets, and planned-versus-actual timing, exportable to PDF and Excel.</p>
                    </div>
                    <div class="bento-visual mini-bars">
                        <span class="mini-bar" style="height: 45%;"></span>
                        <span class="mini-bar" style="height: 70%;"></span>
                        <span class="mini-bar is-accent" style="height: 95%;"></span>
                        <span class="mini-bar" style="height: 60%;"></span>
                        <span class="mini-bar" style="height: 80%;"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <footer class="site-footer py-5">
        <div class="container">
            <div class="row gy-4 align-items-center">
                <div class="col-md-6">
                    <div class="footer-brand mb-1">Academic Research Presentation Queueing and Rating System</div>
                    <p class="text-brand-muted small mb-0">Registration, scheduling, live queueing, and panel evaluation in one system.</p>
                </div>
                <div class="col-md-6 d-flex justify-content-md-end">
                    <nav class="footer-links">
                        <a href="{{ route('landing') }}">Home</a>
                        <a href="#presentations">Presentations</a>
                        <a href="#how-it-works">How It Works</a>
                        <a href="{{ route('room-session.entry') }}">Room Session</a>
                        <a href="{{ route('help') }}">Help Center</a>
                        <a href="{{ route('login') }}">Log in</a>
                    </nav>
                </div>
            </div>
            <hr class="brand-divider my-4">
            <p class="text-brand-muted small text-center mb-0">
                &copy; {{ date('Y') }} BSIT Department, Cagayan State University &ndash; Aparri.
            </p>
        </div>
    </footer>

    @include('partials.theme-toggle-script')
    <script>
        (function () {
            var nav = document.getElementById('site-nav');
            if (nav) {
                var onScroll = function () {
                    nav.classList.toggle('is-scrolled', window.scrollY > 8);
                };
                onScroll();
                window.addEventListener('scroll', onScroll, { passive: true });
            }

            var reveals = document.querySelectorAll('.reveal');
            if (!reveals.length) {
                return;
            }

            var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                reveals.forEach(function (el) { el.classList.add('is-visible'); });
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

            reveals.forEach(function (el) { observer.observe(el); });
        })();
    </script>
</body>
</html>
