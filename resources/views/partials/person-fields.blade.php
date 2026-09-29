{{--
    One student's fields — last, first and middle name, sex, section and (when
    the category asks for it) track. Shared by the public
    registration form and the Admin Add/Edit Group modals so a leader and every
    member are collected identically.

    $nameFor      Closure: field key => the input's name attribute
    $idBase       unique id prefix for this person's inputs
    $values       [last_name, first_name, middle_name, sex, section, research_track_name]
    $trackRequired  show the Track input
    $trackOptions   the category's track list; when set, Track is a dropdown of it
    $required     true for the leader (asterisks + required attribute); a member
                  row is optional as a whole, so it carries neither
    $small        compact controls (Admin modals)
    $disabled     disable every input
    $showErrors   render a data-error-for container under each input (public form)
--}}
@include('partials.name-input-script')
@include('partials.section-input-script')

@php
    $required = $required ?? false;
    $small = $small ?? false;
    $disabled = $disabled ?? false;
    $showErrors = $showErrors ?? false;
    $trackRequired = $trackRequired ?? false;
    $trackOptions = $trackOptions ?? [];
    $labelClass = 'form-label'.($small ? ' small' : '').($required ? '' : ' text-brand-muted small');
    $controlClass = 'form-control'.($small ? ' form-control-sm' : '');
    $star = $required ? ' <span class="text-brand-accent">*</span>' : '';
    $errorKey = fn (string $name) => preg_replace('/\[([^\]]+)\]/', '.$1', $name);
    $fieldSpans = $trackRequired ? ['sex' => 'col-md-3', 'section' => 'col-md-3', 'track' => 'col-md-6'] : ['sex' => 'col-md-6', 'section' => 'col-md-6'];
    $sexValue = $values['sex'] ?? '';
@endphp

<div class="row g-2">
    @foreach (['last_name' => 'Last Name', 'first_name' => 'First Name', 'middle_name' => 'Middle Name'] as $field => $label)
        @php $name = $nameFor($field); @endphp
        <div class="col-md-4">
            <label for="{{ $idBase }}_{{ $field }}" class="{{ $labelClass }}">{!! $label.$star !!}</label>
            <input type="text" name="{{ $name }}" id="{{ $idBase }}_{{ $field }}" class="{{ $controlClass }}" maxlength="100" autocomplete="off" data-name-input
                   @required($required) @disabled($disabled) value="{{ $values[$field] ?? '' }}">
            @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
        </div>
    @endforeach
</div>

<div class="row g-2 mt-0">
    @php $name = $nameFor('sex'); @endphp
    <div class="{{ $fieldSpans['sex'] }}">
        <label for="{{ $idBase }}_sex" class="{{ $labelClass }}">{!! 'Sex'.$star !!}</label>
        <select name="{{ $name }}" id="{{ $idBase }}_sex" class="form-select{{ $small ? ' form-select-sm' : '' }}" @required($required) @disabled($disabled)>
            <option value="">Select</option>
            @foreach (\App\Models\Student::SEXES as $sex)
                <option value="{{ $sex }}" @selected($sexValue === $sex)>{{ $sex }}</option>
            @endforeach
        </select>
        @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
    </div>

    @php $name = $nameFor('section'); @endphp
    <div class="{{ $fieldSpans['section'] }}">
        <label for="{{ $idBase }}_section" class="{{ $labelClass }}">{!! 'Section'.$star !!}</label>
        <input type="text" name="{{ $name }}" id="{{ $idBase }}_section" class="{{ $controlClass }}" maxlength="2" placeholder="e.g. 4B" autocapitalize="characters" autocomplete="off" data-section-input
               pattern="[1-9][A-Z]" title="{{ \App\Services\ResearchGroupRegistrationService::SECTION_FORMAT_MESSAGE }}"
               @required($required) @disabled($disabled) value="{{ $values['section'] ?? '' }}">
        @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
    </div>

    @if ($trackRequired)
        @php $name = $nameFor('research_track_name'); @endphp
        <div class="{{ $fieldSpans['track'] }}">
            <label for="{{ $idBase }}_track" class="{{ $labelClass }}">{!! 'Track'.$star !!}</label>
            @if ($trackOptions !== [])
                <select name="{{ $name }}" id="{{ $idBase }}_track" class="form-select{{ $small ? ' form-select-sm' : '' }}" @required($required) @disabled($disabled)>
                    <option value="">Select</option>
                    @foreach ($trackOptions as $track)
                        <option value="{{ $track }}" @selected(($values['research_track_name'] ?? '') === $track)>{{ $track }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" name="{{ $name }}" id="{{ $idBase }}_track" class="{{ $controlClass }}" maxlength="150" autocomplete="off"
                       @required($required) @disabled($disabled) value="{{ $values['research_track_name'] ?? '' }}">
            @endif
            @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
        </div>
    @endif
</div>
