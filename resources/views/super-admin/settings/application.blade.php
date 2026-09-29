@extends('layouts.super-admin')

@section('title', 'Application Settings')

@section('content')
    <div class="page-shell">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h4 mb-0">Application Settings</h2>
            @if ($campus)
                <span class="badge badge-muted-tint">{{ $campus->name }} ({{ $campus->code }})</span>
            @endif
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <ul class="nav nav-tabs mb-4" id="application-settings-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-colleges" type="button">Colleges</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-academic-years" type="button">Academic Years</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-semesters" type="button">Semesters</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-colleges">
                @include('partials.reference-table', [
                    'routePrefix' => 'super-admin.settings.application',
                    'type' => 'colleges',
                    'label' => 'Colleges',
                    'rows' => $colleges,
                    'exclusiveActive' => false,
                    'columns' => [
                        ['key' => 'code', 'label' => 'Code', 'type' => 'text'],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ],
                ])
            </div>

            <div class="tab-pane fade" id="tab-academic-years">
                @include('partials.reference-table', [
                    'routePrefix' => 'super-admin.settings.application',
                    'type' => 'academic-years',
                    'label' => 'Academic Years',
                    'rows' => $academicYears,
                    'exclusiveActive' => true,
                    'columns' => [
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['key' => 'start_year', 'label' => 'Start Year', 'type' => 'number'],
                        ['key' => 'end_year', 'label' => 'End Year', 'type' => 'number'],
                    ],
                ])
            </div>

            <div class="tab-pane fade" id="tab-semesters">
                @include('partials.reference-table', [
                    'routePrefix' => 'super-admin.settings.application',
                    'type' => 'semesters',
                    'label' => 'Semesters',
                    'rows' => $semesters,
                    'exclusiveActive' => true,
                    'columns' => [
                        ['key' => 'code', 'label' => 'Code', 'type' => 'text'],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['key' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                    ],
                ])
            </div>
        </div>
    </div>
@endsection
