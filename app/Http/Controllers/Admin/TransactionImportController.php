<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransactionImportConfirmRequest;
use App\Http\Requests\Admin\TransactionImportRequest;
use App\Services\TransactionCsvImport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransactionImportController extends Controller
{
    private const SESSION_KEY = 'transaction_import';

    public function __construct(private readonly TransactionCsvImport $importer) {}

    public function create(): View
    {
        return view('admin.transactions.import.create', [
            'columns' => TransactionCsvImport::COLUMNS,
            'required' => TransactionCsvImport::REQUIRED_COLUMNS,
        ]);
    }

    public function template(): Response
    {
        return response(TransactionCsvImport::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="service-history-template.csv"',
        ]);
    }

    public function upload(TransactionImportRequest $request): RedirectResponse
    {
        $this->forgetUpload($request);
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('imports', $token.'.csv', 'local');
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'path' => $path,
            'name' => Str::limit($request->file('file')->getClientOriginalName(), 120),
        ]);

        return redirect()->route('admin.transactions.import.preview');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $upload = $this->storedUpload($request);

        if (! $upload) {
            return redirect()->route('admin.transactions.import.create')
                ->with('error', __('Upload the CSV file again.'));
        }

        $result = $this->importer->parse(Storage::disk('local')->path($upload['path']));
        $newRows = collect($result['rows'])->reject(fn (array $row) => $row['already_imported']);

        return view('admin.transactions.import.preview', [
            'upload' => $upload,
            'result' => $result,
            'summary' => $this->importer->summary($result['rows']),
            'newCount' => $newRows->count(),
            'skipCount' => count($result['rows']) - $newRows->count(),
            'paidOn' => $newRows->max('date') ?? today()->toDateString(),
            'canImport' => $result['file_errors'] === [] && $result['errors'] === [] && $newRows->isNotEmpty(),
        ]);
    }

    public function store(TransactionImportConfirmRequest $request): RedirectResponse
    {
        $upload = $this->storedUpload($request);

        if (! $upload || ! hash_equals($upload['token'], (string) $request->validated('token'))) {
            return redirect()->route('admin.transactions.import.create')
                ->with('error', __('Upload the CSV file again.'));
        }

        $result = $this->importer->parse(Storage::disk('local')->path($upload['path']));

        if ($result['file_errors'] !== [] || $result['errors'] !== []) {
            return redirect()->route('admin.transactions.import.preview');
        }

        $paidAt = $request->boolean('commissions_paid')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $request->validated('commissions_paid_on'))->setTime(12, 0)
            : null;
        $outcome = $this->importer->import($result['rows'], $request->user(), $paidAt);
        $this->forgetUpload($request);

        return redirect()->route('admin.transactions.index')->with('status', trans_choice(
            '{0} No new rows were imported; :skipped already existed.|{1} Imported 1 service; :skipped already existed.|[2,*] Imported :count services; :skipped already existed.',
            $outcome['created'],
            ['count' => $outcome['created'], 'skipped' => $outcome['skipped']],
        ));
    }

    /** @return array{token: string, path: string, name: string}|null */
    private function storedUpload(Request $request): ?array
    {
        $upload = $request->session()->get(self::SESSION_KEY);

        return is_array($upload) && Storage::disk('local')->exists($upload['path'] ?? '') ? $upload : null;
    }

    private function forgetUpload(Request $request): void
    {
        $upload = $request->session()->pull(self::SESSION_KEY);

        if (is_array($upload) && isset($upload['path'])) {
            Storage::disk('local')->delete($upload['path']);
        }
    }
}
