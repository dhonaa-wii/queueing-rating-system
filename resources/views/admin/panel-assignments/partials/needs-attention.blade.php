{{--
    2026-08-25 simplified (user-directed): this used to be a full-width
    bannered card listing every deferred group, pending substitution
    request, and cross-category conflict inline with its own action
    buttons. Deferred groups are actioned from the Deferred tab below
    (Reinsert/Verify Payment/Edit live there now), pending substitution
    requests stay reachable from the Dashboard and the notification bell
    (App\Services\PanelSubstitutionService), and a fresh assignment
    conflict is now surfaced the moment it happens via
    assign-conflict-modal.blade.php rather than listed here after the fact.

    2026-09-16 redesigned (user-directed): the plain sentence on its own
    row above the toolbar is now a compact chip sitting beside Add Group,
    styled after the Dashboard header's own "Needs attention" button
    (admin/dashboard.blade.php's `.db-btn`/`.db-count`) — bell, label,
    solid danger count. The breakdown it used to print inline is a small
    plain hover/focus hint instead, so the toolbar row stays one line.
    It only ever counted deferred groups in that sentence even though the
    count in its heading covered all three kinds; the hint now names
    whichever kinds are actually present.
--}}
@if ($needsAttention->isNotEmpty())
    @php
        $substitutionCount = $needsAttention->where('type', 'substitution')->count();
        $conflictCount = $needsAttention->where('type', 'conflict')->count();

        $hintParts = [];

        if ($deferredCount > 0) {
            $hintParts[] = $deferredCount . ' group' . ($deferredCount === 1 ? '' : 's') . ' deferred';
        }

        if ($substitutionCount > 0) {
            $hintParts[] = $substitutionCount . ' panel substitution' . ($substitutionCount === 1 ? '' : 's') . ' pending';
        }

        if ($conflictCount > 0) {
            $hintParts[] = $conflictCount . ' panelist schedule conflict' . ($conflictCount === 1 ? '' : 's');
        }

        $unscheduledCount = (int) ($needsAttention->firstWhere('type', 'unscheduled')['count'] ?? 0);

        if ($unscheduledCount > 0) {
            $hintParts[] = $unscheduledCount . ' group' . ($unscheduledCount === 1 ? '' : 's') . ' to be scheduled — add a date or room';
        }

        $hint = implode(' · ', $hintParts);
    @endphp

    {{-- A button only when there are deferred groups to open — with none,
         everything it counts (a pending substitution, a schedule conflict)
         is resolved elsewhere, so clicking through to an empty Deferred
         tab would be a dead end. --}}
    @if ($deferredCount > 0)
        <button type="button" class="attention-chip" data-open-deferred aria-describedby="attention-chip-hint">
    @else
        <span class="attention-chip" tabindex="0" aria-describedby="attention-chip-hint">
    @endif
        <x-icon name="bell" />
        <span class="attention-chip-label">Needs Attention</span>
        <span class="attention-chip-count">{{ $needsAttention->count() }}</span>
        <span class="attention-chip-hint" id="attention-chip-hint" role="tooltip">{{ $hint }}</span>
    @if ($deferredCount > 0)
        </button>
    @else
        </span>
    @endif

    @push('styles')
        <style>
            /* Mirrors the Dashboard header's `.db-btn` shape so the two
               read as the same control, at the toolbar's own 2.1rem
               control height. Deliberately no hover lift or accent
               colour — only the danger tint that signals its hint. */
            .attention-chip {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                height: 2.1rem;
                padding: 0 0.7rem;
                border: 0;
                border-radius: 0.55rem;
                background: var(--brand-surface-alt);
                color: var(--brand-text);
                font-size: var(--page-fs-sm);
                font-weight: 600;
                line-height: 1.2;
                white-space: nowrap;
                cursor: default;
                transition: background-color 0.15s ease;
            }

            button.attention-chip {
                cursor: pointer;
            }

            .attention-chip > svg {
                width: 0.95rem;
                height: 0.95rem;
                color: var(--brand-danger);
                flex-shrink: 0;
            }

            .attention-chip:hover,
            .attention-chip:focus-visible {
                background: var(--brand-danger-tint);
                outline: none;
            }

            .attention-chip-count {
                background: var(--brand-danger);
                color: #fff;
                border-radius: 999px;
                font-size: var(--page-fs-xs);
                font-variant-numeric: tabular-nums;
                padding: 0.05rem 0.4rem;
            }

            .attention-chip-hint {
                position: absolute;
                top: calc(100% + 0.3rem);
                right: 0;
                z-index: 5;
                padding: 0.25rem 0.5rem;
                border-radius: 0.4rem;
                background: var(--brand-surface);
                border: 1px solid var(--brand-border);
                color: var(--brand-muted);
                font-size: var(--page-fs-xs);
                font-weight: 400;
                pointer-events: none;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-2px);
                transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
            }

            .attention-chip:hover .attention-chip-hint,
            .attention-chip:focus-visible .attention-chip-hint {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            /* The label is the least useful part once space is tight —
               the bell and the count still say what it is — and the hint
               has to wrap rather than run off a phone's edge. */
            @media (max-width: 575.98px) {
                .attention-chip-label {
                    display: none;
                }

                .attention-chip-hint {
                    max-width: min(16rem, 70vw);
                    white-space: normal;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .attention-chip,
                .attention-chip-hint {
                    transition: none;
                }
            }
        </style>
    @endpush

    @if ($deferredCount > 0)
        @push('scripts')
            <script>
                // Opens the Deferred tab by clicking the tab button itself,
                // so the pane swap, the room filter and the active-tab state
                // all stay owned by that one handler in show.blade.php.
                // User-directed 2026-09-16: no blink — this deliberately
                // does not go through FocusLink/.focus-flash, which is for
                // landing on one specific row from another page.
                document.addEventListener('DOMContentLoaded', function () {
                    var chip = document.querySelector('[data-open-deferred]');
                    var tab = document.querySelector('[data-tab-btn="deferred"]');

                    if (! chip || ! tab) return;

                    chip.addEventListener('click', function () {
                        tab.click();
                        tab.scrollIntoView({ block: 'nearest' });
                    });
                });
            </script>
        @endpush
    @endif
@endif
