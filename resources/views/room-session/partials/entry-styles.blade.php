{{--
    Shared styles for the three room-session entry screens (sign in, terminal
    picker, waiting for the room to start). They are one design: a feature
    panel on the left and the screen's own action card on the right, wrapping
    to a single column — card first — when the two genuinely don't fit.

    Sizes use clamp() with static fallbacks, so an older tablet browser without
    clamp() still gets sane values rather than losing the card's padding.
--}}
<style>
        /* Static values first, fluid ones only where clamp() is supported.
           A var() holding an unsupported clamp() is invalid at computed-value
           time, which resets the property rather than falling back to an
           earlier declaration — so the fallback has to live in the token
           itself. Older tablet browsers (pre-Chrome 79 WebView) otherwise lose
           every size on this page, including the card's padding, which leaves
           the inputs running edge to edge. */
        .rs-auth {
            --rs-fs: 0.9rem;
            --rs-fs-sm: 0.82rem;
            --rs-fs-xs: 0.73rem;
            --rs-title: 1.75rem;
            max-width: 68rem;
            margin: 0 auto;
            font-size: var(--rs-fs);
        }

        @supports (font-size: clamp(1rem, 1vw, 2rem)) {
            .rs-auth {
                --rs-fs: clamp(0.85rem, 0.8rem + 0.18vw, 0.94rem);
                --rs-fs-sm: clamp(0.78rem, 0.74rem + 0.14vw, 0.85rem);
                --rs-fs-xs: clamp(0.7rem, 0.67rem + 0.12vw, 0.76rem);
                --rs-title: clamp(1.35rem, 1.02rem + 1.3vw, 2.1rem);
            }
        }

        /* Flex-wrap rather than a breakpoint: the two columns sit side by side
           whenever they actually fit (their flex-basis plus the gap, ~32rem)
           and wrap to one column only when they don't. A tablet's CSS width is
           its physical width divided by its pixel ratio, so a 10" panel can
           report anything from ~600px to ~1280px — no single min-width value
           covers them all, but "does it fit" does. */
        .rs-grid {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            gap: clamp(1.75rem, 1.4rem + 1vw, 2.25rem) clamp(1.25rem, 0.3rem + 2.2vw, 3rem);
        }

        .rs-auth {
            display: flex;
            align-items: center;
            /* Capped, so a tall portrait tablet doesn't push the pair into the
               middle of the screen with a screenful of gap above it. */
            min-height: 34rem;
            min-height: min(calc(100vh - 9rem), 40rem);
        }

        .rs-grid {
            width: 100%;
        }

        .rs-intro {
            order: 1;
            flex: 1.1 1 16rem;
            min-width: 0;
        }

        .rs-form-col {
            order: 2;
            flex: 0.9 1 15rem;
            min-width: 0;
        }

        /* Phone: one column, and the form goes first so the inputs stay above
           the fold. Forced rather than left to wrapping, since at this width
           the root font drops to 13px and the bases would still "fit". */
        @media (max-width: 575.98px) {
            .rs-auth {
                display: block;
                min-height: 0;
            }

            .rs-intro {
                order: 2;
                flex-basis: 100%;
            }

            .rs-form-col {
                order: 1;
                flex-basis: 100%;
            }
        }

        .rs-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
            font-size: var(--rs-fs-xs);
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .rs-eyebrow svg {
            width: 0.95em;
            height: 0.95em;
        }

        .rs-title {
            margin: 0.85rem 0 0.6rem;
            font-size: var(--rs-title);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -0.015em;
        }

        .rs-lede {
            margin: 0;
            max-width: 34rem;
            color: var(--brand-muted);
            font-size: var(--rs-fs);
            line-height: 1.55;
        }

        .rs-features {
            list-style: none;
            margin: 1.5rem 0 0;
            margin: clamp(1.25rem, 1rem + 1vw, 1.9rem) 0 0;
            padding: 0;
            display: grid;
            gap: 0.9rem;
            gap: clamp(0.75rem, 0.6rem + 0.5vw, 1.1rem);
        }


        .rs-feature {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            min-width: 0;
        }

        .rs-feature-icon {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.6rem;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .rs-feature-icon svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .rs-feature-body {
            min-width: 0;
        }

        .rs-feature-title {
            margin: 0;
            font-size: var(--rs-fs-sm);
            font-weight: 600;
            line-height: 1.35;
        }

        .rs-feature-text {
            margin: 0.15rem 0 0;
            color: var(--brand-muted);
            font-size: var(--rs-fs-xs);
            line-height: 1.5;
        }

        /* Fixed comfortable width, centred in its column, so the card (and with
           it the fields) never just tracks whatever width the column happens to
           be. The cap is below the column width at every breakpoint the grid
           splits at, so it always applies. Extra side padding keeps the inputs
           visibly inset from the card edge. */
        .rs-card {
            max-width: 19.5rem;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem 1.6rem;
            padding: clamp(1.25rem, 1rem + 1.2vw, 1.85rem) clamp(1.4rem, 1.1rem + 1.4vw, 2rem);
            border-radius: 1rem;
            box-shadow: var(--brand-shadow-lifted);
        }

        .rs-card-head {
            margin-bottom: 1.15rem;
            margin-bottom: clamp(1rem, 0.85rem + 0.5vw, 1.35rem);
        }

        .rs-card-title {
            margin: 0;
            font-size: 1.12rem;
            font-size: clamp(1.05rem, 0.98rem + 0.3vw, 1.2rem);
            font-weight: 600;
        }

        .rs-card-sub {
            margin: 0.2rem 0 0;
            color: var(--brand-muted);
            font-size: var(--rs-fs-xs);
        }

        .rs-auth .form-label {
            margin-bottom: 0.3rem;
            font-size: var(--rs-fs-xs);
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .rs-auth .form-control {
            padding: 0.42rem 0.7rem;
            font-size: var(--rs-fs-sm);
            line-height: 1.45;
            border-radius: 0.55rem;
        }

        .rs-auth .form-control:focus {
            box-shadow: 0 0 0 0.18rem var(--brand-accent-tint);
        }

        .rs-field {
            margin-bottom: 0.85rem;
        }

        .rs-auth .btn-brand {
            --bs-btn-font-size: var(--rs-fs-sm);
            --bs-btn-padding-y: 0.5rem;
            --bs-btn-border-radius: 0.55rem;
            margin-top: 0.35rem;
        }

        .rs-auth .alert {
            padding: 0.6rem 0.8rem;
            font-size: var(--rs-fs-xs);
            border-radius: 0.55rem;
        }

        /* Always the last thing on the page, whichever way the grid is laid out. */
        .rs-back {
            order: 3;
            flex: 1 1 100%;
            margin: 0;
            text-align: center;
            font-size: var(--rs-fs-sm);
        }

    /* ---- Screen-specific pieces layered on the same shell ---- */

    /* Terminal-number choices on the picker. */
    .rs-choice {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0 0 0.5rem;
        padding: 0.55rem 0.7rem;
        border: 1px solid var(--brand-border);
        border-radius: 0.55rem;
        font-size: var(--rs-fs-sm);
        cursor: pointer;
    }

    .rs-choice:last-of-type {
        margin-bottom: 0;
    }

    .rs-choice:hover {
        border-color: var(--brand-accent);
    }

    .rs-choice input {
        margin: 0;
        flex: 0 0 auto;
    }

    .rs-choice-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        flex: 1 1 auto;
        min-width: 0;
        cursor: inherit;
    }

    .rs-choice-taken {
        border-style: dashed;
        opacity: 0.6;
        cursor: not-allowed;
    }

    .rs-choice-taken:hover {
        border-color: var(--brand-border);
    }

    .rs-choices {
        margin-bottom: 1rem;
    }

    /* Stacked full-width actions (picker / waiting screens). */
    .rs-actions {
        display: grid;
        gap: 0.5rem;
        margin-top: 0.35rem;
    }

    .rs-actions form {
        margin: 0;
    }

    .rs-actions .btn {
        width: 100%;
    }

    .rs-auth .btn-outline-brand {
        --bs-btn-font-size: var(--rs-fs-sm);
        --bs-btn-padding-y: 0.5rem;
        --bs-btn-border-radius: 0.55rem;
    }

    .rs-note {
        margin: 0;
        color: var(--brand-muted);
        font-size: var(--rs-fs-xs);
        line-height: 1.5;
    }
</style>

<style>
    /* Same flexbox-gap problem as the Bootstrap utilities patched in
       theme-head: on a browser older than Chrome/WebView 84 every gap below is
       dropped, which would butt the icon against its text and the radio
       against its label. theme-head's runtime probe sets .no-flex-gap. */
    .no-flex-gap .rs-feature > .rs-feature-icon { margin-right: 0.75rem; }
    .no-flex-gap .rs-eyebrow > svg { margin-right: 0.4rem; }
    .no-flex-gap .rs-choice > input { margin-right: 0.55rem; }
    .no-flex-gap .rs-choice-label > span:first-child { margin-right: 0.5rem; }

    /* The column gap can't become a margin without breaking the flex-basis
       arithmetic that decides when the two columns wrap, so it becomes inner
       padding on the columns themselves instead. */
    @media (min-width: 576px) {
        .no-flex-gap .rs-intro { padding-right: 0.75rem; }
        .no-flex-gap .rs-form-col { padding-left: 0.75rem; }
    }
</style>
