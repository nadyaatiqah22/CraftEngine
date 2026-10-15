<?php
// Halaman Log Proses: semua proses sistem + potongan kode yang menjalankannya
require __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'clear') { @unlink(LOG_FILE); header('Location: log.php'); exit; }
$rows = read_log();
$runs = [];
foreach ($rows as $r) { if (!isset($runs[$r['run']])) $runs[$r['run']] = ['t' => $r['t'], 'first' => $r['stage'] . ': ' . $r['msg']]; }
$sel = $_GET['run'] ?? (array_key_last($runs) ?: '');
$stage = $_GET['stage'] ?? '';
$show = array_filter($rows, function ($r) use ($sel, $stage) { return ($sel === 'all' || $r['run'] === $sel) && ($stage === '' || $r['stage'] === $stage); });

function code_ctx(string $file, int $line, int $before = 5): string {
    $path = ROOT . '/' . $file;
    if (!is_file($path)) return '';
    $L = file($path); $out = '';
    for ($i = max(1, $line - $before); $i <= min(count($L), $line); $i++) {
        $out .= sprintf("%4d  %s", $i, $L[$i - 1]);
    }
    return $out;
}
layout_head('Log Proses', 'log'); ?>
<section class="panel">
  <h2>📜 Log Proses Sistem</h2>
  <form method="get" class="filters">
    <select name="run" onchange="this.form.submit()">
      <option value="all" <?= $sel === 'all' ? 'selected' : '' ?>>Semua run</option>
      <?php foreach (array_reverse($runs, true) as $id => $info): ?>
      <option value="<?= e($id) ?>" <?= $sel === $id ? 'selected' : '' ?>><?= e($info['t'] . ' • ' . mb_substr($info['first'], 0, 40)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stage" onchange="this.form.submit()">
      <option value="">Semua tahap</option>
      <?php foreach (['UPLOAD','CSV','INDEX','PREPROCESS','DF','IDF','TFIDF','QUERY','VSM','RANK','RESULT','DONE','ERROR'] as $s): ?>
      <option <?= $stage === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
    </select>
  </form>
  <form method="post" onsubmit="return confirm('Hapus semua log?')" style="display:inline"><input type="hidden" name="act" value="clear"><button class="btn red">Hapus Log</button></form>
</section>
<?php if (!$show): ?><div class="alert bad">Belum ada log.</div><?php endif; ?>
<?php foreach ($show as $r): ?>
<section class="panel logrow st-<?= e($r['stage']) ?>">
  <div class="loghead"><span class="tag"><?= e($r['stage']) ?></span> <b><?= e($r['msg']) ?></b>
    <small><?= e($r['t']) ?> • +<?= $r['ms'] ?> ms • run <?= e($r['run']) ?></small></div>
  <?php if ($r['ctx']): ?><pre class="ctx"><?= e(json_encode($r['ctx'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
  <details><summary>Kode: <code><?= e($r['fn']) ?>()</code> di <a href="code.php?f=<?= urlencode($r['file']) ?>&l=<?= $r['line'] ?>#L<?= $r['line'] ?>"><?= e($r['file']) ?>:<?= $r['line'] ?></a></summary>
    <pre class="ctx"><?= e(code_ctx($r['file'], $r['line'])) ?></pre></details>
</section>
<?php endforeach; layout_foot();
