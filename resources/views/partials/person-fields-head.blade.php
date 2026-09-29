{{--
    Column labels printed once above a list of label-less person rows
    (partials.person-fields with $rowLabel). Hidden once the rows wrap, where
    each input carries its own placeholder instead.

    $trackRequired  include the Track column
--}}
@include('partials.person-fields-styles')

<div class="person-fields person-fields-head" aria-hidden="true">
    <div class="person-grid has-row-label {{ ($trackRequired ?? false) ? 'has-track' : '' }}">
        <div></div>
        <div>Last Name</div>
        <div>First Name</div>
        <div>Middle Name</div>
        <div>Sex</div>
        <div>Section</div>
        @if ($trackRequired ?? false)
            <div>Track</div>
        @endif
    </div>
</div>
