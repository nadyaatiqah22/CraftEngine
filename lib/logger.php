<?php
// Logger: mencatat setiap proses sistem + lokasi kode (file & baris) yang menjalankannya
function log_event(string $stage, string $msg, array $ctx = []): void {
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $at = $bt[0];
    $fn = isset($bt[1]['function']) ? $bt[1]['function'] : 'main';
    $row = [
        't'     => date('Y-m-d H:i:s'),
        'ms'    => round((microtime(true) - $GLOBALS['T0']) * 1000, 1),
        'run'   => $GLOBALS['RUN_ID'],
        'stage' => $stage,
        'msg'   => $msg,
        'ctx'   => $ctx,
        'fn'    => $fn,
        'file'  => ltrim(str_replace(ROOT, '', str_replace('\\', '/', $at['file'])), '/'),
        'line'  => $at['line'],
    ];
    @file_put_contents(LOG_FILE, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function read_log(int $max = 5000): array {
    if (!is_file(LOG_FILE)) return [];
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($lines, -$max);
    $rows = [];
    foreach ($lines as $l) { $r = json_decode($l, true); if ($r) $rows[] = $r; }
    return $rows;
}
