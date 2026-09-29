{{--
    Expects: $letterhead (an EvaluationLetterhead, already resolved for the
    college in scope — see EvaluationLetterhead::forCollege()).

    The one letterhead block for every "paper" surface: the Evaluation
    Library builder, the room-session live evaluation panel and Reports'
    read-only sheets all include this, so a logo or header line can never
    show up on one and not the others. Styling lives in
    admin/evaluation-library/partials/paper-styles.blade.php, which all
    three already load.
--}}
@php
    $letterheadLines = array_filter([
        $letterhead->line_1 ?? null,
        $letterhead->line_2 ?? null,
        $letterhead->line_3 ?? null,
        $letterhead->line_4 ?? null,
    ]);
@endphp

<div class="eval-paper-letterhead">
    @if ($letterhead->logo_path)
        <img src="{{ Storage::url($letterhead->logo_path) }}" alt="" class="eval-paper-logo">
    @endif

    @if ($letterheadLines !== [])
        <div class="eval-paper-letterhead-text">
            @foreach ($letterheadLines as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
    @endif

    @if ($letterhead->secondary_logo_path)
        <img src="{{ Storage::url($letterhead->secondary_logo_path) }}" alt="" class="eval-paper-logo">
    @endif
</div>
