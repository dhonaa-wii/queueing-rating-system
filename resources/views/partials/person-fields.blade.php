{{--
    One student's fields — last, first and middle name, sex, section and (when
    the category asks for it) track. Shared by the public
    registration form and the Admin Add/Edit Group modals so a leader and every
    member are collected identically.

    One compact line per person (2026-09-30, user-directed): each field is
    sized to what it holds — a name, a one-letter sex, a two-character
    section — instead of three wide name fields plus a second row. The grid
    responds to the width it is given (container query), not the window, so
    the same partial reads as one line on the public page and wraps to two or
    one per line inside a narrow Admin modal or on a phone.

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
    $rowLabel     optional: render as a label-less row led by this text (e.g.
                  "1"), for a list whose column labels are printed once above
                  it with partials.person-fields-head
--}}
@include('partials.name-input-script')
@include('partials.section-input-script')
@include('partials.person-fields-styles')

@php
    $required = $required ?? false;
    $small = $small ?? false;
    $disabled = $disabled ?? false;
    $showErrors = $showErrors ?? false;
    $trackRequired = $trackRequired ?? false;
    $trackOptions = $trackOptions ?? [];
    $rowLabel = $rowLabel ?? null;
    $labelled = $rowLabel === null;
    $labelClass = 'form-label'.($small ? ' small' : '').($required ? '' : ' text-brand-muted small');
    $controlClass = 'form-control'.($small ? ' form-control-sm' : '');
    $selectClass = 'form-select'.($small ? ' form-select-sm' : '');
    $star = $required ? ' <span class="text-brand-accent">*</span>' : '';
    $errorKey = fn (string $name) => preg_replace('/\[([^\]]+)\]/', '.$1', $name);
    $sexValue = $values['sex'] ?? '';
    $names = ['last_name' => 'Last Name', 'first_name' => 'First Name', 'middle_name' => 'Middle Name'];
@endphp

<div class="person-fields">
    <div class="person-grid {{ $trackRequired ? 'has-track' : '' }} {{ $labelled ? '' : 'has-row-label' }}">
        @unless ($labelled)
            <div class="person-row-label"><span class="person-row-word">Member </span>{{ $rowLabel }}</div>
        @endunless

        @foreach ($names as $field => $label)
            @php $name = $nameFor($field); @endphp
            <div class="person-cell person-{{ str_replace('_', '-', $field) }}">
                @if ($labelled)
                    <label for="{{ $idBase }}_{{ $field }}" class="{{ $labelClass }}">{!! $label.$star !!}</label>
                @endif
                <input type="text" name="{{ $name }}" id="{{ $idBase }}_{{ $field }}" class="{{ $controlClass }}" maxlength="100" autocomplete="off" data-name-input
                       @unless ($labelled) placeholder="{{ $label }}" aria-label="{{ $label }}" @endunless
                       @required($required) @disabled($disabled) value="{{ $values[$field] ?? '' }}">
                @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
            </div>
        @endforeach

        @php $name = $nameFor('sex'); @endphp
        <div class="person-cell person-sex">
            @if ($labelled)
                <label for="{{ $idBase }}_sex" class="{{ $labelClass }}">{!! 'Sex'.$star !!}</label>
            @endif
            <select name="{{ $name }}" id="{{ $idBase }}_sex" class="{{ $selectClass }}" @unless ($labelled) aria-label="Sex" @endunless @required($required) @disabled($disabled)>
                <option value="">{{ $labelled ? 'Select' : 'Sex' }}</option>
                @foreach (\App\Models\Student::SEXES as $sex)
                    <option value="{{ $sex }}" @selected($sexValue === $sex)>{{ $sex }}</option>
                @endforeach
            </select>
            @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
        </div>

        @php $name = $nameFor('section'); @endphp
        <div class="person-cell person-section">
            @if ($labelled)
                <label for="{{ $idBase }}_section" class="{{ $labelClass }}">{!! 'Section'.$star !!}</label>
            @endif
            <input type="text" name="{{ $name }}" id="{{ $idBase }}_section" class="{{ $controlClass }}" maxlength="2" placeholder="e.g. 4B" autocapitalize="characters" autocomplete="off" data-section-input
                   pattern="[1-9][A-Z]" title="{{ \App\Services\ResearchGroupRegistrationService::SECTION_FORMAT_MESSAGE }}"
                   @unless ($labelled) aria-label="Section" @endunless
                   @required($required) @disabled($disabled) value="{{ $values['section'] ?? '' }}">
            @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
        </div>

        @if ($trackRequired)
            @php $name = $nameFor('research_track_name'); @endphp
            <div class="person-cell person-track">
                @if ($labelled)
                    <label for="{{ $idBase }}_track" class="{{ $labelClass }}">{!! 'Track'.$star !!}</label>
                @endif
                @if ($trackOptions !== [])
                    <select name="{{ $name }}" id="{{ $idBase }}_track" class="{{ $selectClass }}" @unless ($labelled) aria-label="Track" @endunless @required($required) @disabled($disabled)>
                        <option value="">{{ $labelled ? 'Select' : 'Track' }}</option>
                        @foreach ($trackOptions as $track)
                            <option value="{{ $track }}" @selected(($values['research_track_name'] ?? '') === $track)>{{ $track }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="{{ $name }}" id="{{ $idBase }}_track" class="{{ $controlClass }}" maxlength="150" autocomplete="off"
                           @unless ($labelled) placeholder="Track" aria-label="Track" @endunless
                           @required($required) @disabled($disabled) value="{{ $values['research_track_name'] ?? '' }}">
                @endif
                @if ($showErrors)<div class="invalid-feedback" data-error-for="{{ $errorKey($name) }}"></div>@endif
            </div>
        @endif
    </div>
</div>
