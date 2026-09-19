<?php
// Display formatting helpers (Item 4): one voice for dates, scores, and
// day-counts across Employer pages. All inputs are coerced to safe output
// (numbers formatted, strings escaped, unparseable dates fall back) so
// call sites stay terse. No echo — return only.
function pf_date(?string $iso, string $fallback = 'Unknown'): string
{
    if ($iso === null || $iso === '') {
        return $fallback;
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $fallback;
    }
    return date('M j, Y', $ts);
}

function pf_score($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '—';
    }
    return number_format((float) $value, 1);
}

function pf_score_pair($score, $max = 5.0): string
{
    if ($score === null || $score === '' || !is_numeric($score)) {
        return '—';
    }
    return number_format((float) $score, 1) . ' / ' . number_format((float) $max, 1);
}

function pf_day(int $n): string
{
    return 'Day ' . max(0, $n);
}

// Department display normalization (Stage 1): free-text entry produced
// "food" / "Sales" / "const" side by side. Normalize only at render — raw
// Firestore values are never rewritten (Stage 2 canonical list is the real
// fix; abbreviations still abbreviate until then).
function pf_dept_label(?string $raw): string
{
    $clean = trim((string) $raw);
    if ($clean === '') {
        return '';
    }
    return ucwords(strtolower($clean));
}
