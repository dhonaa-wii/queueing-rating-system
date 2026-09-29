{{--
    Per-row View / Edit / Delete-confirm modals for one reference-data row.
    Expected variables: $routePrefix, $type, $label, $row, $columns
--}}
<div class="modal fade" id="view-{{ $type }}-{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ Str::singular($label) }} Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="detail-list">
                    @foreach ($columns as $column)
                        <dt>{{ $column['label'] }}</dt>
                        <dd>{{ isset($column['display']) ? $column['display']($row) : ($row->{$column['key']} ?: '—') }}</dd>
                    @endforeach

                    <dt>Status</dt>
                    <dd>
                        @if ($row->is_active)
                            <span class="badge badge-success-tint">Active</span>
                        @else
                            <span class="badge badge-muted-tint">Inactive</span>
                        @endif
                    </dd>

                    <dt>Created</dt>
                    <dd>{{ $row->created_at?->format('M d, Y g:i A') ?? '—' }}</dd>

                    <dt>Last Updated</dt>
                    <dd>{{ $row->updated_at?->format('M d, Y g:i A') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="edit-{{ $type }}-{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route("{$routePrefix}.update", [$type, $row->id]) }}">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ Str::singular($label) }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @foreach ($columns as $column)
                        <div class="mb-3">
                            <label class="form-label">{{ $column['label'] }}</label>

                            @if ($column['type'] === 'select')
                                <select name="{{ $column['key'] }}" class="form-select" required>
                                    <option value="">Select&hellip;</option>
                                    @foreach ($column['options'] as $option)
                                        <option value="{{ $option->id }}" @selected($row->{$column['key']} == $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            @elseif ($column['type'] === 'textarea')
                                <textarea name="{{ $column['key'] }}" class="form-control" rows="2">{{ $row->{$column['key']} }}</textarea>
                            @else
                                <input type="{{ $column['type'] }}" name="{{ $column['key'] }}" class="form-control" value="{{ $row->{$column['key']} }}">
                            @endif
                        </div>
                    @endforeach

                    <div class="form-check mb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit-{{ $type }}-{{ $row->id }}-is-active" @checked($row->is_active)>
                        <label class="form-check-label" for="edit-{{ $type }}-{{ $row->id }}-is-active">Active</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="delete-{{ $type }}-{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete {{ Str::singular($label) }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    Are you sure you want to permanently delete
                    <strong>{{ $row->name ?? $row->code ?? "#{$row->id}" }}</strong>?
                    This cannot be undone. If it's still referenced elsewhere in the system, deletion will be blocked.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route("{$routePrefix}.destroy", [$type, $row->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
