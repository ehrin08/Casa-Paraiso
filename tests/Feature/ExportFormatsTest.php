<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportFormatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $customer->user->update(['name' => '=SUM(1,1)']);
        $service = Service::factory()->create(['name' => 'Signature massage']);
        $staff = StaffProfile::factory()->create();
        $visit = CarbonImmutable::parse('2026-09-30 13:00:00', 'Asia/Manila');
        $appointment = Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'scheduled_start_at' => $visit,
            'scheduled_end_at' => $visit->addHour(),
        ]);
        $this->transaction = Transaction::factory()->for($customer)->for($service)->for($appointment)->create([
            'amount' => 1234.50,
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => $visit,
            'recorded_by' => $this->admin->id,
        ]);
        TherapistCommission::factory()->create([
            'staff_profile_id' => $staff->id,
            'appointment_id' => $appointment->id,
            'transaction_id' => $this->transaction->id,
            'commission_amount' => -45.25,
            'earned_at' => $visit,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function pages(): array
    {
        return [
            'reports' => ['admin.reports.export', ['range' => 'all', 'category' => 'transactions']],
            'payments' => ['admin.transactions.export', []],
            'commissions' => ['admin.commissions.export', []],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_page_downloads_each_format_with_matching_type_and_extension(string $route, array $query): void
    {
        foreach (['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'] as $format => $type) {
            $response = $this->actingAs($this->admin)->get(route($route, [...$query, 'format' => $format], false))->assertOk();

            $this->assertStringStartsWith($type, $response->headers->get('Content-Type'));
            $this->assertMatchesRegularExpression('/filename="?casa-paraiso-[a-z]+-\d{8}-\d{6}\.'.$format.'"?/', $response->headers->get('Content-Disposition'));
        }

        $this->actingAs($this->admin)->get(route($route, [...$query, 'format' => 'print'], false))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('window.print()', false)
            ->assertSee(config('casa.business_name'));
    }

    public function test_csv_stays_the_default_format(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('admin.transactions.export', absolute: false))
            ->assertOk()->assertDownload()->streamedContent();

        $rows = array_map(str_getcsv(...), explode("\n", trim($csv)));
        $this->assertSame(['ID', 'Customer', 'Email', 'Visit date', 'Amount', 'Payment method', 'Status'], $rows[0]);
        $this->assertSame([$this->transaction->transaction_number, "'=SUM(1,1)"], array_slice($rows[1], 0, 2));
        $this->assertSame('2026-09-30 13:00:00', $rows[1][3]);
        $this->assertSame('1234.50', $rows[1][4]);
    }

    public function test_excel_keeps_typed_cells_and_never_writes_formulas(): void
    {
        $rows = $this->xlsxRows(route('admin.transactions.export', ['format' => 'xlsx'], false));

        $this->assertSame(['ID', 'Customer', 'Email', 'Visit date', 'Amount', 'Payment method', 'Status'], $rows[0]);
        $this->assertSame('=SUM(1,1)', $rows[1][1]);
        $this->assertInstanceOf(DateTimeInterface::class, $rows[1][3]);
        $this->assertSame('2026-09-30 13:00', $rows[1][3]->format('Y-m-d H:i'));
        $this->assertEqualsWithDelta(1234.5, $rows[1][4], 0.001);

        $commissions = $this->xlsxRows(route('admin.commissions.export', ['format' => 'xlsx'], false));
        $this->assertEqualsWithDelta(-45.25, $commissions[1][10], 0.001);
    }

    public function test_pdf_and_print_show_filters_and_cap_long_lists(): void
    {
        config(['casa.exports.document_row_limit' => 1]);
        Transaction::factory()->create(['customer_profile_id' => $this->transaction->customer_profile_id, 'recorded_by' => $this->admin->id, 'appointment_id' => null, 'payment_status' => Transaction::PAYMENT_UNPAID]);

        $pdf = $this->actingAs($this->admin)->get(route('admin.transactions.export', ['format' => 'pdf'], false))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($this->admin)->get(route('admin.transactions.export', ['format' => 'print', 'payment_status' => 'paid'], false))
            ->assertOk()
            ->assertSee('Payments')
            ->assertSee('Status: paid')
            ->assertSee('₱1,234.50')
            ->assertSee('Sep 30, 2026 1:00 PM')
            ->assertDontSee('Showing the first');

        $this->actingAs($this->admin)->get(route('admin.transactions.export', ['format' => 'print'], false))
            ->assertOk()
            ->assertSee('Showing the first 1 of 2 records. Use CSV or Excel for the full list.');

        $this->actingAs($this->admin)->get(route('admin.reports.export', ['range' => 'all', 'category' => 'transactions', 'format' => 'print'], false))
            ->assertOk()
            ->assertSee('Transactions report')
            ->assertSee('All history');
    }

    public function test_pages_offer_every_format_in_the_export_menu(): void
    {
        foreach (['admin.reports.index' => 'admin.reports.export', 'admin.transactions.index' => 'admin.transactions.export', 'admin.commissions.index' => 'admin.commissions.export'] as $page => $export) {
            $response = $this->actingAs($this->admin)->get(route($page, absolute: false))->assertOk()->assertSee('data-export-menu', false);

            foreach (['csv', 'xlsx', 'pdf', 'print'] as $format) {
                $response->assertSee('data-export-format="'.$format.'"', false);
            }
            $response->assertSee(route($export), false);
        }
    }

    #[DataProvider('pages')]
    public function test_unknown_formats_are_rejected_and_other_roles_are_denied(string $route, array $query): void
    {
        $this->actingAs($this->admin)->getJson(route($route, [...$query, 'format' => 'docx'], false))
            ->assertUnprocessable()->assertJsonValidationErrors(['format']);

        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route($route, [...$query, 'format' => 'pdf'], false))
                ->assertForbidden();
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function xlsxRows(string $url): array
    {
        $response = $this->actingAs($this->admin)->get($url)->assertOk()->assertDownload();
        $path = tempnam(sys_get_temp_dir(), 'casa-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
            }
            break;
        }
        $reader->close();
        unlink($path);

        return $rows;
    }
}
