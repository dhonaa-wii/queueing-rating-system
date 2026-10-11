{{--
    Live Room (user-directed 2026-10-11): the running rooms of the panelist's
    college. Join takes a seat with the panelist's own account — no room
    login — and opens the room screen (RoomSessionController, panelist mode).
    Rooms the panelist is on the panel for come first. A panelist who isn't
    on a room's panel can still join; the room screen then routes them through
    the alternate / Admin-approval substitution flow, as before.
--}}
@extends('layouts.panelist')

@section('title', 'Live Room')

@section('heading', 'Live Room')

@section('content')
    <div class="page-shell lr-page">
        @if ($roomSessions->isEmpty())
            <div class="picker-empty">
                <h2>No room is running</h2>
                <p>Rooms appear here once an Administrator starts them in Event Control.</p>
            </div>
        @else
            <div class="lr-grid">
                @foreach ($roomSessions as $roomSession)
                    @php
                        $date = $roomSession->presentationDateRoom->presentationDate;
                        $seat = $seats[$roomSession->id];
                        $joined = $joinedRoomSessionId === $roomSession->id;
                        $mine = $onPanel[$roomSession->id];
                    @endphp
                    <div class="card-brand lr-card {{ $joined ? 'is-joined' : '' }}">
                        <div class="lr-card-head">
                            <div class="min-w-0">
                                <div class="lr-room">{{ $roomSession->presentationDateRoom->room_name }}</div>
                                <div class="lr-meta">{{ $date->category->name }} &middot; {{ $date->presentation_date->format('M j, Y') }}</div>
                            </div>
                            @if ($joined)
                                <span class="badge badge-success-tint">Joined</span>
                            @elseif ($mine)
                                <span class="badge badge-brand-tint">Your panel</span>
                            @endif
                        </div>

                        <div class="lr-seats">
                            <span class="badge {{ $seat['full'] ? 'badge-success-tint' : 'badge-muted-tint' }}">
                                {{ $seat['full'] ? 'Panel complete' : 'Panelists' }} &middot; {{ $seat['taken'] }}/{{ $seat['total'] }}
                            </span>
                        </div>

                        @if ($joined)
                            <a href="{{ route('panelist.live-room.home') }}" class="btn btn-brand btn-sm w-100"><x-icon name="arrow-right" /> Open Room</a>
                        @else
                            <form method="POST" action="{{ route('panelist.live-room.join') }}">
                                @csrf
                                <input type="hidden" name="room_session_id" value="{{ $roomSession->id }}">
                                <button type="submit" class="btn btn-brand btn-sm w-100" @disabled($seat['full'])
                                        title="{{ $seat['full'] ? 'Every seat in this room is taken' : '' }}"><x-icon name="log-in" /> Join Room</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .lr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
            gap: 0.85rem;
        }

        .lr-card {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            padding: 0.9rem;
        }

        .lr-card.is-joined {
            box-shadow: inset 0 0 0 2px var(--brand-success);
        }

        .lr-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .lr-room {
            font-weight: 600;
            font-size: var(--page-fs-heading, 1rem);
        }

        .lr-meta {
            color: var(--brand-muted);
            font-size: var(--page-fs-xs, 0.75rem);
        }
    </style>
@endpush
