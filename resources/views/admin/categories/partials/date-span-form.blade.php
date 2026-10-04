{{-- The date span form. Rendered inline on the Schedules tab while the
     category has no dates yet, and inside the Add Date modal afterwards.
     $focusable puts the dashboard's "date-span" focus key on the inputs;
     in the modal that key sits on the Add Date button instead, since a
     closed modal's inputs can't be scrolled to. --}}
@php
    $focusAttrs = $focusable
        ? 'data-focus="date-span" data-focus-reveal="[data-bs-target=\'#tab-schedules\']"'
        : '';
    $spanErrors = collect(['start_date', 'end_date', 'event_start_time', 'event_end_time', 'break_start_time', 'break_end_time'])
        ->flatMap(fn ($field) => $errors->get($field));
@endphp

<form method="POST" action="{{ route('admin.categories.dates.span.store', $category) }}">
    @csrf

    @if ($spanErrors->isNotEmpty())
        <div class="alert alert-danger small py-2">
            @foreach ($spanErrors as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6">
            <label for="span_start_date" class="form-label">Start Date</label>
            <input type="date" name="start_date" id="span_start_date" class="form-control" required value="{{ old('start_date') }}" {!! $focusAttrs !!}>
        </div>
        <div class="col-6">
            <label for="span_end_date" class="form-label">End Date</label>
            <input type="date" name="end_date" id="span_end_date" class="form-control" required value="{{ old('end_date') }}" {!! $focusAttrs !!}>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <label for="span_event_start_time" class="form-label">Daily Start Time</label>
            <input type="time" name="event_start_time" id="span_event_start_time" class="form-control" required value="{{ old('event_start_time') }}" {!! $focusAttrs !!}>
        </div>
        <div class="col-6">
            <label for="span_event_end_time" class="form-label">Daily End Time</label>
            <input type="time" name="event_end_time" id="span_event_end_time" class="form-control" required value="{{ old('event_end_time') }}" {!! $focusAttrs !!}>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <label for="span_break_start_time" class="form-label">Break Start Time</label>
            <input type="time" name="break_start_time" id="span_break_start_time" class="form-control" value="{{ old('break_start_time') }}">
        </div>
        <div class="col-6">
            <label for="span_break_end_time" class="form-label">Break End Time</label>
            <input type="time" name="break_end_time" id="span_break_end_time" class="form-control" value="{{ old('break_end_time') }}">
        </div>
    </div>

    <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Date Span</button>
</form>
