<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RfmSegmentRequest;
use App\Models\Addon;
use App\Models\MembershipTier;
use App\Models\RfmSegment;
use App\Services\RfmRuleEngine;
use App\Services\RfmScorer;
use App\Services\RfmVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RfmSegmentController extends Controller
{
    public function create(Request $request, RfmVocabulary $vocabulary): View
    {
        $template = $vocabulary->templates()[(string) $request->query('template')] ?? [];

        return $this->form(new RfmSegment(['is_active' => true, ...$template]));
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
        $engine = app(RfmRuleEngine::class);
        $population = $scorer->population();
        $scores = $scorer->scoreAll($population)->values();
        $rulesAbove = $engine->rulesAbove($segment);

        // Preview the values on screen, including recovered input after a failed save.
        $candidate = $segment->replicate()->forceFill(['is_active' => (bool) old('is_active', $segment->is_active)]);
        foreach (array_keys(RfmSegment::METRICS) as $metric) {
            foreach (['min', 'max'] as $bound) {
                $value = old("{$metric}_score_{$bound}", $segment->{"{$metric}_score_{$bound}"});
                $candidate->{"{$metric}_score_{$bound}"} = filled($value) ? (int) $value : null;
            }
        }

        return view('admin.promotions.segment-form', [
            'segment' => $segment,
            'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(),
            'tiers' => MembershipTier::query()->ordered()->get(),
            'cutoffs' => $scorer->cutoffs($population),
            'templates' => $segment->exists ? [] : app(RfmVocabulary::class)->templates(),
            'preview' => $engine->preview($candidate, $scores, $rulesAbove),
            // Anonymous score triples and range lists only, so the live preview carries no customer details.
            'previewScores' => $scores->map(fn (array $customerScores) => [$customerScores['r'], $customerScores['f'], $customerScores['m']])->all(),
            'previewRulesAbove' => $rulesAbove->map(fn (RfmSegment $rule) => collect(array_keys(RfmSegment::METRICS))
                ->flatMap(fn (string $metric) => [$rule->{"{$metric}_score_min"}, $rule->{"{$metric}_score_max"}])->all())->all(),
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
