const METRICS = ['r', 'f', 'm'];

// Ranges are [rMin, rMax, fMin, fMax, mMin, mMax]; null means "Any", like RfmSegment::matchesScores.
export function matchesRanges(scores, ranges) {
    return METRICS.every((_, index) => {
        const min = ranges[index * 2];
        const max = ranges[index * 2 + 1];

        return (min === null || scores[index] >= min) && (max === null || scores[index] <= max);
    });
}

// Mirrors RfmRuleEngine::preview: customers already matched by a rule above stay there.
export function countPreview(scores, rulesAbove, ranges, active) {
    const fitting = scores.filter((customerScores) => matchesRanges(customerScores, ranges));

    return {
        fits: fitting.length,
        lands: active ? fitting.filter((customerScores) => !rulesAbove.some((rule) => matchesRanges(customerScores, rule))).length : 0,
    };
}

export function readRanges(form) {
    return METRICS.flatMap((metric) => ['min', 'max'].map((bound) => {
        const value = form.querySelector(`[name="${metric}_score_${bound}"]`)?.value ?? '';

        return value === '' ? null : Number(value);
    }));
}

export function createRfmSegmentPreview(scores, rulesAbove, initial) {
    return {
        fits: initial.fits,
        lands: initial.lands,
        active: true,

        init() {
            this.active = this.$el.querySelector('input[type="checkbox"][name="is_active"]')?.checked ?? true;
            this.$el.addEventListener('change', () => this.update());
        },

        update() {
            this.active = this.$el.querySelector('input[type="checkbox"][name="is_active"]')?.checked ?? true;
            Object.assign(this, countPreview(scores, rulesAbove, readRanges(this.$el), this.active));
        },
    };
}
