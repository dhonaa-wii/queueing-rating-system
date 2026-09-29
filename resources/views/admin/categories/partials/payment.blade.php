@php
    $paymentRequired = (bool) old('payment_required', optional($category->categoryPaymentSetting)->payment_required);
    $paymentTypes = old('payment_types', $category->categoryPaymentTypes->pluck('name')->all());
    $paymentTypeIds = old('payment_type_ids', $category->categoryPaymentTypes->pluck('id')->all());
    if (empty($paymentTypes)) {
        $paymentTypes = [''];
        $paymentTypeIds = [''];
    }
@endphp

<div class="card-brand p-4 h-100 pay-card">
    <div class="pay-head">
        <span class="pay-chip"><x-icon name="wallet" /></span>
        <h3 class="h6 mb-0">Payment Verification</h3>
    </div>

    <form method="POST" action="{{ route('admin.categories.payment-config.update', $category) }}" data-ajax="update">
        @csrf
        @method('PUT')

        <input type="hidden" name="payment_required" value="0">
        <label class="pay-toggle" for="payment_required">
            <span class="pay-toggle-icon"><x-icon name="shield-check" /></span>
            <span class="pay-toggle-text">Payment verification required</span>
            <span class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" name="payment_required" id="payment_required" value="1"
                       @checked($paymentRequired)>
            </span>
        </label>

        <div data-payment-fields class="{{ $paymentRequired ? '' : 'd-none' }}">
            <section class="pay-section">
                <div class="pay-section-head">
                    <span class="pay-section-title"><x-icon name="list-check" /> Payment Types</span>
                </div>
                <div data-payment-type-rows data-error-for="payment_types">
                    @foreach ($paymentTypes as $index => $name)
                        <div class="pay-row" data-payment-type-row>
                            <input type="hidden" name="payment_type_ids[]" value="{{ $paymentTypeIds[$index] ?? '' }}">
                            <input type="text" name="payment_types[]" class="form-control form-control-sm" value="{{ $name }}"
                                   maxlength="100" pattern="[A-Za-z0-9 ]+" placeholder="e.g. Defense Fee" aria-label="Payment type">
                            <button type="button" class="btn btn-sm btn-outline-danger-brand" data-remove-payment-type
                                    aria-label="Remove payment type" @disabled(count($paymentTypes) <= 1)><x-icon name="trash" /></button>
                        </div>
                    @endforeach
                </div>
                {{-- Cloned by "+ Add Payment Type" — keeps the row markup (and
                     its server-rendered icon) in exactly one place. --}}
                <template data-payment-type-row-template>
                    <div class="pay-row" data-payment-type-row>
                        <input type="hidden" name="payment_type_ids[]" value="">
                        <input type="text" name="payment_types[]" class="form-control form-control-sm"
                               maxlength="100" pattern="[A-Za-z0-9 ]+" placeholder="e.g. Defense Fee" aria-label="Payment type">
                        <button type="button" class="btn btn-sm btn-outline-danger-brand" data-remove-payment-type
                                aria-label="Remove payment type"><x-icon name="trash" /></button>
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-brand mt-1" data-add-payment-type>
                    <x-icon name="plus" /> Add Payment Type
                </button>
            </section>

            <section class="pay-section">
                <div class="pay-section-head">
                    <label for="verification_instructions" class="pay-section-title mb-0"><x-icon name="clipboard-check" /> Verification Instructions</label>
                    <span class="badge badge-info-tint pay-posted"><x-icon name="megaphone" /> Posted to Announcements</span>
                </div>
                <textarea name="verification_instructions" id="verification_instructions" class="form-control" rows="4">{{ old('verification_instructions', optional($category->categoryPaymentSetting)->verification_instructions) }}</textarea>
            </section>
        </div>

        <button type="submit" class="btn btn-brand mt-2"><x-icon name="save" /> Save Payment Configuration</button>
    </form>
</div>

@push('styles')
    <style>
        .pay-head {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.9rem;
        }

        .pay-chip,
        .pay-toggle-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.55rem;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .pay-chip {
            width: 2rem;
            height: 2rem;
        }

        .pay-chip svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .pay-toggle {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.9rem;
            border: 1px solid var(--brand-border);
            border-radius: 0.65rem;
            background-color: var(--brand-surface);
            cursor: pointer;
            transition: border-color 0.15s ease;
        }

        .pay-toggle:hover,
        .pay-toggle:focus-within {
            border-color: var(--brand-accent);
        }

        .pay-toggle-icon {
            width: 1.7rem;
            height: 1.7rem;
        }

        .pay-toggle-icon svg {
            width: 1rem;
            height: 1rem;
        }

        .pay-toggle-text {
            flex: 1;
            min-width: 0;
            font-weight: 500;
        }

        .pay-toggle .form-check-input {
            cursor: pointer;
        }

        .pay-toggle .form-check-input:checked {
            background-color: var(--brand-accent);
            border-color: var(--brand-accent);
        }

        .pay-section {
            padding: 0.75rem;
            margin-bottom: 0.9rem;
            border: 1px solid var(--brand-border);
            border-radius: 0.65rem;
            background-color: var(--brand-surface);
        }

        .pay-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.6rem;
        }

        .pay-section-title {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--brand-muted);
        }

        .pay-section-title svg {
            width: 1rem;
            height: 1rem;
            color: var(--brand-accent);
        }

        .pay-posted svg {
            width: 0.85rem;
            height: 0.85rem;
        }

        [data-payment-type-rows] {
            counter-reset: pay-type;
        }

        .pay-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            counter-increment: pay-type;
        }

        .pay-row::before {
            content: counter(pay-type);
            flex-shrink: 0;
            width: 1.5rem;
            height: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .pay-row .form-control {
            flex: 1;
            min-width: 0;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var checkbox = document.getElementById('payment_required');
            var fields = document.querySelector('[data-payment-fields]');
            var rows = document.querySelector('[data-payment-type-rows]');
            var addButton = document.querySelector('[data-add-payment-type]');
            var template = document.querySelector('[data-payment-type-row-template]');
            if (! checkbox || ! fields || ! rows || ! addButton || ! template) return;

            checkbox.addEventListener('change', function () {
                fields.classList.toggle('d-none', ! checkbox.checked);
            });

            function syncRemoveButtons() {
                var buttons = rows.querySelectorAll('[data-remove-payment-type]');
                buttons.forEach(function (button) {
                    button.disabled = buttons.length <= 1;
                });
            }

            addButton.addEventListener('click', function () {
                var fragment = template.content.cloneNode(true);
                var input = fragment.querySelector('input[type="text"]');
                rows.appendChild(fragment);
                syncRemoveButtons();
                if (input) input.focus();
            });

            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove-payment-type]');
                if (! button || button.disabled) return;
                button.closest('[data-payment-type-row]').remove();
                syncRemoveButtons();
            });

            syncRemoveButtons();
        })();
    </script>
@endpush
