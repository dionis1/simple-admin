<?php

namespace App\Http\Controllers;

use App\Governance\Audit;
use App\Governance\AuditHistory;
use App\Models\AuditEvent;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    public function __invoke(Request $request, AuditHistory $history): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize('export', AuditEvent::class);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $rows = $history->query($actor, $data['search'] ?? '')->limit(1000)->get();
        app(Audit::class)->record('audit.exported', app(TenantContext::class)->resolve($actor)->organization_id, 'AuditEvent', null, ['count' => $rows->count()], $actor);

        return response()->streamDownload(function () use ($rows, $history): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fputcsv($out, ['id', 'event', 'actor_id', 'subject_user_id', 'organization_id', 'target_type', 'target_id', 'timestamp', 'correlation_id', 'redacted_changes'], ',', '"', '');
            foreach ($rows as $row) {
                $cells = [(string) $row->id, $row->event, (string) $row->actor_id, (string) $row->getAttribute('subject_user_id'), (string) $row->organization_id, $row->target_type, (string) $row->target_id, $row->occurred_at->toIso8601String(), $row->correlation_id, json_encode($row->changes, JSON_THROW_ON_ERROR)];
                fputcsv($out, array_map($history->cell(...), $cells), ',', '"', '');
            }
            fclose($out);
        }, 'audit-history.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff']);
    }
}
