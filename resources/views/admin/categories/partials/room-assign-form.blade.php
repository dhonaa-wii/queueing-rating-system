{{-- The placement action, shared by the Schedules tab and the "Created"
     modal. Hidden until at least one room and one day are ticked. They read whichever room/day checkboxes sit inside the enclosing
     [data-room-assigner]; the script in schedule.blade.php fills the hidden
     inputs at submit time. --}}
<form method="POST" action="{{ route('admin.categories.rooms.assign-to-dates', $category) }}" class="d-none flex-wrap gap-2 {{ $formClass ?? 'mb-3' }}" data-assign-form>
    @csrf
    <div class="assign-hidden-inputs"></div>
    <button type="submit" class="btn btn-sm btn-brand" data-assign-btn="dates">
        <x-icon name="plus" /> Assign to Selected Days
    </button>
</form>
