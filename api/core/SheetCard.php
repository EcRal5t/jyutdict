<?php
/** Read-only first paint of the existing ResultCard, without SEO-only headings or fields. */
function seoSheetCard(array $row, array $columns) {
    $glyph = preg_replace('~[?/!！？見歸 ]~u', '', $row['繁'] ?? '') ?: '□';
    $pron = str_replace(['!', '！'], '', $row['綜'] ?? '');
    $simple = str_replace(['!', '！'], '', $row['檢'] ?? '');
    $unicode = mb_strlen($glyph) === 1 && mb_ord($glyph) <= 0xFFFF ? 'U+' . strtoupper(str_pad(dechex(mb_ord($glyph)), 4, '0', STR_PAD_LEFT)) : '';
    if (!empty($row['IDS'])) $unicode .= "\n[" . $row['IDS'] . ']';
    $glyphStyle = preg_match('/[？?]/u', $row['繁'] ?? '') ? 'color:#9CA3AF' : (preg_match('/[見歸]/u', $row['繁'] ?? '') ? 'color:#4B5563' : '');
    $html = '<div class="group relative bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-none shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:shadow-[4px_4px_0_rgba(0,0,0,0.3)] transition-all duration-300"><div class="flex flex-col md:flex-row">';
    $html .= '<div class="md:w-32 bg-gray-50 dark:bg-slate-900/50 flex flex-row md:flex-col items-center md:justify-center px-6 py-2 gap-4 md:gap-2 border-b md:border-b-0 md:border-r border-gray-100 dark:border-slate-700">';
    $html .= '<div class="text-4xl md:text-5xl font-bold leading-tight inline-block" style="' . $glyphStyle . '">' . seoEscape($glyph) . '</div><div class="flex flex-col md:items-center text-left md:text-center w-full md:w-auto">';
    $html .= '<div class="text-xs text-slate-400 font-mono mb-1">' . seoEscape(trim($unicode)) . '</div><div class="inline-block text-lg text-slate-700 dark:text-slate-300 font-medium whitespace-pre-wrap leading-tight' . (strpos($pron, '?') !== false ? ' italic' : '') . '">' . seoEscape(str_replace('/', "/\n", $pron)) . '</div>';
    if ($simple !== $pron) $html .= '<div class="text-xs text-slate-500 mt-1">' . seoEscape($simple) . '</div>';
    if (!empty($row['俗/常'])) $html .= '<div class="text-xs text-slate-400 mt-0.5">(' . seoEscape($row['俗/常']) . ')</div>';
    $html .= '</div></div><div class="flex-1 px-5 py-3 min-w-0"><div class="mb-5 text-base leading-relaxed text-slate-700 dark:text-slate-300 relative block">';
    $bookGlyph = $row['錔'] ?? ''; $bookPron = $row['音'] ?? ''; $bookMeaning = $row['義'] ?? '';
    $book = $bookGlyph;
    if ($bookGlyph && ($bookPron || $bookMeaning)) $book .= ': ';
    $book .= $bookPron;
    if ($bookPron && $bookMeaning) $book .= ' | ';
    if ($bookMeaning) $book .= '「' . $bookMeaning . '」';
    if ($book) $html .= '<div class="mb-2 italic text-slate-500 border-l-2 border-slate-200 pl-2 text-sm">—— ' . seoEscape($book) . '</div>';
    $meaning = $row['釋義'] ?? '';
    if ($meaning) {
        // Same sense boundaries and grammar labels as formatMeanings in formatters.js.
        $meaning = preg_replace('/(?<=[^}"“])</u', '；<', $meaning);
        if (strpos($meaning, '[粵]') === 0 && strpos($meaning, '{1}') !== false) $meaning = preg_replace('/^\[粵\]/u', '[粵]；', $meaning);
        $meaning = str_replace('}', '} ', $meaning);
        $senses = array_values(array_filter(preg_split('/[；。？！] *?(?=<)|(?=\{)/u', $meaning), function ($s) { return trim($s) !== ''; }));
        $grammar = preg_split('/[;；] ?/u', $row['語法'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $order = 0;
        foreach ($senses as $i => $sense) {
            if (isset($grammar[$order]) && strpos($sense, '}') !== false) $sense = preg_replace('/(?<=\})/u', '‹' . str_replace('？', '?', $grammar[$order++]) . '›', $sense, 1);
            $class = strpos($sense, '[粵]') !== false && count($senses) > 1 ? 'font-bold text-slate-900 dark:text-slate-100' : '';
            $html .= '<div' . ($i < count($senses) - 1 ? ' class="mb-1"' : '') . '><span class="' . $class . '">' . seoEscape($sense) . '</span></div>';
        }
    } elseif (!$book) $html .= '<div class="text-slate-400 italic">(暫無釋義)</div>';
    $html .= '</div>';
    $groups = [1 => '', 2 => ''];
    foreach ($columns as $column) {
        $value = trim((string)($row[$column['col']] ?? ''));
        if ($value === '') continue;
        $kind = (int)$column['kind'];
        $name = $column['fullname'] . ($kind === 1 ? ($column['fullname_note'] ?? '') : '');
        $style = preg_match('/\A#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\z/', $column['color'] ?? '') ? ' style="color:' . $column['color'] . '"' : '';
        $valueClass = 'text-slate-800 dark:text-slate-300' . (strpos($value, '?') !== false ? ' italic' : '') . ($value === '_' ? ' text-slate-400' : '');
        if ($kind === 1) {
            $parts = explode('^', $value);
            $display = seoEscape(array_shift($parts));
            foreach ($parts as $part) $display .= '<del class="opacity-50">' . seoEscape($part) . '</del>';
        } else $display = seoEscape(str_replace("\n", ', ', $value));
        $groups[$kind] .= '<div class="relative"><span class="font-bold"' . $style . '>' . seoEscape($name) . ':</span> <span class="' . $valueClass . '">' . $display . '</span></div>';
    }
    $html .= '<div class="flex flex-wrap gap-3 mb-3 text-sm">' . $groups[1] . '</div>';
    if ($groups[2]) $html .= '<div class="pt-3 mt-2 border-t border-dashed border-gray-200 dark:border-slate-700 flex flex-wrap gap-4 text-sm">' . $groups[2] . '</div>';
    $classification = $row['大類'] ?? '';
    if ($classification) {
        foreach (['中類', '小類'] as $column) if (!empty($row[$column])) $classification .= ' > ' . $row[$column];
        $html .= '<div class="mt-4 flex justify-end"><span class="text-xs uppercase tracking-wider text-slate-300 dark:text-slate-600 font-bold bg-slate-50 dark:bg-slate-900 px-2 py-1 rounded-none border border-slate-200 dark:border-slate-700">' . seoEscape($classification) . '</span></div>';
    }
    return $html . '</div></div></div>';
}
