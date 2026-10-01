<?php
// Tests for the Review plans suggestion selection (the keep[] checklist):
// Employer/review_recommendations.php approve branch.
//
// The assertions run against the SHIPPED BYTES: the selection block is sliced
// out of the page and eval'd here, so editing the page can never leave this
// test asserting yesterday's logic. Covered: which suggestions survive, the
// ascending rebuild, the all-unticked refusal, and that reject (and every
// no-checklist caller) stays byte-identical to the old behavior.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('review plan keep[] selection');

$page = file_get_contents(__DIR__ . '/../Employer/review_recommendations.php');

$blockStart = strpos($page, '$approveKeep = null;');
$blockEnd = strpos($page, '// Update document with reviewed status');

assert_true(
    $blockStart !== false && $blockEnd !== false && $blockEnd > $blockStart,
    'the selection block is present in the page'
);

$block = substr($page, $blockStart, $blockEnd - $blockStart);

$select = eval(
    'return static function (array $aiData, string $action, $postedKeep): array {
        $_POST = $postedKeep === null ? [] : ["keep" => $postedKeep];
        ' . $block . '
        return [$aiData, $approveDropped, $approveKeep !== null];
    };'
);

assert_true(is_callable($select), 'the shipped block evaluates to a callable');

$plan = static function (array $areas): array {
    $recs = [];

    foreach ($areas as $area) {
        $recs[] = ['competency_area' => $area, 'description' => 'Do ' . $area];
    }

    return $recs;
};

$areasOf = static function (array $result): array {
    $areas = [];

    foreach ($result[0]['training_recommendations'] ?? [] as $rec) {
        $areas[] = $rec['competency_area'];
    }

    return $areas;
};

pf_case('every box ticked (or no checklist at all) changes nothing');

$all = ['a', 'b', 'c'];
$result = $select(['training_recommendations' => $plan($all)], 'approve', ['0', '1', '2']);
assert_same($all, $areasOf($result), 'all ticked keeps every suggestion');
assert_same(0, $result[1], 'dropped counter stays 0');
assert_true($result[2], 'an all-ticked post rewrites the same suggestions (idempotent)');

$result = $select(['training_recommendations' => $plan($all)], 'approve', null);
assert_same($all, $areasOf($result), 'no-JS / legacy POST keeps every suggestion');
assert_false($result[2], 'the legacy path never rewrites the stored plan');

$result = $select(['training_recommendations' => $plan($all)], 'approve', '0');
assert_same($all, $areasOf($result), 'a scalar keep is ignored (is_array guard)');
assert_false($result[2], 'a scalar keep is not treated as a selection');

pf_case('a crafted empty keep[] is refused, not read as "keep all"');

foreach ([[] , ['']] as $crafted) {
    $refused = false;

    try {
        $select(['training_recommendations' => $plan($all)], 'approve', $crafted);
    } catch (RuntimeException $e) {
        $refused = $e->getMessage() === 'Select at least one training suggestion to approve.';
    }

    assert_true($refused, 'keep[]=' . json_encode($crafted) . ' raises the guard message');
}


pf_case('unticking drops exactly that suggestion');

$result = $select(['training_recommendations' => $plan($all)], 'approve', ['0', '2']);
assert_same(['a', 'c'], $areasOf($result), 'the middle suggestion is gone');
assert_same(1, $result[1], 'one dropped');
assert_true($result[2], 'the plan was rewritten');

$result = $select(['training_recommendations' => $plan($all)], 'approve', ['2']);
assert_same(['c'], $areasOf($result), 'only the last box ticked');
assert_same(2, $result[1], 'two dropped');

pf_case('selection is rebuilt ascending, deduped, and range-checked');

$result = $select(['training_recommendations' => $plan($all)], 'approve', ['2', '0']);
assert_same(['a', 'c'], $areasOf($result), 'shuffled ticks keep document order');

$result = $select(['training_recommendations' => $plan($all)], 'approve', ['1', '1', '2']);
assert_same(['b', 'c'], $areasOf($result), 'repeated ticks count once');

$result = $select(['training_recommendations' => $plan($all)], 'approve', ['9', 'x', '1', '-1']);
assert_same(['b'], $areasOf($result), 'out-of-range, non-numeric and negative values are ignored');

$result = $select(['training_recommendations' => $plan($all)], 'approve', [1, 2]);
assert_same(['b', 'c'], $areasOf($result), 'numeric strings and ints behave alike');

pf_case('all boxes unticked is refused, not silently approved');

$refused = false;

try {
    $select(['training_recommendations' => $plan($all)], 'approve', ['5', 'x']);
} catch (RuntimeException $e) {
    $refused = $e->getMessage() === 'Select at least one training suggestion to approve.';
}

assert_true($refused, 'an empty kept set raises the guard message');

pf_case('reject and empty plans are untouched');

$result = $select(['training_recommendations' => $plan($all)], 'reject', ['0']);
assert_same($all, $areasOf($result), 'reject ignores the checklist');
assert_same(0, $result[1], 'reject drops nothing');

$result = $select(['training_recommendations' => []], 'approve', []);
assert_same([], $areasOf($result), 'a plan with no suggestions is not an error');

$result = $select(['training_recommendations' => []], 'approve', ['0', '1']);
assert_same([], $areasOf($result), 'ticks against an empty plan are harmless');

$legacy = $select(['summary' => 'legacy doc'], 'approve', ['0']);
assert_false($legacy[2], 'a legacy plan without the key is left alone');

pf_case('ordering + markup wiring');

// Counting runs on a comment-stripped copy: the inline JS comment mentions
// form="approve-..." in prose and must not be mistaken for markup.
$stripComments = static function (string $source): string {
    $source = preg_replace('/\/\*.*?\*\//s', ' ', $source);
    $source = preg_replace('/<!--.*?-->/s', ' ', $source);

    return preg_replace('/(?m)^[ \t]*\/\/.*$/', ' ', $source);
};

$wiring = $stripComments($page);

$guardAt = strpos($block, 'throw new RuntimeException');
$patchAt = strpos($page, "firestore_patch_document('Ratings', \$ratingDocId", $blockStart);

assert_true(
    $guardAt !== false && $patchAt !== false && $patchAt > $blockStart && $guardAt < ($patchAt - $blockStart),
    'the refusal throws before the Ratings patch is reached'
);
assert_same(
    substr_count($wiring, 'form="approve-'),
    substr_count($wiring, 'id="approve-'),
    'every checkbox points at an approve form that exists'
);
assert_same(
    substr_count($wiring, 'data-edit-target="edit-'),
    substr_count($wiring, 'id="edit-'),
    'every "click text to edit" hook has a matching edit form id'
);
assert_contains('name="reviewPlan"', $page, 'native single-open accordion attribute is emitted');
assert_contains('name="keep[]"', $page, 'the checklist posts keep[] to the approve form');
assert_not_contains('class="review-trigger', $page, 'the old severity strip markup is gone');

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
