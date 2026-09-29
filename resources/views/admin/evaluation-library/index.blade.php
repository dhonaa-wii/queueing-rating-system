@extends('layouts.admin')

@section('title', 'Evaluation Library')
@section('heading', 'Evaluation Library')

@section('content')
    <div class="page-shell el-page">
        @if ($forms->isEmpty())
            <div class="card-brand p-5 text-center text-brand-muted">
                <p class="mb-3">No evaluation forms yet.</p>
                <form method="POST" action="{{ route('admin.evaluation-library.store') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-brand btn-sm"><x-icon name="plus" /> Add First Form</button>
                </form>
            </div>
        @else
            <div class="el-toolbar">
                <span class="el-count">{{ $forms->count() }} {{ Str::plural('form', $forms->count()) }}</span>

                <div class="el-toolbar-actions">
                    <a href="{{ route('admin.evaluation-library.letterhead.edit') }}" class="btn btn-outline-brand btn-sm"><x-icon name="file-text" /> Letterhead</a>
                    <form method="POST" action="{{ route('admin.evaluation-library.store') }}">
                        @csrf
                        <button type="submit" class="btn btn-brand btn-sm"><x-icon name="plus" /> Add Form</button>
                    </form>
                </div>
            </div>

            <div class="el-grid">
                @foreach ($forms as $form)
                    @php
                        $active = $form->evaluationFormVersions->first(fn ($v) => $v->status->code === 'ACTIVE');
                        // A form has one version and is edited in place, so this
                        // is simply "the form" - resolved the same way the builder
                        // itself opens it (EvaluationForm::currentVersion()), read
                        // off the already-loaded relation to keep the grid free of
                        // one query per card.
                        $preview = $active
                            ?? $form->evaluationFormVersions->first(fn ($v) => $v->status->code === 'DRAFT')
                            ?? $form->evaluationFormVersions->sortByDesc('version_number')->first();
                        $sections = $preview
                            ? $preview->evaluationCriteria->whereNull('parent_criterion_id')->sortBy('sort_order')->values()
                            : collect();
                        $itemCount = $preview ? $preview->evaluationCriteria->whereNotNull('parent_criterion_id')->count() : 0;
                        $weightTotal = round((float) $sections->sum('weight'), 2);
                        $mode = $preview?->applicablePresentationModes->first();
                        $creatorName = trim(($form->createdBy->profile->first_name ?? '').' '.($form->createdBy->profile->last_name ?? '')) ?: ($form->createdBy->username ?? '—');
                        $blockingCategories = $blockingCategoriesByForm->get($form->id);
                    @endphp

                    <article class="el-sheet {{ $form->is_active ? '' : 'is-archived' }}">
                        <div class="el-sheet-head">
                            @if ($preview?->show_letterhead)
                                <div class="el-sheet-letterhead">
                                    <span class="el-sheet-seal"></span>
                                    <span class="el-sheet-seal-lines">
                                        <i></i><i></i><i></i>
                                    </span>
                                    <span class="el-sheet-seal"></span>
                                </div>
                            @endif

                            <h3 class="el-sheet-title" title="{{ $form->name }}">{{ $form->name }}</h3>

                            <div class="el-sheet-status">
                                @if (! $form->is_active)
                                    <span class="badge badge-muted-tint">Archived</span>
                                @elseif ($active)
                                    <span class="badge badge-success-tint">Published</span>
                                @else
                                    <span class="badge badge-brand-tint">Draft</span>
                                @endif
                                @if ($mode)
                                    <span class="badge badge-muted-tint">{{ $mode->name }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="el-sheet-body">
                            @forelse ($sections->take(4) as $section)
                                <div class="el-sheet-row">
                                    <span class="el-sheet-row-name">{{ $section->name }}</span>
                                    <span class="el-sheet-row-weight">{{ rtrim(rtrim(number_format((float) $section->weight, 2), '0'), '.') }}%</span>
                                </div>
                            @empty
                                <div class="el-sheet-row el-sheet-row-empty">
                                    <span class="el-sheet-row-name">No criteria yet</span>
                                    <span class="el-sheet-row-weight">—</span>
                                </div>
                            @endforelse

                            @if ($sections->count() > 4)
                                <div class="el-sheet-more">+{{ $sections->count() - 4 }} more</div>
                            @endif

                            <div class="el-sheet-scale">
                                @for ($v = 1; $v <= 5; $v++)
                                    <span>{{ $v }}</span>
                                @endfor
                            </div>
                        </div>

                        <div class="el-sheet-foot">
                            <div class="el-sheet-meta">
                                <span>{{ $sections->count() }} {{ Str::plural('section', $sections->count()) }}</span>
                                <span>{{ $itemCount }} {{ Str::plural('criterion', $itemCount) }}</span>
                                <span class="{{ abs($weightTotal - 100) < 0.01 ? 'is-balanced' : 'is-off' }}">{{ $weightTotal }}%</span>
                            </div>
                            <div class="el-sheet-byline">{{ $creatorName }}</div>

                            <div class="el-sheet-actions">
                                <a href="{{ route('admin.evaluation-library.show', $form) }}" class="btn btn-brand btn-sm el-open-btn"><x-icon name="edit" /> Open</a>

                                <div class="dropdown">
                                    <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="More actions">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if ($form->is_active)
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#archive-form-modal-{{ $form->id }}">
                                                    <x-icon name="archive" /> Archive
                                                </button>
                                            </li>
                                        @endif
                                        <li>
                                            <button type="button" class="dropdown-item text-brand-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#{{ $blockingCategories->isNotEmpty() ? 'delete-blocked-modal-' : 'delete-form-modal-' }}{{ $form->id }}">
                                                <x-icon name="trash" /> Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </article>

                    @if ($form->is_active)
                        <div class="modal fade" id="archive-form-modal-{{ $form->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Archive Form</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0">Archive <strong>{{ $form->name }}</strong>? Categories already using it keep it assigned.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.evaluation-library.archive', $form) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="archive" /> Archive</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($blockingCategories->isNotEmpty())
                        <div class="modal fade" id="delete-blocked-modal-{{ $form->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Can't Delete This Form</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>{{ $form->name }}</strong> is currently assigned to a category with an incoming or active presentation:</p>
                                        <ul class="mb-2">
                                            @foreach ($blockingCategories as $blockingCategory)
                                                <li>
                                                    {{ $blockingCategory->name }}
                                                    <span class="badge badge-brand-tint">{{ $blockingCategory->statusDisplayName() }}</span>
                                                    &mdash;
                                                    <a href="{{ route('admin.categories.show', $blockingCategory) }}#tab-evaluation">change its assigned evaluation form</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <p class="text-brand-muted small mb-0">Reassign those categories to a different form, then delete this one.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Got it</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="modal fade" id="delete-form-modal-{{ $form->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Form</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0">Permanently delete <strong>{{ $form->name }}</strong> and every section and criterion on it? This cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.evaluation-library.destroy', $form) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @push('styles')
        <style>
            .el-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.6rem;
                margin-bottom: 1rem;
                padding-bottom: 0.6rem;
                border-bottom: 1px solid var(--brand-border);
            }

            .el-count {
                font-size: var(--page-fs-xs);
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--brand-muted);
            }

            .el-toolbar-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .el-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(min(100%, 17.5rem), 1fr));
                gap: 1rem;
            }

            /* Each card is a small sheet of paper: a page squared off at the
               top and rounded at the foot, with a second and third sheet
               peeking out beneath it, holding a miniature of the real form
               (letterhead stub, heading, weighted sections, 1–5 scale).
               The stack is drawn with stacked box-shadows rather than a
               negative-z-index pseudo-element, so it can't disappear behind
               a painted ancestor. */
            .el-sheet {
                position: relative;
                display: flex;
                flex-direction: column;
                margin-bottom: 0.5rem;
                background: var(--brand-surface);
                border: 1px solid var(--brand-border);
                border-radius: 0.15rem 0.15rem 0.5rem 0.5rem;
                box-shadow:
                    0 4px 0 -1px var(--brand-surface),
                    0 4px 0 0 var(--brand-border),
                    0 8px 0 -1px var(--brand-surface),
                    0 8px 0 0 var(--brand-border),
                    var(--brand-shadow);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .el-sheet:hover,
            .el-sheet:focus-within {
                transform: translateY(-2px);
                box-shadow:
                    0 4px 0 -1px var(--brand-surface),
                    0 4px 0 0 var(--brand-border),
                    0 8px 0 -1px var(--brand-surface),
                    0 8px 0 0 var(--brand-border),
                    var(--brand-shadow-lifted);
            }

            /* The hover lift (transform) gives each card its own stacking
               layer, and a later card paints over an earlier one — so the
               card whose actions menu is open has to sit above the rest, or
               the card below covers the menu. */
            .el-sheet:hover,
            .el-sheet:focus-within { z-index: 2; }
            .el-sheet:has(.dropdown-menu.show) { z-index: 3; }

            .el-sheet.is-archived { opacity: 0.72; }

            .el-sheet-head {
                padding: 0.85rem 0.9rem 0.7rem;
                border-bottom: 1px solid var(--brand-border);
                text-align: center;
            }

            /* Letterhead stub — the two logo squares and the header lines,
               drawn as marks rather than loaded images. */
            .el-sheet-letterhead {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.4rem;
                margin-bottom: 0.55rem;
            }

            .el-sheet-seal {
                width: 0.85rem;
                height: 0.85rem;
                border-radius: 50%;
                border: 1px solid var(--brand-border);
                flex: 0 0 auto;
            }

            .el-sheet-seal-lines {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.16rem;
            }

            .el-sheet-seal-lines i {
                display: block;
                height: 2px;
                border-radius: 1px;
                background: var(--brand-border);
            }

            .el-sheet-seal-lines i:nth-child(1) { width: 3.4rem; }
            .el-sheet-seal-lines i:nth-child(2) { width: 4.4rem; }
            .el-sheet-seal-lines i:nth-child(3) { width: 2.6rem; }

            .el-sheet-title {
                margin: 0 0 0.4rem;
                font-size: var(--page-fs-heading);
                font-weight: 600;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .el-sheet-status {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.25rem;
            }

            .el-sheet-body {
                flex: 1;
                padding: 0.7rem 0.9rem;
            }

            .el-sheet-row {
                display: flex;
                align-items: baseline;
                gap: 0.4rem;
                padding: 0.28rem 0;
                border-bottom: 1px dashed var(--brand-border);
                font-size: var(--page-fs-xs);
            }

            .el-sheet-row-name {
                flex: 1;
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .el-sheet-row-weight {
                flex: 0 0 auto;
                font-weight: 600;
                color: var(--brand-muted);
                font-variant-numeric: tabular-nums;
            }

            .el-sheet-row-empty { color: var(--brand-muted); font-style: italic; }

            .el-sheet-more {
                padding-top: 0.3rem;
                font-size: var(--page-fs-xs);
                color: var(--brand-muted);
            }

            .el-sheet-scale {
                display: flex;
                justify-content: flex-end;
                gap: 0.2rem;
                margin-top: 0.6rem;
            }

            .el-sheet-scale span {
                width: 1.05rem;
                height: 1.05rem;
                line-height: 1;
                display: grid;
                place-items: center;
                border: 1px solid var(--brand-border);
                border-radius: 50%;
                font-size: 0.62rem;
                color: var(--brand-muted);
            }

            .el-sheet-foot {
                padding: 0.6rem 0.9rem 0.75rem;
                border-top: 1px solid var(--brand-border);
                background: var(--brand-surface-alt);
                border-radius: 0 0 0.45rem 0.45rem;
            }

            .el-sheet-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                font-size: var(--page-fs-xs);
                color: var(--brand-muted);
            }

            .el-sheet-meta .is-balanced { color: var(--brand-success); font-weight: 600; }
            .el-sheet-meta .is-off { color: var(--brand-danger); font-weight: 600; }

            .el-sheet-byline {
                margin-top: 0.15rem;
                font-size: var(--page-fs-xs);
                color: var(--brand-muted);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .el-sheet-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.4rem;
                margin-top: 0.55rem;
            }

            .el-open-btn { padding-inline: 0.85rem; }

            .el-page .dropdown-item {
                display: flex;
                align-items: center;
                gap: 0.45rem;
                font-size: var(--page-fs-sm);
            }

            @media (max-width: 575.98px) {
                .el-toolbar {
                    flex-direction: column;
                    align-items: stretch;
                }

                .el-toolbar-actions { justify-content: flex-end; }
            }
        </style>
    @endpush
@endsection
