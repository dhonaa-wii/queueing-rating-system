{{-- Muted "ARPQRS · College of … · 2026-2027" line closing a filled-in
     evaluation sheet (live tablet panel, Reports viewer, Panelist completed
     view). Same wording as the Reports PDF footer (GradePdfExporter::footerText),
     from the category's own college and academic year. Expects $attempt. Not
     part of the Evaluation Library builder paper. --}}
@php
    $markCategory = $attempt->researchGroup->loadMissing(['category.college', 'category.academicYear'])->category;
    $markLine = implode(' · ', array_filter([
        'ARPQRS',
        $markCategory?->college?->name,
        $markCategory?->academicYear?->name,
    ]));
@endphp
<div class="eval-paper-mark">{{ $markLine }}</div>
