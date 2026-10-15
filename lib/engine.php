<?php
// FITUR 3 - Mesin VSM: indexing, pembobotan TF-IDF, cosine similarity

function csv_detect_delimiter(string $line): string {
    $best = ','; $max = 0;
    foreach ([',', ';', "\t", '|'] as $d) { $c = substr_count($line, $d); if ($c > $max) { $max = $c; $best = $d; } }
    return $best;
}

function build_index(string $path, string $origName, array $opt): array {
    $max_docs = 5000;
    log_event('INDEX', "Mulai indeksasi file '$origName'", ['opsi' => $opt]);
    $fh = fopen($path, 'r');
    $first = fgets($fh);
    $delim = csv_detect_delimiter($first);
    rewind($fh);
    log_event('CSV', 'Delimiter terdeteksi', ['delimiter' => $delim === "\t" ? 'TAB' : $delim]);

    $header = fgetcsv($fh, 0, $delim, '"', '');
    $header = array_map(function ($h) { return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h)); }, $header);
    $idCol = null; $titleCol = null;
    foreach ($header as $i => $h) {
        if ($idCol === null && preg_match('/^(id|no|nomor|doc_?id|id_?dokumen)$/i', $h)) $idCol = $i;
        if ($titleCol === null && preg_match('/(judul|title|nama|headline|subject)/i', $h)) $titleCol = $i;
    }
    if ($titleCol === null) $titleCol = ($idCol === 0 && count($header) > 1) ? 1 : 0;
    $contentCols = [];
    foreach ($header as $i => $h) { if ($i !== $idCol && $i !== $titleCol) $contentCols[] = $i; }
    if (!$contentCols) $contentCols = [$titleCol];
    log_event('CSV', 'Mapping kolom otomatis', [
        'header' => $header, 'kolom_id' => $idCol === null ? '(nomor urut)' : $header[$idCol],
        'kolom_judul' => $header[$titleCol], 'kolom_isi' => array_map(function ($i) use ($header) { return $header[$i]; }, $contentCols),
    ]);

    $docs = []; $tfs = []; $n = 0; $skipped = 0; $tokTotal = 0;
    while (($row = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        if ($row === [null] || !array_filter($row, function ($c) { return trim((string)$c) !== ''; })) { $skipped++; continue; }
        if ($n >= $max_docs) { log_event('CSV', "Batas $max_docs dokumen tercapai, sisa baris diabaikan"); break; }
        $title = trim((string)($row[$titleCol] ?? ''));
        $content = '';
        foreach ($contentCols as $c) $content .= ' ' . ($row[$c] ?? '');
        $content = trim($content);
        $docId = ($idCol !== null && isset($row[$idCol]) && $row[$idCol] !== '') ? $row[$idCol] : (string)($n + 1);

        $pp = preprocess($title . ' ' . $content, $opt, $n < 3);   // 3 dokumen pertama dilog detail
        if ($n < 3) log_event('PREPROCESS', "Pipeline dokumen #$docId (contoh detail)", $pp['trace']);
        $tf = array_count_values($pp['tokens']);
        $tokTotal += count($pp['tokens']);
        $docs[$n] = ['id' => $docId, 'title' => $title !== '' ? $title : "Dokumen $docId",
                     'snippet' => mb_substr($content !== '' ? $content : $title, 0, 400), 'len' => count($pp['tokens']), 'norm' => 0];
        $tfs[$n] = $tf;
        $n++;
    }
    fclose($fh);
    log_event('PREPROCESS', "Pra-proses selesai untuk $n dokumen", ['baris_kosong_dilewati' => $skipped, 'total_token' => $tokTotal]);
    if ($n === 0) throw new Exception('Tidak ada dokumen valid di CSV.');

    // Document Frequency
    $df = [];
    foreach ($tfs as $tf) foreach ($tf as $term => $_) $df[$term] = ($df[$term] ?? 0) + 1;
    log_event('DF', 'Hitung document frequency (df) tiap term', ['ukuran_vocabulary' => count($df)]);

    // IDF = log10(N / df)
    $idf = [];
    foreach ($df as $term => $d) $idf[$term] = round(log10($n / $d), 6);
    arsort($idf);
    log_event('IDF', 'Hitung IDF = log10(N/df)', ['N' => $n, 'contoh_idf_tertinggi' => array_slice($idf, 0, 5, true)]);

    // TF-IDF: w = (1 + log10(tf)) * idf ; norm = sqrt(sum w^2) ; inverted index
    $post = [];
    foreach ($tfs as $d => $tf) {
        $sum = 0.0;
        foreach ($tf as $term => $f) {
            $w = (1 + log10($f)) * $idf[$term];
            if ($w <= 0) continue;
            $post[$term][$d] = round($w, 5);
            $sum += $w * $w;
        }
        $docs[$d]['norm'] = round(sqrt($sum), 6);
    }
    log_event('TFIDF', 'Hitung bobot TF-IDF w=(1+log10 tf)*idf, norma vektor dokumen, dan bangun inverted index', ['term_terindeks' => count($post)]);

    $meta = ['file' => $origName, 'built' => date('Y-m-d H:i:s'), 'N' => $n, 'vocab' => count($df),
             'opt' => $opt, 'columns' => $header, 'title_col' => $header[$titleCol],
             'content_cols' => array_map(function ($i) use ($header) { return $header[$i]; }, $contentCols)];
    file_put_contents(INDEX_FILE, json_encode(['meta' => $meta, 'docs' => $docs, 'idf' => $idf, 'post' => $post], JSON_UNESCAPED_UNICODE));
    log_event('INDEX', 'Indeks disimpan ke data/index.json', ['ukuran_kb' => round(filesize(INDEX_FILE) / 1024, 1)]);
    return $meta;
}

function load_index(): ?array {
    if (!is_file(INDEX_FILE)) return null;
    return json_decode(file_get_contents(INDEX_FILE), true);
}

// Cosine similarity antara vektor kueri dan semua dokumen kandidat
function search_query(string $q, array $idx, int $topk = 100): array {
    log_event('QUERY', 'Kueri diterima', ['kueri' => $q]);
    $opt = $idx['meta']['opt'];
    $pp = preprocess($q, $opt, true);
    log_event('PREPROCESS', 'Pipeline kueri', $pp['trace']);
    $qtf = array_count_values($pp['tokens']);

    $qw = []; $qnorm = 0.0; $unknown = [];
    foreach ($qtf as $term => $f) {
        if (!isset($idx['idf'][$term]) || !isset($idx['post'][$term])) { $unknown[] = $term; continue; }
        $qw[$term] = (1 + log10($f)) * $idx['idf'][$term];
        $qnorm += $qw[$term] * $qw[$term];
    }
    $qnorm = sqrt($qnorm);
    log_event('TFIDF', 'Bobot TF-IDF vektor kueri', ['bobot' => array_map(function ($v) { return round($v, 4); }, $qw),
              'norma_kueri' => round($qnorm, 4), 'term_tidak_ada_di_indeks' => $unknown]);
    if ($qnorm == 0) { log_event('RANK', 'Tidak ada term kueri yang cocok dengan indeks'); return ['pp' => $pp, 'qw' => [], 'results' => [], 'cand' => 0]; }

    $dot = []; $contrib = [];
    foreach ($qw as $term => $wq) {
        foreach ($idx['post'][$term] as $d => $wd) {
            $dot[$d] = ($dot[$d] ?? 0) + $wq * $wd;
            $contrib[$d][$term] = [round($wq, 4), $wd, round($wq * $wd, 4)];
        }
    }
    log_event('VSM', 'Dot product kueri x dokumen kandidat', ['jumlah_kandidat' => count($dot)]);
    $scores = [];
    foreach ($dot as $d => $v) {
        $dn = $idx['docs'][$d]['norm'];
        if ($dn > 0) $scores[$d] = $v / ($qnorm * $dn);
    }
    arsort($scores);
    $results = [];
    foreach (array_slice($scores, 0, $topk, true) as $d => $s) $results[] = ['d' => $d, 'score' => $s, 'contrib' => $contrib[$d]];
    $top = [];
    foreach (array_slice($results, 0, 5) as $r) $top[$idx['docs'][$r['d']]['id']] = round($r['score'], 5);
    log_event('RANK', 'Cosine similarity = dot/(|q|*|d|), diurutkan menurun', ['top5 (id=>skor)' => $top, 'total_hasil' => count($scores)]);
    return ['pp' => $pp, 'qw' => $qw, 'results' => $results, 'cand' => count($dot)];
}

function highlight_text(string $text, array $terms, array $opt): string {
    $parts = preg_split('/(\p{L}+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1) {
            $w = mb_strtolower($p, 'UTF-8');
            $s = !empty($opt['stem']) ? pp_stem($w) : $w;
            $out .= isset($terms[$s]) ? '<mark>' . e($p) . '</mark>' : e($p);
        } else $out .= e($p);
    }
    return $out;
}
