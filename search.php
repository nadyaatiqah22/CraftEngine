<?php
// FITUR 4 - Halaman Hasil Pencarian
require __DIR__ . '/bootstrap.php';
$q = trim($_GET['q'] ?? '');
$idx = load_index();
$page = max(1, (int)($_GET['p'] ?? 1)); $per = 10;
layout_head('Hasil: ' . $q, 'home');
?>
<section class="panel">
  <form action="search.php" method="get" class="searchbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Kata kunci..." required>
    <button class="btn green">CARI</button>
  </form>
</section>
<?php
if (!$idx) { echo '<div class="alert bad">Indeks belum ada. <a href="index.php">Import CSV dulu</a>.</div>'; layout_foot(); exit; }
$t0 = microtime(true);
$r = search_query($q, $idx);
$ms = round((microtime(true) - $t0) * 1000, 2);
$total = count($r['results']);
log_event('RESULT', "Menampilkan halaman $page", ['total' => $total, 'waktu_ms' => $ms]);
?>
<section class="panel">
  <p><b><?= $total ?></b> dokumen ditemukan (<?= $ms ?> ms) • kandidat: <?= $r['cand'] ?> • <a href="log.php?run=<?= e($GLOBALS['RUN_ID']) ?>">lihat log kueri ini</a></p>
  <details><summary>Pipeline pra-proses kueri</summary>
    <table class="kv"><?php foreach ($r['pp']['trace'] as $k => $v): ?><tr><td><?= e($k) ?></td><td><?= e($v) ?></td></tr><?php endforeach; ?></table>
    <?php if ($r['qw']): ?><p>Bobot kueri: <?php foreach ($r['qw'] as $t => $w) echo '<code>' . e($t) . '=' . round($w, 4) . '</code> '; ?></p><?php endif; ?>
  </details>
</section>
<?php
foreach (array_slice($r['results'], ($page - 1) * $per, $per) as $i => $res):
    $doc = $idx['docs'][$res['d']]; $rank = ($page - 1) * $per + $i + 1; $pct = round($res['score'] * 100, 1);
?>
<article class="panel result">
  <div class="rank">#<?= $rank ?></div>
  <div class="body">
    <h3><?= highlight_text($doc['title'], $r['qw'], $idx['meta']['opt']) ?> <small>(ID: <?= e($doc['id']) ?>)</small></h3>
    <p><?= highlight_text($doc['snippet'], $r['qw'], $idx['meta']['opt']) ?>…</p>
    <div class="bar"><span style="width:<?= min(100, $pct) ?>%"></span></div>
    <small>Cosine similarity: <b><?= number_format($res['score'], 5) ?></b> (<?= $pct ?>%)</small>
    <details><summary>Rincian perhitungan</summary>
      <table class="kv"><tr><th>Term</th><th>w kueri</th><th>w dokumen</th><th>hasil kali</th></tr>
      <?php foreach ($res['contrib'] as $t => $c): ?><tr><td><?= e($t) ?></td><td><?= $c[0] ?></td><td><?= $c[1] ?></td><td><?= $c[2] ?></td></tr><?php endforeach; ?></table>
      <small>|d| = <?= $doc['norm'] ?> • skor = Σ(w_q·w_d) / (|q|·|d|)</small>
    </details>
  </div>
</article>
<?php endforeach;
if (!$total) echo '<div class="alert bad">Tidak ada dokumen yang cocok.</div>';
$pages = (int)ceil($total / $per);
if ($pages > 1): ?><div class="pager"><?php for ($p = 1; $p <= $pages; $p++): ?><a class="<?= $p === $page ? 'on' : '' ?>" href="?q=<?= urlencode($q) ?>&p=<?= $p ?>"><?= $p ?></a><?php endfor; ?></div><?php endif;
layout_foot();
