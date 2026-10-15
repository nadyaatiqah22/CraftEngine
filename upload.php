<?php
// Handler import CSV -> jalankan indeksasi
require __DIR__ . '/bootstrap.php';
function back(string $m, bool $ok) { header('Location: index.php?ok=' . ($ok ? 1 : 0) . '&msg=' . urlencode($m)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') back('Metode tidak valid', false);
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) { log_event('UPLOAD', 'Upload gagal', ['error' => $_FILES['csv']['error'] ?? 'no file']); back('Upload gagal.', false); }
$f = $_FILES['csv'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'])) { log_event('UPLOAD', 'Ekstensi ditolak', ['ext' => $ext]); back('Hanya file .csv yang diperbolehkan.', false); }
log_event('UPLOAD', 'File diterima', ['nama' => $f['name'], 'ukuran_kb' => round($f['size'] / 1024, 1)]);
$dest = ROOT . '/uploads/dataset.csv';
move_uploaded_file($f['tmp_name'], $dest);
$opt = ['stopword' => isset($_POST['stopword']), 'stem' => isset($_POST['stem'])];
try {
    $meta = build_index($dest, basename($f['name']), $opt);
    log_event('DONE', 'Indeksasi selesai', ['dokumen' => $meta['N'], 'vocab' => $meta['vocab']]);
    back("Berhasil mengindeks {$meta['N']} dokumen ({$meta['vocab']} term).", true);
} catch (Throwable $ex) {
    log_event('ERROR', $ex->getMessage());
    back('Gagal: ' . $ex->getMessage(), false);
}
