<?php
// FITUR 1 - Dashboard Pencarian + Import CSV
require __DIR__ . '/bootstrap.php';
$idx = load_index();
$meta = $idx['meta'] ?? null;
$msg = $_GET['msg'] ?? ''; $ok = ($_GET['ok'] ?? '1') === '1';
layout_head('Beranda', 'home'); ?>
<section class="panel hero">
  <h1>SEARCH ENGINE
  </h1>
  <p class="sub">Vector Space Model • TF-IDF • Cosine Similarity</p>
  <?php if ($msg): ?><div class="alert <?= $ok ? 'good' : 'bad' ?>"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($meta): ?>
  <form action="search.php" method="get" class="searchbar">
    <input type="text" name="q" placeholder="Masukkan kata kunci..." required autofocus>
    <button class="btn green">CARI</button>
  </form>
  <?php else: ?>
  <div class="alert bad">Belum ada indeks. Import file CSV dokumen terlebih dahulu di bawah.</div>
  <?php endif; ?>
</section>

<div class="grid">
  <section class="panel">
    <h2>📦 Import Dataset CSV</h2>
    <form action="upload.php" method="post" enctype="multipart/form-data">
      <input type="file" name="csv" accept=".csv,.txt" required>
      <label class="chk"><input type="checkbox" name="stopword" checked> Hapus stopword</label>
      <label class="chk"><input type="checkbox" name="stem" checked> Stemming (Bahasa Indonesia)</label>
      <p class="hint">Delimiter &amp; kolom dideteksi otomatis: kolom id (id/no), judul (judul/title/nama), sisanya dianggap isi dokumen. Baris pertama = header.</p>
      <button class="btn green">INDEKS DOKUMEN</button>
    </form>
  </section>
  <section class="panel">
    <h2>📊 Status Indeks</h2>
    <?php if ($meta): ?>
    <table class="kv">
      <tr><td>File</td><td><?= e($meta['file']) ?></td></tr>
      <tr><td>Dokumen (N)</td><td><?= (int)$meta['N'] ?></td></tr>
      <tr><td>Vocabulary</td><td><?= (int)$meta['vocab'] ?> term</td></tr>
      <tr><td>Kolom judul</td><td><?= e($meta['title_col']) ?></td></tr>
      <tr><td>Kolom isi</td><td><?= e(implode(', ', $meta['content_cols'])) ?></td></tr>
      <tr><td>Stopword / Stem</td><td><?= $meta['opt']['stopword'] ? 'ya' : 'tidak' ?> / <?= $meta['opt']['stem'] ? 'ya' : 'tidak' ?></td></tr>
      <tr><td>Dibuat</td><td><?= e($meta['built']) ?></td></tr>
    </table>
    <?php else: ?><p class="hint">Tidak ada data.</p><?php endif; ?>
    <a class="btn" href="log.php">Lihat Log Proses</a>
    <a class="btn" href="code.php">Lihat Kode Backend</a>
  </section>
</div>
<?php layout_foot();
