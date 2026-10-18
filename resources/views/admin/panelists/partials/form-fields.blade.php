{{--
    Shared fields for the Add Panelist and Edit Panelist modals.
    $idPrefix keeps element ids unique when the edit modal is repeated once
    per row. $panelist is null for Add, or the User being edited for Edit.
--}}
@php
    $profile = $panelist->profile ?? null;
    $panelistProfile = $panelist->panelistProfile ?? null;
    // Internal = the registering Admin's college, locked; external = typed.
    $collegeName = $panelist
        ? ($panelistProfile->college->name ?? '—')
        : (auth()->user()->administratorProfile?->college?->name ?? '—');
    $isExternal = (bool) ($panelistProfile->is_external ?? false);
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-first_name" class="form-label">First Name <span class="text-danger">*</span></label>
        <input type="text" name="first_name" id="{{ $idPrefix }}-first_name" class="form-control" value="{{ $profile->first_name ?? '' }}" required>
    </div>
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-middle_name" class="form-label">Middle Name</label>
        <input type="text" name="middle_name" id="{{ $idPrefix }}-middle_name" class="form-control" value="{{ $profile->middle_name ?? '' }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
        <input type="text" name="last_name" id="{{ $idPrefix }}-last_name" class="form-control" value="{{ $profile->last_name ?? '' }}" required>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-suffix" class="form-label">Suffix</label>
        <input type="text" name="suffix" id="{{ $idPrefix }}-suffix" class="form-control" value="{{ $profile->suffix ?? '' }}">
    </div>
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-sex" class="form-label">Sex <span class="text-danger">*</span></label>
        <select name="sex" id="{{ $idPrefix }}-sex" class="form-select" required>
            <option value="">Select</option>
            @foreach (\App\Models\Student::SEXES as $sex)
                <option value="{{ $sex }}" @selected(($profile->sex ?? '') === $sex)>{{ $sex }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-contact_number" class="form-label">Contact Number</label>
        <input type="text" name="contact_number" id="{{ $idPrefix }}-contact_number" class="form-control" placeholder="optional" value="{{ $profile->contact_number ?? '' }}">
    </div>
</div>

<div class="row g-3 mb-3" data-affiliation-fields>
    <div class="col-md-4">
        <label for="{{ $idPrefix }}-affiliation" class="form-label">Panelist Type <span class="text-danger">*</span></label>
        <select name="affiliation" id="{{ $idPrefix }}-affiliation" class="form-select" data-affiliation required>
            <option value="INTERNAL" @selected(! $isExternal)>Internal</option>
            <option value="EXTERNAL" @selected($isExternal)>External</option>
        </select>
    </div>
    <div class="col-md-8" data-affiliation-internal @if ($isExternal) hidden @endif>
        <label for="{{ $idPrefix }}-department-internal" class="form-label">Department</label>
        <input type="text" id="{{ $idPrefix }}-department-internal" class="form-control" value="{{ $collegeName }}" disabled>
    </div>
    <div class="col-md-8" data-affiliation-external @unless ($isExternal) hidden @endunless>
        <label for="{{ $idPrefix }}-external_department" class="form-label">Department <span class="text-danger">*</span></label>
        <input type="text" name="external_department" id="{{ $idPrefix }}-external_department" class="form-control" maxlength="150" value="{{ $panelistProfile->external_department ?? '' }}" @if ($isExternal) required @else disabled @endif>
    </div>
</div>

<div class="mb-0">
    <label for="{{ $idPrefix }}-specialization" class="form-label">Field of Specialization</label>
    <input type="text" name="specialization" id="{{ $idPrefix }}-specialization" class="form-control" value="{{ $panelistProfile->specialization ?? '' }}">
</div>

@once
    @push('scripts')
        <script>
            // Internal shows the locked college; External shows the typed
            // department, which is only submitted (and required) while shown.
            document.addEventListener('change', function (event) {
                const select = event.target.closest('[data-affiliation]');
                if (! select) return;

                const root = select.closest('[data-affiliation-fields]');
                const external = select.value === 'EXTERNAL';
                const typed = root.querySelector('[data-affiliation-external] input');

                root.querySelector('[data-affiliation-internal]').hidden = external;
                root.querySelector('[data-affiliation-external]').hidden = ! external;
                typed.disabled = ! external;
                typed.required = external;
                if (external) typed.focus();
            });
        </script>
    @endpush
@endonce