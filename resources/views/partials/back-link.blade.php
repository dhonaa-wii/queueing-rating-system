{{--
    Modern back-navigation affordance — just a chevron, optionally with a
    plain "Back" label. Never the destination name (e.g. "Back to X"); the
    page's own heading already says where you are.

    Expects: $href
    Optional: $label (defaults to 'Back'; pass '' for an icon-only arrow)
              $button (true renders it as an outline button instead of a
              plain inline link; default false)
              $fullWidth (only relevant when $button is true)
              $class (extra classes appended to whichever variant renders)
--}}
@php
    $label = $label ?? 'Back';
    $variantClass = ($button ?? false)
        ? 'btn btn-outline-brand back-btn' . (($fullWidth ?? false) ? ' w-100' : '')
        : 'back-link';
@endphp
<a href="{{ $href }}" class="{{ $variantClass }}{{ isset($class) ? ' '.$class : '' }}">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>
    @if ($label !== '')<span>{{ $label }}</span>@endif
</a>
