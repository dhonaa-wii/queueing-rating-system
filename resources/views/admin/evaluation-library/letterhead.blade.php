@extends('layouts.admin')

@section('title', 'Letterhead')
@section('heading', 'Letterhead')
@section('back-link')
    @include('partials.back-link', ['href' => route('admin.evaluation-library.index')])
@endsection

@section('content')
    @php
        $placeholders = ['Republic of the Philippines', 'Cagayan State University', 'Aparri Campus', 'College of Information and Computing Sciences'];
        $lines = array_filter([$letterhead->line_1, $letterhead->line_2, $letterhead->line_3, $letterhead->line_4]);
    @endphp

    <div class="page-shell lh-page">
        @unless ($college)
            <div class="card-brand p-4 text-center text-brand-muted mb-3">
                Your account has no college assigned, so there is no letterhead to configure.
            </div>
        @endunless

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card-brand lh-card">
                    <div class="lh-card-head">
                        <h3 class="h6 mb-0">Letterhead</h3>
                        @if ($college)
                            <span class="badge badge-brand-tint">{{ $college->code ?? $college->name }}</span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.evaluation-library.letterhead.update') }}" enctype="multipart/form-data" class="lh-card-body">
                        @csrf
                        @method('PUT')

                        <p class="lh-group-label">Logos</p>
                        <div class="lh-logo-grid">
                            @foreach ([['logo', 'logo_path', 'Left Logo'], ['secondary_logo', 'secondary_logo_path', 'Right Logo']] as [$field, $column, $label])
                                <div class="lh-logo-field">
                                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                    <div class="lh-logo-row">
                                        <span class="lh-logo-thumb">
                                            @if ($letterhead->{$column})
                                                <img src="{{ $letterhead->logoUrl($column) }}" alt="">
                                            @else
                                                <x-icon name="file-text" />
                                            @endif
                                        </span>
                                        <div class="lh-logo-controls">
                                            <input type="file" name="{{ $field }}" id="{{ $field }}" accept="image/*"
                                                   class="form-control form-control-sm @error($field) is-invalid @enderror" @disabled(! $college)>
                                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            @if ($letterhead->{$column})
                                                <div class="form-check mt-1">
                                                    <input class="form-check-input" type="checkbox" value="1" name="remove_{{ $field }}" id="remove_{{ $field }}">
                                                    <label class="form-check-label" for="remove_{{ $field }}">Remove</label>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="lh-group-label">Header Lines</p>
                        <div class="lh-line-grid">
                            @for ($i = 1; $i <= 4; $i++)
                                <div>
                                    <label for="line_{{ $i }}" class="form-label">Line {{ $i }}</label>
                                    <input type="text" name="line_{{ $i }}" id="line_{{ $i }}" class="form-control form-control-sm @error('line_'.$i) is-invalid @enderror"
                                           value="{{ old('line_'.$i, $letterhead->{'line_'.$i}) }}" maxlength="200"
                                           placeholder="{{ $placeholders[$i - 1] }}" @disabled(! $college)>
                                    @error('line_'.$i) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endfor
                        </div>

                        <div class="lh-card-foot">
                            <button type="submit" class="btn btn-brand btn-sm" @disabled(! $college)><x-icon name="save" /> Save Letterhead</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card-brand lh-card">
                    <div class="lh-card-head">
                        <h3 class="h6 mb-0">Preview</h3>
                    </div>
                    <div class="lh-card-body">
                        <div class="lh-preview-sheet">
                            <div class="lh-preview-head">
                                @if ($letterhead->logo_path)
                                    <img src="{{ $letterhead->logoUrl('logo_path') }}" alt="">
                                @endif
                                <div class="lh-preview-lines">
                                    @forelse ($lines as $line)
                                        <div>{{ $line }}</div>
                                    @empty
                                        <div class="text-brand-muted">&mdash;</div>
                                    @endforelse
                                </div>
                                @if ($letterhead->secondary_logo_path)
                                    <img src="{{ $letterhead->logoUrl('secondary_logo_path') }}" alt="">
                                @endif
                            </div>
                            <div class="lh-preview-title">Evaluation Form</div>
                            @for ($i = 0; $i < 7; $i++)
                                <div class="lh-preview-rule"></div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .lh-card {
                display: flex;
                flex-direction: column;
                height: 100%;
                overflow: hidden;
            }

            .lh-card-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                padding: 0.7rem 0.9rem;
                border-bottom: 1px solid var(--brand-border);
            }

            .lh-card-body { padding: 0.9rem; }

            .lh-card-foot {
                margin-top: 1rem;
                padding-top: 0.8rem;
                border-top: 1px solid var(--brand-border);
            }

            .lh-group-label {
                margin: 0 0 0.5rem;
                font-size: var(--page-fs-xs);
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--brand-muted);
            }

            .lh-line-grid + .lh-group-label,
            .lh-logo-grid + .lh-group-label { margin-top: 1.1rem; }

            .lh-logo-grid,
            .lh-line-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
                gap: 0.75rem;
            }

            .lh-logo-row {
                display: flex;
                align-items: flex-start;
                gap: 0.6rem;
            }

            .lh-logo-thumb {
                display: grid;
                place-items: center;
                flex: 0 0 auto;
                width: 3.1rem;
                height: 3.1rem;
                border: 1px dashed var(--brand-border);
                border-radius: 0.5rem;
                background: var(--brand-surface);
                color: var(--brand-muted);
                overflow: hidden;
            }

            .lh-logo-thumb img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                padding: 0.2rem;
            }

            .lh-logo-thumb svg {
                width: 1.1rem;
                height: 1.1rem;
                opacity: 0.7;
            }

            .lh-logo-controls { min-width: 0; flex: 1; }

            .lh-page .form-label { margin-bottom: 0.2rem; }
            .lh-page .form-check-label { font-size: var(--page-fs-xs); }

            /* Preview is a small sheet of paper rather than a plain text
               block, so the logos read in the position they will print in. */
            .lh-preview-sheet {
                background: var(--brand-surface);
                border: 1px solid var(--brand-border);
                border-radius: 0.4rem;
                padding: 0.9rem 0.8rem 1.1rem;
                box-shadow: var(--brand-shadow);
            }

            .lh-preview-head {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.7rem;
                text-align: center;
            }

            .lh-preview-head img {
                width: 2.6rem;
                height: 2.6rem;
                object-fit: contain;
                flex: 0 0 auto;
            }

            .lh-preview-lines {
                font-size: var(--page-fs-xs);
                line-height: 1.3;
                min-width: 0;
            }

            .lh-preview-lines div:first-child { font-weight: 600; }

            .lh-preview-title {
                margin: 0.8rem 0 0.7rem;
                padding-top: 0.6rem;
                border-top: 1px solid var(--brand-border);
                text-align: center;
                font-size: var(--page-fs-sm);
                font-weight: 600;
            }

            .lh-preview-rule {
                height: 0.5rem;
                margin-bottom: 0.45rem;
                border-bottom: 1px solid var(--brand-border);
                opacity: 0.55;
            }

            .lh-preview-rule:nth-child(odd) { width: 88%; }
            .lh-preview-rule:last-child { width: 55%; }
        </style>
    @endpush
@endsection
