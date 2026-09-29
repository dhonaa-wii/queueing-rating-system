{{-- The two placement actions, shared by the Schedules tab and the "Created"
     modal. They read whichever room/day checkboxes sit inside the enclosing
     [data-room-assigner]; the script in schedule.blade.php fills the hidden
     inputs at submit time. --}}
<form method="POST" action="{{ route('admin.categories.rooms.assign-to-dates', $category) }}" class="d-flex flex-wrap gap-2 mb-3" data-assign-form>
    @csrf
    <div class="assign-hidden-inputs"></div>
    <button type="submit" class="btn btn-sm btn-brand" data-assign-btn="dates" disabled>
        <x-icon name="plus" /> Assign to Selected Days
    </button>
    <button type="submit" class="btn btn-sm btn-outline-brand" data-assign-btn="all" disabled
            formaction="{{ route('admin.categories.rooms.apply-many-to-all-dates', $category) }}">
        <x-icon name="calendar-copy" /> Assign to Every Day
    </button>
</form>
