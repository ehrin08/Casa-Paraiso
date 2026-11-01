<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RfmSegmentRequest;
use App\Models\Addon;
use App\Models\MembershipTier;
use App\Models\RfmSegment;
use App\Services\RfmScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RfmSegmentController extends Controller
{
    public function create(): View
    {
        return $this->form(new RfmSegment(['is_active' => true]));
    }

    public function store(RfmSegmentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            RfmSegment::query()->create([
                ...$this->attributes($request),
                'priority' => (int) RfmSegment::query()->lockForUpdate()->max('priority') + 10,
            ]);
        });

        return to_route('admin.promotions.settings.edit')->with('status', 'rfm-segment-created');
    }

    public function edit(RfmSegment $segment): View
    {
        return $this->form($segment);
    }

    public function update(RfmSegmentRequest $request, RfmSegment $segment): RedirectResponse
    {
        $segment->update($this->attributes($request));

        return to_route('admin.promotions.settings.edit')->with('status', 'rfm-segment-updated');
    }

    public function move(Request $request, RfmSegment $segment): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($direction, $segment): void {
            // Renumber in steps of ten so equal or missing priorities still reorder predictably.
            $ordered = RfmSegment::query()->ordered()->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (RfmSegment $candidate) => $candidate->is($segment));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $ordered->has($target)) {
                return;
            }

            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach (array_values($items) as $position => $item) {
                $item->forceFill(['priority' => ($position + 1) * 10])->save();
            }
        });

        return to_route('admin.promotions.settings.edit')->with('status', 'rfm-segment-moved');
    }

    public function destroy(RfmSegment $segment): RedirectResponse
    {
        if ($segment->promotionSuggestions()->exists()) {
            throw ValidationException::withMessages([
                'segment' => __('This rule has issued rewards, so it is kept for history. Deactivate it instead.'),
            ]);
        }

        $segment->delete();

        return to_route('admin.promotions.settings.edit')->with('status', 'rfm-segment-deleted');
    }

    private function form(RfmSegment $segment): View
    {
        $scorer = app(RfmScorer::class);

        return view('admin.promotions.segment-form', [
            'segment' => $segment,
            'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(),
            'tiers' => MembershipTier::query()->ordered()->get(),
            'cutoffs' => $scorer->cutoffs($scorer->population()),
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(RfmSegmentRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        foreach (array_keys(RfmSegment::METRICS) as $metric) {
            $data["{$metric}_score_min"] = filled($data["{$metric}_score_min"] ?? null) ? (int) $data["{$metric}_score_min"] : null;
            $data["{$metric}_score_max"] = filled($data["{$metric}_score_max"] ?? null) ? (int) $data["{$metric}_score_max"] : null;
        }

        return $data;
    }
}
