<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RfmScorer;
use App\Services\TransactionCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StaffProfile $earl;

    private Service $massage;

    private Service $pedicure;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00', 'Asia/Manila'));
        $this->admin = User::factory()->admin()->create();
        $this->earl = StaffProfile::factory()->create([
            'user_id' => User::factory()->staff()->create(['name' => 'Almerson Dosal', 'email' => 'earl.therapist@example.test']),
        ]);
        StaffProfile::factory()->create([
            'user_id' => User::factory()->staff()->create(['name' => 'Marisol Bacong']),
        ]);
        $this->massage = Service::factory()->create(['name' => 'Import Calm Touch', 'slug' => 'import-calm-touch', 'duration_minutes' => 60, 'price' => 499]);
        $this->pedicure = Service::factory()->create(['name' => 'Import Pedicure', 'slug' => 'import-pedicure', 'duration_minutes' => 60, 'price' => 189, 'is_active' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_admins_reach_the_import_pages(): void
    {
        foreach ([$this->admin, User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])] as $user) {
            $this->actingAs($user)->get(route('admin.transactions.import.create'))
                ->assertOk()
                ->assertSee('Import service history');
        }

        foreach (['receptionist', 'staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('admin.transactions.import.create'))
                ->assertForbidden();
        }

        $this->actingAs($this->admin)->get(route('admin.transactions.index'))
            ->assertSee(route('admin.transactions.import.create'), false);
    }

    public function test_template_lists_every_column(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.transactions.import.template'));

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="service-history-template.csv"');
        $this->assertStringStartsWith(implode(',', TransactionCsvImport::COLUMNS), $response->getContent());
    }

    public function test_preview_shows_totals_and_import_creates_completed_paid_visits_with_commissions(): void
    {
        $this->upload($this->csv([
            'P1-001,2026-10-01,,Import Calm Touch,499,Earl,,,,',
            'P1-002,2026-10-01,,import calm touch,499,earl,,,GCash,Regular guest',
            'P1-003,2026-10-03,15:30,Import Pedicure,189,Almerson Dosal,37.80,Ana Cruz,cash,',
        ]))->assertRedirect(route('admin.transactions.import.preview'));

        $this->actingAs($this->admin)->get(route('admin.transactions.import.preview'))
            ->assertOk()
            ->assertSee('₱1,187.00')
            ->assertSee('₱257.36')
            ->assertSee('(20%)', false)
            ->assertSee('Import 3 services');
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($this->admin)->post(route('admin.transactions.import.store'), [
            'token' => session('transaction_import.token'),
            'commissions_paid' => '1',
            'commissions_paid_on' => '2026-10-13',
        ])->assertRedirect(route('admin.transactions.index'));

        $this->assertDatabaseCount('transactions', 3);
        $first = Transaction::query()->where('import_reference', 'P1-001')->firstOrFail();
        $second = Transaction::query()->where('import_reference', 'P1-002')->firstOrFail();
        $pedicure = Transaction::query()->where('import_reference', 'P1-003')->firstOrFail();

        $this->assertSame(Transaction::PAYMENT_PAID, $first->payment_status);
        $this->assertSame('Cash', $first->payment_method);
        $this->assertSame('GCash', $second->payment_method);
        $this->assertSame('Regular guest', $second->notes);
        $this->assertSame($this->admin->id, $first->recorded_by);
        $this->assertSame(Appointment::STATUS_COMPLETED, $first->appointment->status);
        $this->assertSame($this->earl->id, $first->appointment->staff_profile_id);
        $this->assertSame('2026-10-01 13:00', $first->appointment->scheduled_start_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 14:00', $second->appointment->scheduled_start_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-03 15:30', $pedicure->appointment->scheduled_start_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $this->assertTrue($first->paid_at->equalTo($first->appointment->completed_at));

        $this->assertSame($first->customer_profile_id, $second->customer_profile_id);
        $this->assertTrue($first->customerProfile->is_import_walk_in);
        $this->assertSame('Ana Cruz', $pedicure->customerProfile->guest_name);

        $commission = $pedicure->therapistCommissions()->sole();
        $this->assertSame('0.2000', $commission->commission_rate);
        $this->assertSame('37.80', $commission->commission_amount);
        $this->assertSame(TherapistCommission::STATUS_PAID, $commission->status);
        $this->assertSame('2026-10-13', $commission->paid_at->toDateString());
        $this->assertSame('109.78', $first->therapistCommissions()->sole()->commission_amount);
        $this->assertSame('0.2200', $first->therapistCommissions()->sole()->commission_rate);
        $this->assertNull(session('transaction_import'));
    }

    public function test_reimporting_skips_existing_references_and_pending_commissions_are_supported(): void
    {
        $file = $this->csv(['P2-001,2026-10-05,,Import Calm Touch,499,Earl,,,,']);
        $this->importFile($file, paid: false);
        $this->assertSame(TherapistCommission::STATUS_PENDING, TherapistCommission::query()->sole()->status);

        $this->importFile($this->csv([
            'P2-001,2026-10-05,,Import Calm Touch,499,Earl,,,,',
            'P2-002,2026-10-06,,Import Calm Touch,399,Earl,,,,',
        ]), paid: false)->assertSessionHas('status', 'Imported 1 service; 1 already existed.');

        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame(1, CustomerProfile::query()->where('customer_code', config('casa.imports.walk_in_customer_code'))->count());
        $this->assertSame('87.78', Transaction::query()->where('import_reference', 'P2-002')->sole()->therapistCommissions()->sole()->commission_amount);
    }

    public function test_row_errors_block_the_whole_import(): void
    {
        $this->upload($this->csv([
            'E-001,2026-10-01,,Import Calm Touch,499,Earl,,,,',
            'E-002,10/01/2026,,Unknown Service,abc,Nobody,,,Card,',
            'E-001,2026-12-01,25:00,Import Calm Touch,499,Earl,600,,,',
        ]));

        $this->actingAs($this->admin)->get(route('admin.transactions.import.preview'))
            ->assertOk()
            ->assertSee('Nothing can be imported yet')
            ->assertSee('Line 3:')
            ->assertSee('Date must be written as YYYY-MM-DD.')
            ->assertSee('Service &quot;Unknown Service&quot; is not in the catalog', false)
            ->assertSee('Therapist &quot;Nobody&quot; was not found.', false)
            ->assertSee('Payment method must be one of')
            ->assertSee('Line 4:')
            ->assertSee('Date cannot be in the future.')
            ->assertSee('Time must be written as HH:MM')
            ->assertSee('Commission must be a number from zero up to the amount')
            ->assertSee('Reference E-001 is repeated on line 2.')
            ->assertDontSee('Import 1 service');

        $this->actingAs($this->admin)->post(route('admin.transactions.import.store'), [
            'token' => session('transaction_import.token'),
            'commissions_paid' => '0',
        ])->assertRedirect(route('admin.transactions.import.preview'));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_file_level_problems_are_reported(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('bad.csv', "reference,date,colour\nX,2026-10-01,red\n"));

        $this->actingAs($this->admin)->get(route('admin.transactions.import.preview'))
            ->assertSee('Unknown columns: colour.')
            ->assertSee('Missing columns: service, amount, therapist.');

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.import.upload'), ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_ambiguous_therapist_names_are_rejected(): void
    {
        StaffProfile::factory()->create(['user_id' => User::factory()->staff()->create(['name' => 'Marisol Reyes'])]);
        $this->upload($this->csv(['A-001,2026-10-01,,Import Calm Touch,499,Marisol,,,,']));

        $this->actingAs($this->admin)->get(route('admin.transactions.import.preview'))
            ->assertSee('Therapist &quot;Marisol&quot; matches more than one account. Use the full name.', false);
    }

    public function test_store_requires_the_uploaded_token(): void
    {
        $this->upload($this->csv(['T-001,2026-10-01,,Import Calm Touch,499,Earl,,,,']));

        $this->actingAs($this->admin)->post(route('admin.transactions.import.store'), [
            'token' => '00000000-0000-4000-8000-000000000000',
            'commissions_paid' => '0',
        ])->assertRedirect(route('admin.transactions.import.create'));

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_rfm_scoring_ignores_the_shared_walk_in_profile(): void
    {
        $this->importFile($this->csv([
            'R-001,2026-10-01,,Import Calm Touch,499,Earl,,,,',
            'R-002,2026-10-02,,Import Calm Touch,499,Earl,,,,',
        ]), paid: false);
        $walkIn = CustomerProfile::importWalkIn();

        $this->assertFalse(app(RfmScorer::class)->population()->has($walkIn->id));
        $this->assertSame(1, CustomerProfile::query()->where('customer_code', config('casa.imports.walk_in_customer_code'))->count());
    }

    /** @param array<int, string> $rows */
    private function csv(array $rows): UploadedFile
    {
        $contents = "\xEF\xBB\xBF".implode(',', TransactionCsvImport::COLUMNS)."\r\n".implode("\r\n", $rows)."\r\n";

        return UploadedFile::fake()->createWithContent('history.csv', $contents);
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->admin)->post(route('admin.transactions.import.upload'), ['file' => $file]);
    }

    private function importFile(UploadedFile $file, bool $paid)
    {
        $this->upload($file);

        return $this->actingAs($this->admin)->post(route('admin.transactions.import.store'), [
            'token' => session('transaction_import.token'),
            'commissions_paid' => $paid ? '1' : '0',
            'commissions_paid_on' => $paid ? '2026-10-13' : null,
        ]);
    }
}
