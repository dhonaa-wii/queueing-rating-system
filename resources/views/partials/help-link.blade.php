{{-- Topbar link to the Help Center (route `help`). $chapter jumps straight to
     the reader's own role chapter; $newTab keeps a signed-in user's place in
     the page they were working on. Styled in theme-head (.help-link). --}}
@php
    $chapter = $chapter ?? null;
    $newTab = $newTab ?? false;
@endphp
<a href="{{ route('help') }}{{ $chapter ? '#' . $chapter : '' }}" class="help-link" @if ($newTab) target="_blank" rel="noopener" @endif aria-label="Help Center">
    <x-icon name="help-circle" />
    <span class="help-link-label">Help Center</span>
</a>
