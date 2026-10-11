{{--
    One sidebar toggle. $action = 'close' (in the sidebar's header) or 'open'
    (in the top bar). See sidebar-toggle-head for how they behave.
--}}
@php $isOpen = ($action ?? 'close') === 'open'; @endphp
<button type="button"
        class="sidebar-toggle {{ $isOpen ? 'sidebar-toggle-open' : 'sidebar-toggle-close' }}"
        id="{{ $isOpen ? 'admin-sidebar-open' : 'admin-sidebar-close' }}"
        aria-controls="admin-sidebar"
        aria-label="{{ $isOpen ? 'Open sidebar' : 'Close sidebar' }}"
        title="{{ $isOpen ? 'Open sidebar' : 'Close sidebar' }}">
    <x-icon name="panel-left" />
</button>
