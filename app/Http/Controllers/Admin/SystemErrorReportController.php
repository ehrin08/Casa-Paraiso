<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemErrorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemErrorReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['open', 'resolved'])],
            'family' => ['nullable', Rule::in(['4xx', '5xx'])],
            'status' => ['nullable', 'integer', 'between:400,599'],
            'category' => ['nullable', Rule::in([
                SystemErrorReport::CATEGORY_CLIENT_ERROR,
                SystemErrorReport::CATEGORY_SERVER_ERROR,
                SystemErrorReport::CATEGORY_FEATURE_UNAVAILABLE,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $reports = SystemErrorReport::query()
            ->with(['lastUser', 'resolvedBy'])
            ->when(($filters['state'] ?? null) === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when(($filters['state'] ?? null) === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->when(($filters['family'] ?? null) === '4xx', fn ($query) => $query->whereBetween('http_status', [400, 499]))
            ->when(($filters['family'] ?? null) === '5xx', fn ($query) => $query->whereBetween('http_status', [500, 599]))
            ->when(isset($filters['status']), fn ($query) => $query->where('http_status', $filters['status']))
            ->when(isset($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(isset($filters['date_from']), fn ($query) => $query->where('last_seen_at', '>=', $filters['date_from'].' 00:00:00'))
            ->when(isset($filters['date_to']), fn ($query) => $query->where('last_seen_at', '<=', $filters['date_to'].' 23:59:59'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('last_request_id', 'like', "%{$search}%")
                        ->orWhere('exception_class', 'like', "%{$search}%")
                        ->orWhere('last_route_name', 'like', "%{$search}%")
                        ->orWhere('last_request_path', 'like', "%{$search}%")
                        ->orWhereHas('occurrences', fn ($occurrences) => $occurrences->where('request_id', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('last_seen_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.system-errors.index', [
            'reports' => $reports,
            'filters' => $filters,
            'search' => $search,
            'summary' => [
                'openServer' => SystemErrorReport::query()->unresolved()->where('category', SystemErrorReport::CATEGORY_SERVER_ERROR)->count(),
                'openClient' => SystemErrorReport::query()->unresolved()->where('category', SystemErrorReport::CATEGORY_CLIENT_ERROR)->count(),
                'unavailable' => SystemErrorReport::query()->unresolved()->where('category', SystemErrorReport::CATEGORY_FEATURE_UNAVAILABLE)->count(),
            ],
        ]);
    }

    public function show(SystemErrorReport $systemErrorReport): View
    {
        $systemErrorReport->load(['lastUser', 'resolvedBy']);

        return view('admin.system-errors.show', [
            'report' => $systemErrorReport,
            'occurrences' => $systemErrorReport->occurrences()
                ->with('user')
                ->latest('occurred_at')
                ->paginate((int) config('casa.pagination.per_page', 15), ['*'], 'occurrences_page')
                ->withQueryString(),
        ]);
    }

    public function resolve(Request $request, SystemErrorReport $systemErrorReport): RedirectResponse
    {
        $systemErrorReport->forceFill([
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        $this->forgetBadge();

        return redirect()->route('admin.system-errors.show', $systemErrorReport)->with('status', 'system-error-resolved');
    }

    public function reopen(SystemErrorReport $systemErrorReport): RedirectResponse
    {
        $systemErrorReport->forceFill([
            'resolved_by' => null,
            'resolved_at' => null,
        ])->save();

        $this->forgetBadge();

        return redirect()->route('admin.system-errors.show', $systemErrorReport)->with('status', 'system-error-reopened');
    }

    private function forgetBadge(): void
    {
        Cache::forget('system-error-reports:unresolved-server-count');
    }
}
