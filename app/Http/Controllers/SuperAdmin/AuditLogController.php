<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only browser for the audit trail, with a CSV export of the same filter. */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    private const EXPORT_LIMIT = 100000;

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('user.profile')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $this->filters($request),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entities' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type')
                ->mapWithKeys(fn ($type) => [$type => class_basename($type)]),
            'actors' => User::query()
                ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('username')
                ->get(['id', 'username']),
            'total' => AuditLog::count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user:id,username')->orderBy('id');

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'AUDIT_LOG_EXPORTED',
            'entity_type' => AuditLog::class,
            'new_values' => array_filter($this->filters($request)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'When', 'User', 'Action', 'Entity', 'Entity ID', 'IP address', 'Old values', 'New values']);

            $written = 0;
            $query->chunkById(1000, function ($chunk) use ($out, &$written) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->username ?? 'System',
                        $log->action,
                        class_basename($log->entity_type),
                        $log->entity_id,
                        $log->ip_address,
                        $log->old_values ? json_encode($log->old_values) : '',
                        $log->new_values ? json_encode($log->new_values) : '',
                    ]);
                }

                $written += $chunk->count();

                return $written < self::EXPORT_LIMIT;
            });

            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{q: ?string, user: ?string, action: ?string, entity: ?string, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return [
            'q' => $validated['q'] ?? null,
            'user' => $validated['user'] ?? null,
            'action' => $validated['action'] ?? null,
            'entity' => $validated['entity'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    private function filtered(Request $request): Builder
    {
        $f = $this->filters($request);

        return AuditLog::query()
            ->when($f['q'], function (Builder $query, string $q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('new_values', 'like', $like));
            })
            ->when($f['user'] === 'system', fn (Builder $query) => $query->whereNull('user_id'))
            ->when($f['user'] && $f['user'] !== 'system', fn (Builder $query) => $query->where('user_id', (int) $f['user']))
            ->when($f['action'], fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($f['entity'], fn (Builder $query, string $entity) => $query->where('entity_type', $entity))
            ->when($f['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($f['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'));
    }
}
