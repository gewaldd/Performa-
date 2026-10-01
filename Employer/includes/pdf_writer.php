<?php
/**
 * Minimal dependency-free PDF writer for employer reports.
 *
 * No Composer packages, no mbstring, no iconv: every string op below is
 * byte-safe by construction (strlen/word-wrap on transliterated ASCII).
 * Emits PDF 1.4 with Helvetica/Helvetica-Bold, A4 pages, automatic page
 * breaks and "Page X of Y" footers. Text outside Latin-1 degrades to '?'
 * (documented; employee names in this deployment are ASCII).
 *
 * Block model (one flow across as many pages as needed):
 *   ['h', 'Heading text']
 *   ['p', 'Wrapped paragraph text']
 *   ['small', 'Footnote-sized text']
 *   ['gap']                      (12pt spacer)
 *   ['table', ['head' => [...], 'rows' => [[...], ...]]]
 */

function pdf_safe_text(string $text): string
{
    static $map = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ç' => 'c', 'Ç' => 'C',
        'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
        // Punctuation that otherwise degrades to one '?' per UTF-8 byte
        // (the old "??" mojibake): middle dot, dashes, smart quotes,
        // ellipsis, bullets, multiplication sign.
        '·' => '-', '–' => '-', '—' => '-',
        "‘" => "'", "’" => "'", "“" => '"', "”" => '"',
        '…' => '...', '•' => '-', '×' => 'x',
    ];

    $text = strtr($text, $map);
    $out = '';
    $len = strlen($text);

    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        $ord = ord($ch);

        if ($ord === 10 || $ord === 13) {
            $out .= ' ';
            continue;
        }

        if ($ord < 32 || $ord > 126) {
            $out .= '?';
            continue;
        }

        if ($ch === '\\' || $ch === '(' || $ch === ')') {
            $out .= '\\';
        }

        $out .= $ch;
    }

    return $out;
}

// Greedy word wrap measured in characters (caller transliterates first,
// so strlen() is exact). Returns lines of at most $width chars.
function pdf_wrap(string $text, int $width): array
{
    $text = pdf_safe_text($text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    if (!$words) {
        return [''];
    }

    $lines = [];
    $line = '';

    foreach ($words as $word) {
        // Hard-split pathological tokens (hashes, URLs) so nothing overflows.
        while (strlen($word) > $width) {
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }

            $lines[] = substr($word, 0, $width);
            $word = substr($word, $width);
        }

        if ($line === '') {
            $line = $word;
        } elseif (strlen($line) + 1 + strlen($word) <= $width) {
            $line .= ' ' . $word;
        } else {
            $lines[] = $line;
            $line = $word;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

// Canonical JSON for fingerprinting: recursive key sort, stable flags.
function report_pdf_canonical($snapshot): string
{
    $sort = function (&$value) use (&$sort): void {
        if (!is_array($value)) {
            return;
        }

        $isList = array_is_list($value);

        foreach ($value as &$child) {
            $sort($child);
        }
        unset($child);

        if (!$isList) {
            ksort($value);
        }
    };

    $copy = $snapshot;
    $sort($copy);

    $json = json_encode(
        $copy,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return is_string($json) ? $json : 'null';
}

function report_pdf_fingerprint($snapshot): string
{
    return hash('sha256', report_pdf_canonical($snapshot));
}

// Content blocks for one report snapshot: KPI table (targets prefer the
// frozen snapshot, live template fills gaps), approved AI plan, and the
// regularization record. Shared by single + batch generation so both PDFs
// render identical sections.
function report_pdf_blocks(array $snapshot, array $template): array
{
    $blocks = [];
    $blocks[] = ['h', 'KPI Scores'];

    $scores = (array) ($snapshot['scores'] ?? []);
    $frozen = (array) ($snapshot['targets'] ?? []);
    $rows = [];
    $ratedSum = 0.0;
    $ratedCount = 0;

    foreach ((array) ($template['kpis'] ?? []) as $kpi) {
        if (!is_array($kpi) || !isset($kpi['key'])) {
            continue;
        }

        $val = isset($scores[$kpi['key']]) ? (float) $scores[$kpi['key']] : null;
        $tgt = isset($frozen[$kpi['key']])
            ? (float) $frozen[$kpi['key']]
            : (float) ($kpi['target'] ?? 0);

        $status = '-';
        if ($val !== null && function_exists('kpi_status_for_score')) {
            $status = kpi_status_for_score($val, $tgt)['status'];
        }

        $rows[] = [
            (string) ($kpi['name'] ?? $kpi['key']),
            $val !== null ? number_format($val, 1) : '-',
            number_format($tgt, 1),
            $status,
        ];

        if ($val !== null && $val > 0) {
            $ratedSum += $val;
            $ratedCount++;
        }
    }

    if ($rows === []) {
        $blocks[] = ['p', 'No KPI ratings were on file when this report was generated.'];
    } else {
        $blocks[] = ['table', ['head' => ['KPI', 'Score', 'Target', 'Status'], 'rows' => $rows]];

        if ($ratedCount > 0) {
            $blocks[] = [
                'p',
                'Average ' . number_format($ratedSum / $ratedCount, 1) .
                ' across ' . $ratedCount . ' rated KPI' .
                ($ratedCount === 1 ? '' : 's') . '.',
            ];
        } else {
            $blocks[] = ['p', 'No rated KPIs in this report.'];
        }
    }

    $ai = $snapshot['aiRecommendations'] ?? null;

    if (is_array($ai) && !empty($ai['training_recommendations'])) {
        $blocks[] = ['h', 'AI Training Recommendations (approved)'];

        if (!empty($ai['summary'])) {
            $blocks[] = ['p', (string) $ai['summary']];
        }

        foreach ($ai['training_recommendations'] as $rec) {
            if (!is_array($rec)) {
                continue;
            }

            $area = ucwords(str_replace('_', ' ', (string) ($rec['competency_area'] ?? '')));
            $line = trim($area . ' - ' . (string) ($rec['training_type'] ?? ''));

            if (!empty($rec['timeline'])) {
                $line .= ' (' . (string) $rec['timeline'] . ')';
            }

            if (!empty($rec['description'])) {
                $line .= ': ' . (string) $rec['description'];
            }

            $blocks[] = ['p', $line];
        }
    }

    if (!empty($snapshot['regularizationRecommendation'])) {
        $blocks[] = ['h', 'Regularization Decision Record'];
        $blocks[] = [
            'p',
            'Recommendation: ' . (string) $snapshot['regularizationRecommendation'],
        ];

        if (!empty($snapshot['regularizationNotes'])) {
            $blocks[] = ['p', (string) $snapshot['regularizationNotes']];
        }

        $decided = '';
        if (!empty($snapshot['regularizationDecidedAt'])) {
            $ts = strtotime((string) $snapshot['regularizationDecidedAt']);
            $decided = $ts !== false ? date('M j, Y g:ia', $ts) : '';
        }
        if (!empty($snapshot['regularizationDecidedBy'])) {
            $decided .= ($decided !== '' ? ' by ' : '') . (string) $snapshot['regularizationDecidedBy'];
        }
        if ($decided !== '') {
            $blocks[] = ['p', 'Decided ' . $decided . '.'];
        }
    }

    return $blocks;
}

// One laid-out document: eyebrow + title page header + flowing blocks.
// Returns raw PDF bytes. $eyebrow is optional (tests + legacy callers).
function report_pdf_build(string $title, array $metaLines, array $blocks, string $footerNote, string $eyebrow = ''): string
{
    return report_pdf_build_multi([
        [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'meta' => $metaLines,
            'blocks' => $blocks,
            'footer' => $footerNote,
        ],
    ]);
}

// Multi-document build (batch export): cover index + one section per
// document, each starting on a fresh page.
function report_pdf_build_multi(array $docs): string
{
    $pageW = 595;
    $pageH = 842;
    $marginX = 48;
    $topY = 782;
    $bottomY = 70;
    $usableW = $pageW - 2 * $marginX;

    // Layout pass: flow ops into pages (each op: [kind, font, size, x, y, text]
    // or ['rule', x1, y1, x2, y2]).
    $pages = [];
    $current = [];
    $y = $topY;

    $need = function (int $h) use (&$pages, &$current, &$y, $topY, $bottomY): void {
        if ($y - $h < $bottomY) {
            $pages[] = $current;
            $current = [];
            $y = $topY;
        }
    };

    $emitLine = function (string $font, float $size, float $x, string $text) use (&$current, &$y): void {
        // Caller guarantees pdf_safe_text() input (pdf_wrap safes
        // internally; literals below are ASCII). Never escape here or
        // backslashes double up in the stream.
        $current[] = ['line', $font, $size, $x, $y, $text];
    };

    foreach ($docs as $docIndex => $doc) {
        if ($docIndex > 0 || count($docs) > 1) {
            // Fresh page per document (batch callers prepend their own cover doc).
            if ($current !== [] || $docIndex > 0) {
                $pages[] = $current;
                $current = [];
                $y = $topY;
            }
        }

        $need(72);
        $eyebrow = trim((string) ($doc['eyebrow'] ?? ''));
        if ($eyebrow !== '') {
            foreach (pdf_wrap($eyebrow, 60) as $line) {
                $need(14);
                $emitLine('F2', 10.0, $marginX, strtoupper($line));
                $y -= 14;
            }
            $y -= 2;
        }
        foreach (pdf_wrap((string) ($doc['title'] ?? 'Report'), 46) as $line) {
            $need(24);
            $emitLine('F2', 20.0, $marginX, $line);
            $y -= 24;
        }
        $y -= 2;

        foreach ((array) ($doc['meta'] ?? []) as $meta) {
            foreach (pdf_wrap((string) $meta, 95) as $line) {
                $need(13);
                $emitLine('F1', 10.0, $marginX, $line);
                $y -= 13;
            }
        }

        $y -= 8;
        $current[] = ['rule', $marginX, $y, $marginX + $usableW, $y];
        $y -= 14;

        foreach ((array) ($doc['blocks'] ?? []) as $block) {
            $kind = $block[0] ?? '';

            if ($kind === 'gap') {
                $y -= 12;
                continue;
            }

            if ($kind === 'h') {
                $need(20);
                foreach (pdf_wrap((string) ($block[1] ?? ''), 80) as $line) {
                    $need(16);
                    $emitLine('F2', 13.0, $marginX, $line);
                    $y -= 16;
                }
                $y -= 4;
                continue;
            }

            if ($kind === 'p' || $kind === 'small') {
                $size = $kind === 'small' ? 9.0 : 10.5;
                $leading = $kind === 'small' ? 12 : 14;
                $width = $kind === 'small' ? 105 : 92;
                foreach (pdf_wrap((string) ($block[1] ?? ''), $width) as $line) {
                    $need((int) $leading);
                    $emitLine('F1', $size, $marginX, $line);
                    $y -= $leading;
                }
                $y -= 3;
                continue;
            }

            if ($kind === 'table') {
                $spec = $block[1] ?? [];
                $head = array_values((array) ($spec['head'] ?? []));
                $rows = array_values((array) ($spec['rows'] ?? []));
                $cols = count($head);

                if ($cols < 1) {
                    continue;
                }

                // Column widths from longest cell (header included), 5pt/char.
                $maxLens = [];
                for ($c = 0; $c < $cols; $c++) {
                    $maxLens[$c] = strlen((string) ($head[$c] ?? ''));
                }
                foreach ($rows as $row) {
                    for ($c = 0; $c < $cols; $c++) {
                        $maxLens[$c] = max($maxLens[$c], strlen((string) ($row[$c] ?? '')));
                    }
                }
                $widths = [];
                $total = 0;
                foreach ($maxLens as $len) {
                    $w = max(46, min(220, (int) ($len * 5.0) + 12));
                    $widths[] = $w;
                    $total += $w;
                }
                if ($total > $usableW) {
                    $scale = $usableW / $total;
                    foreach ($widths as $i => $w) {
                        $widths[$i] = max(34, (int) floor($w * $scale));
                    }
                }

                $need(18);
                $x = $marginX;
                foreach ($head as $c => $cell) {
                    foreach (pdf_wrap((string) $cell, max(6, (int) (($widths[$c] - 8) / 5))) as $k => $line) {
                        if ($k > 0) {
                            $need(13);
                            $y -= 13;
                        }
                        $emitLine('F2', 10.0, $x, $line);
                    }
                    $x += $widths[$c];
                }
                $y -= 15;
                $current[] = ['rule', $marginX, $y + 4, $marginX + $usableW, $y + 4];

                foreach ($rows as $row) {
                    $need(15);
                    $x = $marginX;
                    $rowLines = [];
                    $tallest = 1;
                    for ($c = 0; $c < $cols; $c++) {
                        $cellLines = pdf_wrap(
                            (string) ($row[$c] ?? ''),
                            max(6, (int) (($widths[$c] - 8) / 5))
                        );
                        $rowLines[$c] = $cellLines;
                        $tallest = max($tallest, count($cellLines));
                    }
                    for ($r = 0; $r < $tallest; $r++) {
                        if ($r > 0) {
                            $need(13);
                            $y -= 13;
                        }
                        $x = $marginX;
                        for ($c = 0; $c < $cols; $c++) {
                            if (isset($rowLines[$c][$r])) {
                                $emitLine('F1', 10.0, $x, $rowLines[$c][$r]);
                            }
                            $x += $widths[$c];
                        }
                    }
                    $y -= 15;
                }
                $y -= 4;
                continue;
            }
        }

        $footer = trim((string) ($doc['footer'] ?? ''));
        if ($footer !== '') {
            $y -= 6;
            foreach (pdf_wrap($footer, 105) as $line) {
                $need(12);
                $emitLine('F1', 9.0, $marginX, $line);
                $y -= 12;
            }
        }
    }

    if ($current !== []) {
        $pages[] = $current;
    }
    if ($pages === []) {
        $pages[] = [];
    }

    // Emit pass (footers need the final page count).
    $pageCount = count($pages);
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

    $kids = [];
    $nextId = 5;
    $pageObjIds = [];
    $contentIds = [];
    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjIds[$i] = $nextId++;
        $contentIds[$i] = $nextId++;
        $kids[] = $pageObjIds[$i] . ' 0 R';
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    foreach ($pages as $i => $ops) {
        $stream = '';
        foreach ($ops as $op) {
            if ($op[0] === 'rule') {
                $stream .= "0.85 G\n{$op[1]} {$op[2]} m {$op[3]} {$op[4]} l S\n0 G\n";
                continue;
            }
            [, $font, $size, $x, $oy, $text] = $op;
            $sizeStr = rtrim(rtrim(number_format($size, 1), '0'), '.');
            $xStr = rtrim(rtrim(number_format($x, 1), '0'), '.');
            $yStr = rtrim(rtrim(number_format($oy, 1), '0'), '.');
            $stream .= "BT /{$font} {$sizeStr} Tf {$xStr} {$yStr} Td ({$text}) Tj ET\n";
        }
        $foot = 'Page ' . ($i + 1) . ' of ' . $pageCount;
        $stream .= "BT /F1 9 Tf {$marginX} 40 Td (" . pdf_safe_text($foot) . ") Tj ET\n";
        $objects[$contentIds[$i]] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $objects[$pageObjIds[$i]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
            . $pageW . ' ' . $pageH . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>'
            . ' /Contents ' . $contentIds[$i] . ' 0 R >>';
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xrefAt = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($id = 1; $id <= $maxId; $id++) {
        if (!isset($offsets[$id])) {
            $pdf .= "0000000000 00000 f \n";
            continue;
        }
        $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefAt . "\n%%EOF";

    return $pdf;
}
