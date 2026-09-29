{{--
    Shared "paper" visual styling — the part of the Evaluation Library
    builder's canvas that is genuinely content a printed sheet would show
    (letterhead, title, info rows, researcher/criteria tables, footer,
    sign-off). Included both by the builder's own workspace-scripts.blade.php
    (which layers its edit-mode-only rules — plain-input styling, row
    actions, add-row toolbar — on top separately) and by the room-session
    live-fill evaluation panel, so the two can never visually drift apart —
    "the evaluation form should be same on the form in the library" is
    guaranteed by sharing this file, not by keeping two copies in sync by
    hand.
--}}
<style>
    .eval-paper {
        background: var(--brand-surface);
        border: 1px solid var(--brand-border);
        border-radius: 0.5rem;
        padding: 1.5rem;
        max-width: 900px;
        margin: 0 auto;
        font-size: 0.82rem;
        position: relative;
    }
    .eval-paper-submitted { position: absolute; top: 0.6rem; right: 0.75rem; font-size: 0.68rem; }
    .eval-paper-letterhead { display: flex; align-items: center; justify-content: center; gap: 1.25rem; text-align: center; margin-bottom: 0.75rem; }
    .eval-paper-letterhead-text { font-size: 0.75rem; line-height: 1.25; }
    .eval-paper-logo { width: 54px; height: 54px; object-fit: contain; flex: 0 0 auto; }

    .eval-title-wrap { text-align: center; margin-bottom: 1rem; }
    .eval-title-static { font-size: 1.05rem; margin-bottom: 0.15rem; }

    .eval-paper-info-table, .eval-paper-researchers-table, .eval-paper-criteria-table { width: 100%; border-collapse: collapse; margin-bottom: 0.85rem; font-size: 0.8rem; }
    .eval-paper-info-table td, .eval-paper-researchers-table th, .eval-paper-researchers-table td,
    .eval-paper-criteria-table th, .eval-paper-criteria-table td { border: 1px solid var(--brand-border); padding: 0.35rem 0.5rem; vertical-align: top; }
    .eval-info-label { width: 150px; font-weight: 600; }
    .eval-remarks-cell { width: 220px; }
    .eval-remark-option { font-size: 0.75rem; margin-bottom: 0.15rem; }
    .eval-checkbox-glyph { display: inline-block; width: 0.9rem; }

    .eval-title-block-header { margin-top: 1.1rem; }
    .eval-title-block-header:first-of-type { margin-top: 0; }
    .eval-approved-cell { width: 110px; text-align: center; white-space: nowrap; font-size: 0.78rem; }

    .eval-legend-row td { background: var(--brand-surface-muted, rgba(127, 127, 127, 0.06)); font-size: 0.72rem; color: var(--brand-muted); }
    .eval-section-row td { background: rgba(217, 119, 6, 0.14); font-weight: 600; }
    .eval-item-row td:first-child { padding-left: 1.25rem; }
    .eval-row-number { margin-right: 0.25rem; color: var(--brand-muted); }

    .eval-rating-bubble { display: inline-block; width: 1.3rem; height: 1.3rem; line-height: 1.3rem; text-align: center; border: 1px solid var(--brand-border); border-radius: 50%; margin-right: 0.2rem; font-size: 0.68rem; }
    .eval-rating-bubble.is-selected { background: var(--brand-accent); color: var(--brand-accent-contrast, #fff); border-color: var(--brand-accent); font-weight: 700; }

    .eval-paper-footer-box { border: 1px solid var(--brand-border); border-radius: 0.375rem; padding: 0.5rem 0.7rem; font-size: 0.8rem; margin-bottom: 0.65rem; }
    .eval-paper-comment-lines { border-top: 1px dashed var(--brand-border); margin-top: 0.4rem; height: 1.75rem; }

    .eval-paper-signoff { display: flex; justify-content: space-between; gap: 2rem; margin-top: 1.5rem; }
    .eval-signoff-line { flex: 1; text-align: center; }
    .eval-signoff-name { border-bottom: 1px solid var(--brand-border); padding: 1.5rem 0.25rem 0.2rem; min-height: 1.2rem; }
    .eval-signoff-role { margin-top: 0.25rem; font-size: 0.75rem; color: var(--brand-muted); }
    .eval-paper-mark { font-family: 'Montserrat', sans-serif; font-style: italic; font-weight: 400; margin-top: 1.25rem; text-align: center; font-size: 0.7rem; letter-spacing: 0.08em; color: var(--brand-muted); opacity: 0.7; }
</style>
