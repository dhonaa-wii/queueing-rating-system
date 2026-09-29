@extends('layouts.admin')

@section('title', 'Presentation Setup')
@section('heading', 'Presentation Setup')

@section('content')
    <div class="cat-page">
    @if ($activeCategories->isEmpty() && $archivedCategories->isEmpty())
        <div class="card-brand p-5 text-center text-brand-muted">
            <p class="mb-3">No presentation categories yet.</p>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-outline-brand"><x-icon name="plus" /> Create the first category</a>
        </div>
    @else
        <div class="cat-toolbar">
            <ul class="nav cat-tabs" id="category-visibility-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-active-categories" type="button" role="tab">
                        Active <span class="cat-tab-count">{{ $activeCategories->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-archived-categories" type="button" role="tab">
                        Archived <span class="cat-tab-count">{{ $archivedCategories->count() }}</span>
                    </button>
                </li>
            </ul>

            <a href="{{ route('admin.categories.create') }}" class="btn btn-brand btn-sm cat-new-btn">
                <x-icon name="plus" /> New Category
            </a>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-active-categories">
                @if ($activeCategories->isEmpty())
                    <div class="card-brand p-5 text-center text-brand-muted">
                        No active presentation categories.
                    </div>
                @else
                    <div class="cat-grid">
                        @foreach ($activeCategories as $category)
                            @include('admin.categories.partials.category-card', ['category' => $category])
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="tab-pane fade" id="tab-archived-categories">
                @if ($archivedCategories->isEmpty())
                    <div class="card-brand p-5 text-center text-brand-muted">
                        No archived presentation categories.
                    </div>
                @else
                    <div class="cat-grid">
                        @foreach ($archivedCategories as $category)
                            @include('admin.categories.partials.category-card', ['category' => $category])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    </div>

    @include('admin.partials.confirm-action-modal')

    @push('styles')
        <style>
            .cat-page {
                width: 85%;
                margin-inline: auto;
            }

            .cat-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-end;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 1.25rem;
                border-bottom: 1px solid var(--brand-border);
            }

            .cat-tabs {
                flex-wrap: nowrap;
                gap: 0.25rem;
                overflow-x: auto;
                scrollbar-width: none;
                margin-bottom: -1px;
            }

            .cat-tabs::-webkit-scrollbar {
                display: none;
            }

            .cat-tabs .nav-link {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.5rem 0.75rem 0.65rem;
                background: none;
                border: 0;
                border-radius: 0;
                color: var(--brand-muted);
                font-size: clamp(0.78rem, 0.72rem + 0.2vw, 0.875rem);
                font-weight: 500;
                white-space: nowrap;
                transition: color 0.15s ease;
            }

            /* Active underline: a 2px accent bar that grows out from the
               centre when a tab becomes active. */
            .cat-tabs .nav-link::after {
                content: "";
                position: absolute;
                left: 0.5rem;
                right: 0.5rem;
                bottom: 0;
                height: 2px;
                border-radius: 2px 2px 0 0;
                background-color: var(--brand-accent);
                transform: scaleX(0);
                transition: transform 0.2s ease;
            }

            .cat-tabs .nav-link:hover:not(.active) {
                color: var(--brand-text);
            }

            .cat-tabs .nav-link.active {
                color: var(--brand-accent);
                font-weight: 600;
            }

            .cat-tabs .nav-link.active::after {
                transform: scaleX(1);
            }

            .cat-tabs .nav-link:focus-visible {
                outline: none;
                border-radius: 0.4rem 0.4rem 0 0;
                box-shadow: inset 0 0 0 2px var(--brand-accent-tint);
            }

            .cat-tab-count {
                min-width: 1.35rem;
                padding: 0.05rem 0.4rem;
                border-radius: 999px;
                background-color: var(--brand-surface-alt);
                color: var(--brand-muted);
                font-size: 0.72em;
                font-weight: 600;
                line-height: 1.5;
                text-align: center;
            }

            .cat-tabs .nav-link.active .cat-tab-count {
                background-color: var(--brand-accent-tint);
                color: var(--brand-accent);
            }

            .cat-new-btn {
                margin-bottom: 0.45rem;
                padding: 0.35rem 0.85rem;
                border-radius: 999px;
                font-size: clamp(0.75rem, 0.7rem + 0.15vw, 0.82rem);
                font-weight: 600;
            }

            .cat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(min(100%, 20.5rem), 1fr));
                gap: 0.85rem;
            }

            @media (max-width: 991.98px) {
                .cat-page {
                    width: 100%;
                }
            }

            @media (max-width: 575.98px) {
                .cat-toolbar {
                    flex-direction: column-reverse;
                    align-items: stretch;
                    gap: 0.5rem;
                    border-bottom: 0;
                }

                .cat-tabs {
                    margin-bottom: 0;
                    border-bottom: 1px solid var(--brand-border);
                }

                .cat-new-btn {
                    align-self: flex-end;
                    margin-bottom: 0;
                }
            }
        </style>
    @endpush
@endsection
