import test from 'node:test';
import assert from 'node:assert/strict';
import { matchesRanges, countPreview, readRanges } from '../../resources/js/rfm-segment-preview.js';

const any = [null, null, null, null, null, null];

test('ranges match like RfmSegment::matchesScores, with null meaning any', () => {
    assert.equal(matchesRanges([5, 1, 3], any), true);
    assert.equal(matchesRanges([5, 1, 3], [4, null, null, 1, null, null]), true);
    assert.equal(matchesRanges([3, 1, 3], [4, null, null, null, null, null]), false);
    assert.equal(matchesRanges([5, 2, 3], [null, null, null, 1, null, null]), false);
});

test('preview leaves customers with the first group above that takes them', () => {
    const scores = [[5, 5, 5], [5, 1, 2], [1, 3, 3]];
    const recent = [4, null, null, null, null, null];
    const champions = [null, null, 5, null, null, null];

    assert.deepEqual(countPreview(scores, [], recent, true), { fits: 2, lands: 2 });
    assert.deepEqual(countPreview(scores, [champions], recent, true), { fits: 2, lands: 1 });
    assert.deepEqual(countPreview(scores, [recent], any, true), { fits: 3, lands: 1 });
    assert.deepEqual(countPreview(scores, [], recent, false), { fits: 2, lands: 0 });
});

test('form selects become range lists', () => {
    const values = { r_score_min: '4', r_score_max: '', f_score_min: '', f_score_max: '1', m_score_min: '', m_score_max: '5' };
    const form = { querySelector: (selector) => ({ value: values[selector.match(/name="(\w+)"/)[1]] }) };

    assert.deepEqual(readRanges(form), [4, null, null, 1, null, 5]);
});
