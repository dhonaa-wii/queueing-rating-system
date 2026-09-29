{{--
    The category picker, as a dropdown on the workspace itself. The three
    category-scoped admin modules (Reports, Event Control, Group & Panel
    Assignment) each open straight on a category now (user-directed
    2026-09-17) rather than on a grid of cards, so this is how the admin
    moves between them. Panelist Schedule Viewing (user-directed 2026-09-20)
    uses the same dropdown for the same reason, which is why this partial
    lives outside admin/ — styled by .category-picker-* in theme-head.

    Expects: $categories  — every category to offer, in the order to list them
             $current     — the one being viewed (marked, and never absent:
                            each caller merges it in)
             $routeName   — route each item links to, called with the category
    Optional: $fixed      — true when an ancestor clips its overflow, so the
                            menu has to escape it via Popper's fixed strategy
--}}
<div class="dropdown">
    <button class="btn btn-sm btn-outline-brand dropdown-toggle category-picker-btn" type="button"
            data-bs-toggle="dropdown" aria-expanded="false"
            @if ($fixed ?? false) data-bs-popper-config='{"strategy":"fixed"}' @endif>
        <x-icon name="list-check" /> Category
    </button>
    <ul class="dropdown-menu dropdown-menu-end category-picker-menu">
        @foreach ($categories as $option)
            <li>
                <a class="dropdown-item category-picker-item {{ $option->id === $current->id ? 'active' : '' }}"
                   href="{{ route($routeName, $option) }}">
                    <span>
                        <span class="category-picker-item-name">{{ $option->name }}</span>
                        <span class="category-picker-item-meta d-block">
                            {{ $option->academicYear->name ?? 'No academic year' }} &middot; {{ $option->semester->name ?? 'No semester' }}
                        </span>
                    </span>
                    @if ($option->id === $current->id)
                        <x-icon name="check" class="category-picker-item-icon" />
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
