@php
    $announcements = $category->categoryAnnouncements
        ->sortBy(fn ($a) => $a->isPaymentInstructions() ? 0 : 1)
        ->values();
@endphp

<div class="row g-4 ann-tab">
    <div class="col-lg-5">
        <div class="card-brand p-4">
            <div class="ann-head">
                <span class="ann-chip"><x-icon name="megaphone" /></span>
                <h3 class="h6 mb-0">Post Category Announcement</h3>
            </div>

            <form method="POST" action="{{ route('admin.categories.announcements.store', $category) }}">
                @csrf

                <div class="mb-3">
                    <label for="announcement_title" class="form-label">Title</label>
                    <input type="text" name="title" id="announcement_title" class="form-control" maxlength="200" required>
                </div>

                <div class="mb-3">
                    <label for="announcement_message" class="form-label">Message</label>
                    <textarea name="message" id="announcement_message" class="form-control" rows="4" required></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label for="announcement_starts_at" class="form-label"><x-icon name="play" class="ann-label-icon" /> Starts</label>
                        <input type="datetime-local" name="starts_at" id="announcement_starts_at" class="form-control">
                    </div>
                    <div class="col-6">
                        <label for="announcement_ends_at" class="form-label"><x-icon name="stop" class="ann-label-icon" /> Ends</label>
                        <input type="datetime-local" name="ends_at" id="announcement_ends_at" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-brand"><x-icon name="plus" /> Post Announcement</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="ann-list-head">
            <h3 class="h6 mb-0">Announcements</h3>
            <span class="badge badge-muted-tint">{{ $announcements->count() }}</span>
        </div>

        @forelse ($announcements as $announcement)
            @php $isPayment = $announcement->isPaymentInstructions(); @endphp
            <div class="card-brand ann-item {{ $announcement->is_active ? '' : 'is-inactive' }} {{ $isPayment ? 'is-payment' : '' }}">
                <span class="ann-item-icon"><x-icon name="{{ $isPayment ? 'wallet' : 'megaphone' }}" /></span>

                <div class="ann-item-body">
                    <div class="ann-item-top">
                        <strong class="ann-item-title">{{ $announcement->title }}</strong>
                        @if ($isPayment)
                            <span class="badge badge-info-tint">Payment</span>
                        @endif
                        @if ($announcement->is_active)
                            <span class="badge badge-success-tint">Active</span>
                        @else
                            <span class="badge badge-muted-tint">Inactive</span>
                        @endif
                    </div>

                    <p class="ann-item-message">{{ $announcement->message }}</p>

                    <div class="ann-item-foot">
                        <span class="ann-item-window">
                            <x-icon name="clock" />
                            @if ($announcement->starts_at || $announcement->ends_at)
                                {{ optional($announcement->starts_at)->format('M j, Y g:i A') ?? 'No start' }}
                                &ndash;
                                {{ optional($announcement->ends_at)->format('M j, Y g:i A') ?? 'No end' }}
                            @else
                                Always shown while active
                            @endif
                        </span>

                        <span class="ann-item-actions">
                            <form method="POST" action="{{ route('admin.categories.announcements.update', [$category, $announcement]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="title" value="{{ $announcement->title }}">
                                <input type="hidden" name="message" value="{{ $announcement->message }}">
                                <input type="hidden" name="starts_at" value="{{ optional($announcement->starts_at)->format('Y-m-d\TH:i') }}">
                                <input type="hidden" name="ends_at" value="{{ optional($announcement->ends_at)->format('Y-m-d\TH:i') }}">
                                <input type="hidden" name="is_active" value="{{ $announcement->is_active ? 0 : 1 }}">
                                <button type="submit" class="btn btn-sm btn-outline-brand">
                                    <x-icon name="{{ $announcement->is_active ? 'pause' : 'play' }}" />
                                    {{ $announcement->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            @if ($isPayment)
                                <a href="#tab-queue-payment" class="btn btn-sm btn-outline-brand" data-open-tab="#tab-queue-payment">
                                    <x-icon name="edit" /> Edit
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-danger-brand"
                                        data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                        data-confirm-action="{{ route('admin.categories.announcements.destroy', [$category, $announcement]) }}"
                                        data-confirm-method="DELETE"
                                        data-confirm-title="Delete Announcement"
                                        data-confirm-message="Delete this announcement?"
                                        data-confirm-submit-label="Delete">
                                    <x-icon name="trash" /> Delete
                                </button>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="card-brand ann-empty">
                <x-icon name="megaphone" />
                <span>No announcements posted yet.</span>
            </div>
        @endforelse
    </div>
</div>

@push('styles')
    <style>
        .ann-head,
        .ann-list-head {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.9rem;
        }

        .ann-chip,
        .ann-item-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.55rem;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .ann-chip {
            width: 2rem;
            height: 2rem;
        }

        .ann-chip svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .ann-label-icon {
            width: 0.85rem;
            height: 0.85rem;
            color: var(--brand-accent);
            vertical-align: -0.12em;
        }

        .ann-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.85rem 0.95rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid var(--brand-accent);
        }

        .ann-item.is-payment {
            border-left-color: var(--brand-info);
        }

        .ann-item.is-payment .ann-item-icon {
            background-color: var(--brand-info-tint);
            color: var(--brand-info);
        }

        .ann-item.is-inactive {
            border-left-color: var(--brand-border);
        }

        .ann-item.is-inactive .ann-item-icon {
            background-color: var(--brand-surface);
            color: var(--brand-muted);
        }

        .ann-item-icon {
            width: 2rem;
            height: 2rem;
        }

        .ann-item-icon svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .ann-item-body {
            flex: 1;
            min-width: 0;
        }

        .ann-item-top {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .ann-item-title {
            margin-right: 0.15rem;
            overflow-wrap: anywhere;
        }

        .ann-item-message {
            margin: 0.4rem 0 0.6rem;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .ann-item-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .ann-item-window {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.78rem;
            color: var(--brand-muted);
        }

        .ann-item-window svg {
            width: 0.9rem;
            height: 0.9rem;
        }

        .ann-item-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-left: auto;
        }

        .ann-item-actions form {
            margin: 0;
        }

        .ann-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 1.75rem 1rem;
            color: var(--brand-muted);
        }

        .ann-empty svg {
            width: 1.6rem;
            height: 1.6rem;
            color: var(--brand-accent);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-open-tab]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var trigger = document.querySelector('[data-bs-target="' + link.dataset.openTab + '"]');
                if (trigger) trigger.click();
            });
        });
    </script>
@endpush
