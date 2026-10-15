<?php
// FITUR 2 - Pipeline Pra-proses Teks:
// case folding -> cleaning -> tokenizing -> stopword removal -> stemming (Indonesia)

function stopwords(): array {
    static $s = null;
    if ($s === null) {
        $list = 'yang dan di ke dari dalam untuk pada dengan ini itu atau juga akan adalah ada tidak sudah belum telah '
              . 'oleh sebagai saat karena agar jika bila maka namun tetapi tapi lebih sangat sebuah para suatu kami kita '
              . 'mereka saya anda dia kamu nya lah kah pun hal serta antara hingga sampai bagi dapat bisa harus masih '
              . 'sedang seperti setelah sebelum ketika yaitu yakni dll dsb the of and to in is a for on with';
        $s = array_flip(explode(' ', $list));
    }
    return $s;
}

function pp_casefold(string $t): string { return mb_strtolower($t, 'UTF-8'); }

function pp_clean(string $t): string {
    $t = strip_tags($t);
    $t = preg_replace('/https?:\/\/\S+/u', ' ', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

function pp_tokenize(string $t): array {
    $tok = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter($tok, function ($w) { return mb_strlen($w) >= 2; }));
}

function pp_stopword(array $tok): array {
    $sw = stopwords();
    return array_values(array_filter($tok, function ($w) use ($sw) { return !isset($sw[$w]); }));
}

function strip_if(string $w, string $re, string $rep = '', int $min = 3): string {
    $n = preg_replace($re, $rep, $w, 1);
    return ($n !== null && mb_strlen($n) >= $min) ? $n : $w;
}

// Stemmer Bahasa Indonesia sederhana (versi ringkas Nazief-Adriani)
function pp_stem(string $w): string {
    static $cache = [];
    if (isset($cache[$w])) return $cache[$w];
    $orig = $w;
    if (mb_strlen($w) <= 4 || preg_match('/\d/', $w)) return $cache[$orig] = $w;
    $w = strip_if($w, '/(lah|kah|tah|pun)$/u');   // partikel
    $w = strip_if($w, '/(ku|mu|nya)$/u');          // kata ganti milik
    $w = strip_if($w, '/(kan|an)$/u');             // akhiran derivasi
    if (mb_strlen($w) > 5) $w = strip_if($w, '/i$/u');
    for ($i = 0; $i < 2; $i++) {                   // awalan (maks 2 lapis)
        $before = $w;
        $w = strip_if($w, '/^meny/u', 's');
        if ($w === $before) $w = strip_if($w, '/^peny/u', 's');
        if ($w === $before) $w = strip_if($w, '/^(meng|men|mem|me|peng|pen|pem|per|pe|di|ter|ke|se|ber|be)/u');
        if ($w === $before) break;
    }
    return $cache[$orig] = $w;
}

// Jalankan seluruh pipeline. $trace=true -> kembalikan hasil tiap langkah (untuk log)
function preprocess(string $text, array $opt, bool $trace = false): array {
    $steps = [];
    $show = function ($v) { return is_array($v) ? implode(' | ', array_slice($v, 0, 25)) : mb_substr($v, 0, 160); };
    if ($trace) $steps['0. Teks asli'] = $show($text);
    $t = pp_casefold($text);          if ($trace) $steps['1. Case folding'] = $show($t);
    $t = pp_clean($t);                if ($trace) $steps['2. Cleaning (hapus simbol/URL/HTML)'] = $show($t);
    $tok = pp_tokenize($t);           if ($trace) $steps['3. Tokenizing'] = $show($tok);
    if (!empty($opt['stopword'])) { $tok = pp_stopword($tok); if ($trace) $steps['4. Stopword removal'] = $show($tok); }
    if (!empty($opt['stem']))     { $tok = array_map('pp_stem', $tok); if ($trace) $steps['5. Stemming'] = $show($tok); }
    return ['tokens' => $tok, 'trace' => $steps];
}
