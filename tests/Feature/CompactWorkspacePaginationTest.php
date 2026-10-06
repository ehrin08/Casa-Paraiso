<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CompactWorkspacePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_records_use_the_fixed_page_size_and_keep_filter_and_sort_state(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createCustomers(16);

        $parameters = [
            'q' => 'Pagination Customer',
            'status' => 'active',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 1,
        ];

        $firstResponse = $this->actingAs($admin)
            ->get(route('admin.customers.index', $parameters, false))
            ->assertOk()
            ->assertSee('Pagination Customer 01')
            ->assertSee('Pagination Customer 15')
            ->assertDontSee('Pagination Customer 16')
            ->assertSee('data-pagination', false)
            ->assertSee('data-first="1"', false)
            ->assertSee('data-last="15"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Page 1 of 2');

        /** @var LengthAwarePaginator $firstPage */
        $firstPage = $firstResponse->viewData('customers');
        $this->assertCount(15, $firstPage->items());
        $this->assertSame(16, $firstPage->total());
        $this->assertSame(15, $firstPage->perPage());

        parse_str((string) parse_url($firstPage->url(2), PHP_URL_QUERY), $pageTwoQuery);
        $this->assertSame('Pagination Customer', $pageTwoQuery['q']);
        $this->assertSame('active', $pageTwoQuery['status']);
        $this->assertSame('name', $pageTwoQuery['sort']);
        $this->assertSame('asc', $pageTwoQuery['direction']);
        $this->assertSame('2', (string) $pageTwoQuery['page']);

        $secondResponse = $this->actingAs($admin)
            ->get(route('admin.customers.index', [...$parameters, 'page' => 2], false))
            ->assertOk()
            ->assertSee('Pagination Customer 16')
            ->assertDontSee('Pagination Customer 01')
            ->assertSee('data-first="16"', false)
            ->assertSee('data-last="16"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('Page 2 of 2');

        /** @var LengthAwarePaginator $secondPage */
        $secondPage = $secondResponse->viewData('customers');
        $this->assertCount(1, $secondPage->items());
        $this->assertSame(2, $secondPage->currentPage());
    }

    public function test_therapists_use_the_fixed_page_size_and_reset_selection_when_paging(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createStaffAndServices(16);
        $selectedStaff = StaffProfile::query()->firstOrFail();

        $parameters = [
            'q' => 'Therapist',
            'status' => 'active',
            'bookable' => 'yes',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 1,
            'selected' => $selectedStaff->id,
            'tab' => 'services',
        ];

        $firstResponse = $this->actingAs($admin)
            ->get(route('admin.staff.index', $parameters, false))
            ->assertOk()
            ->assertSee('Therapist 01')
            ->assertSee('Therapist 15')
            ->assertDontSee('Therapist 16')
            ->assertSee('data-first="1"', false)
            ->assertSee('data-last="15"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('Page 1 of 2')
            ->assertDontSee('services_page=', false)
            ->assertDontSee('service-catalog', false);

        /** @var LengthAwarePaginator $staffPage */
        $staffPage = $firstResponse->viewData('staffProfiles');

        $this->assertSame('page', $staffPage->getPageName());
        $this->assertCount(15, $staffPage->items());
        $this->assertSame(16, $staffPage->total());
        $this->assertSame(15, $staffPage->perPage());
        $this->assertSame('Therapist 01', $staffPage->getCollection()->first()->user->name);
        $this->assertSame('Therapist 15', $staffPage->getCollection()->last()->user->name);

        parse_str((string) parse_url($staffPage->url(2), PHP_URL_QUERY), $pageTwoQuery);
        $this->assertSame('Therapist', $pageTwoQuery['q']);
        $this->assertSame('active', $pageTwoQuery['status']);
        $this->assertSame('yes', $pageTwoQuery['bookable']);
        $this->assertSame('name', $pageTwoQuery['sort']);
        $this->assertSame('asc', $pageTwoQuery['direction']);
        $this->assertSame('2', (string) $pageTwoQuery['page']);
        $this->assertArrayNotHasKey('selected', $pageTwoQuery);
        $this->assertArrayNotHasKey('tab', $pageTwoQuery);

        $secondResponse = $this->actingAs($admin)
            ->get($staffPage->url(2))
            ->assertOk()
            ->assertSee('Therapist 16')
            ->assertDontSee('Therapist 01')
            ->assertSee('data-first="16"', false)
            ->assertSee('data-last="16"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('Page 2 of 2');

        /** @var LengthAwarePaginator $secondStaffPage */
        $secondStaffPage = $secondResponse->viewData('staffProfiles');

        $this->assertSame(2, $secondStaffPage->currentPage());
        $this->assertCount(1, $secondStaffPage->items());
        $this->assertSame('Therapist 16', $secondStaffPage->getCollection()->first()->user->name);
        $this->assertSame('profile', $secondResponse->viewData('selectedTab'));
        $this->assertSame($secondStaffPage->getCollection()->first()->id, $secondResponse->viewData('selectedStaffProfile')->id);
    }

    public function test_services_use_their_own_fixed_pagination_and_keep_filter_and_sort_state(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createStaffAndServices(16);
        $parameters = [
            'q' => 'Service',
            'status' => 'active',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 1,
        ];

        $firstResponse = $this->actingAs($admin)
            ->get(route('admin.services.index', $parameters, false))
            ->assertOk()
            ->assertSee('Service 01')
            ->assertSee('Service 15')
            ->assertDontSee('Service 16')
            ->assertSee('data-first="1"', false)
            ->assertSee('data-last="15"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('Page 1 of 2');

        /** @var LengthAwarePaginator $servicePage */
        $servicePage = $firstResponse->viewData('services');
        $this->assertSame('page', $servicePage->getPageName());
        $this->assertCount(15, $servicePage->items());
        $this->assertSame(16, $servicePage->total());
        $this->assertSame(15, $servicePage->perPage());
        $this->assertSame('Service 01', $servicePage->getCollection()->first()->name);
        $this->assertSame('Service 15', $servicePage->getCollection()->last()->name);

        parse_str((string) parse_url($servicePage->url(2), PHP_URL_QUERY), $pageTwoQuery);
        $this->assertSame('Service', $pageTwoQuery['q']);
        $this->assertSame('active', $pageTwoQuery['status']);
        $this->assertSame('name', $pageTwoQuery['sort']);
        $this->assertSame('asc', $pageTwoQuery['direction']);
        $this->assertSame('2', (string) $pageTwoQuery['page']);

        $secondResponse = $this->actingAs($admin)
            ->get($servicePage->url(2))
            ->assertOk()
            ->assertSee('Service 16')
            ->assertDontSee('Service 01')
            ->assertSee('data-first="16"', false)
            ->assertSee('data-last="16"', false)
            ->assertSee('data-total="16"', false)
            ->assertSee('Page 2 of 2');

        /** @var LengthAwarePaginator $secondServicePage */
        $secondServicePage = $secondResponse->viewData('services');
        $this->assertSame(2, $secondServicePage->currentPage());
        $this->assertCount(1, $secondServicePage->items());
        $this->assertSame('Service 16', $secondServicePage->getCollection()->first()->name);
    }

    public function test_pager_ranges_empty_results_and_filter_disclosure_are_accessible(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createCustomers(1);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', absolute: false))
            ->assertOk()
            ->assertSee('data-active-filters="0"', false)
            ->assertSee('data-filter-toggle', false)
            ->assertSee('data-filter-panel', false)
            ->assertSee('x-data="{ filtersOpen: false }"', false)
            ->assertDontSee('Clear filters')
            ->assertSee('data-pagination-range', false)
            ->assertSee('data-first="1"', false)
            ->assertSee('data-last="1"', false)
            ->assertSee('data-total="1"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Page 1 of 1');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', [
                'q' => 'Pagination Customer',
                'status' => 'active',
            ], false))
            ->assertOk()
            ->assertSee('data-active-filters="2"', false)
            ->assertSee('x-data="{ filtersOpen: true }"', false)
            ->assertSee('Clear filters');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['q' => 'No matching customer'], false))
            ->assertOk()
            ->assertSee('No customers found')
            ->assertDontSee('data-pagination', false);
    }

    private function createCustomers(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $user = User::factory()->customer()->create([
                'name' => sprintf('Pagination Customer %02d', $index),
                'email' => sprintf('pagination-customer-%02d@example.com', $index),
                'is_active' => true,
            ]);

            CustomerProfile::factory()->for($user)->create([
                'customer_code' => sprintf('CP-PAGE-%02d', $index),
            ]);
        }
    }

    private function createStaffAndServices(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $user = User::factory()->staff()->create([
                'name' => sprintf('Therapist %02d', $index),
                'email' => sprintf('pagination-therapist-%02d@example.com', $index),
                'is_active' => true,
            ]);

            StaffProfile::factory()->for($user)->create([
                'position' => 'Spa Therapist',
                'is_bookable' => true,
            ]);

            Service::factory()->create([
                'name' => sprintf('Service %02d', $index),
                'slug' => sprintf('service-%02d', $index),
                'is_active' => true,
            ]);
        }
    }
}
