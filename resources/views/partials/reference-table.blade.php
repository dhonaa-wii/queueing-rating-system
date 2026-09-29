{{--
    Generic list + inline add form for one reference-data type. Used by Super
    Admin's Application Settings (super-admin.settings.application.*) —
    $routePrefix is kept generic in case another screen needs to reuse it.
    Expected variables: $type, $label, $rows, $columns, $exclusiveActive (bool), $routePrefix
    Each entry in $columns: ['key' => ..., 'label' => ..., 'type' => 'text'|'number'|'select'|'textarea', 'options' => Collection (for select), 'display' => Closure (optional, for table cell)]
--}}
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-brand p-4">
            <h3 class="h6 mb-3">Add {{ Str::singular($label) }}</h3>

            <form method="POST" action="{{ route("{$routePrefix}.store", $type) }}">
                @csrf

                @foreach ($columns as $column)
                    <div class="mb-3">
                        <label class="form-label">{{ $column['label'] }}</label>

                        @if ($column['type'] === 'select')
                            <select name="{{ $column['key'] }}" class="form-select" required>
                                <option value="">Select&hellip;</option>
                                @foreach ($column['options'] as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @elseif ($column['type'] === 'textarea')
                            <textarea name="{{ $column['key'] }}" class="form-control" rows="2"></textarea>
                        @else
                            <input type="{{ $column['type'] }}" name="{{ $column['key'] }}" class="form-control">
                        @endif
                    </div>
                @endforeach

                <div class="form-check mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $type }}-is-active" checked>
                    <label class="form-check-label" for="{{ $type }}-is-active">Active</label>
                </div>

                <button type="submit" class="btn btn-brand"><x-icon name="plus" /> Add</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        @if ($rows->isEmpty())
            <div class="card-brand p-4 text-center text-brand-muted">No {{ strtolower($label) }} yet.</div>
        @else
            <div class="card-brand p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th class="{{ $loop->first ? 'ps-3' : '' }}">{{ $column['label'] }}</th>
                                @endforeach
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($columns as $column)
                                        <td class="{{ $loop->first ? 'ps-3' : '' }}">
                                            {{ isset($column['display']) ? $column['display']($row) : $row->{$column['key']} }}
                                        </td>
                                    @endforeach
                                    <td>
                                        @if ($row->is_active)
                                            <span class="badge badge-success-tint">Active</span>
                                        @else
                                            <span class="badge badge-muted-tint">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="dropdown">
                                            <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">
                                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100" data-bs-toggle="modal" data-bs-target="#view-{{ $type }}-{{ $row->id }}">
                                                        <x-icon name="eye" class="dropdown-item-icon" /> View
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100" data-bs-toggle="modal" data-bs-target="#edit-{{ $type }}-{{ $row->id }}">
                                                        <x-icon name="edit" class="dropdown-item-icon" /> Edit
                                                    </button>
                                                </li>

                                                @if ($exclusiveActive && ! $row->is_active)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route("{$routePrefix}.set-active", [$type, $row->id]) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100">
                                                                <x-icon name="check" class="dropdown-item-icon" /> Set Active
                                                            </button>
                                                        </form>
                                                    </li>
                                                @elseif (! $exclusiveActive)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route("{$routePrefix}.toggle-active", [$type, $row->id]) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100">
                                                                <x-icon name="power" class="dropdown-item-icon" /> {{ $row->is_active ? 'Deactivate' : 'Activate' }}
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif

                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger-brand w-100" data-bs-toggle="modal" data-bs-target="#delete-{{ $type }}-{{ $row->id }}">
                                                        <x-icon name="trash" class="dropdown-item-icon" /> Delete
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($rows as $row)
                @include('partials.reference-table-row-modals', [
                    'routePrefix' => $routePrefix,
                    'type' => $type,
                    'label' => $label,
                    'row' => $row,
                    'columns' => $columns,
                ])
            @endforeach
        @endif
    </div>
</div>
