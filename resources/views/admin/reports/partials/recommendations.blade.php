{{--
    Recommendations & comments grid — the View modal's body, fetched fresh
    each time the modal opens (see RecommendationSummaryService). Rows come in
    the order the presentations actually completed.
--}}
@if ($rows->isEmpty())
    <div class="rp-empty">No presentations have been completed yet.</div>
@else
    <div class="rp-rec-count">{{ $rows->count() }} {{ Str::plural('group', $rows->count()) }} completed so far</div>

    <div class="rp-rec-grid">
        <div class="rp-rec-row rp-rec-head">
            <div>#</div>
            <div>Submitted</div>
            <div>Proponents</div>
            <div>Comments</div>
        </div>

        @foreach ($rows as $row)
            <div class="rp-rec-row">
                <div class="rp-rec-seq">{{ $row->sequence }}</div>

                <div>
                    <div class="fw-semibold">{{ $row->completed_at?->format('M j, Y') ?? '—' }}</div>
                    <div class="text-brand-muted small">{{ $row->completed_at?->format('g:i A') }}</div>
                </div>

                <div>
                    <div class="rp-best-ref">
                        {{ $row->group_reference }}
                        @if ($row->attempt_number > 1)
                            <span class="badge badge-muted-tint ms-1">Attempt {{ $row->attempt_number }}</span>
                        @endif
                    </div>
                    @if ($row->project_title)
                        <div class="rp-rec-title">{{ $row->project_title }}</div>
                    @endif
                    @foreach ($row->proponents as $proponent)
                        <div class="small">
                            {{ $proponent->name }}
                            <span class="text-brand-muted">&middot; {{ $proponent->section ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>

                <div>
                    @forelse ($row->comments as $comment)
                        <div class="rp-rec-comment">
                            <span class="rp-rec-panelist">{{ $comment->panelist }}</span>
                            <span class="rp-rec-text">{{ $comment->comment }}</span>
                        </div>
                    @empty
                        <span class="text-brand-muted">—</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endif
