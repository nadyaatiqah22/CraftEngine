<?php
// Halaman terpisah: menampilkan kode backend (syntax highlight + nomor baris)
require __DIR__ . '/bootstrap.php';
$files = ['bootstrap.php', 'index.php', 'upload.php', 'search.php', 'log.php', 'code.php',
          'lib/logger.php', 'lib/preprocess.php', 'lib/engine.php', 'lib/layout.php'];
$f = $_GET['f'] ?? 'lib/engine.php';
if (!in_array($f, $files, true)) $f = 'lib/engine.php';
$hl = (int)($_GET['l'] ?? 0);

function php_highlight_lines(string $src): array {
    static $kw = ['function','return','foreach','for','while','if','else','elseif','require','include','static','const','array','match','true','false','null','new','class','use','declare','define','echo','isset','empty','continue','break','switch','case','default','as','fn','and','or','try','catch','throw','exit'];
    $wrap = function ($txt, $cls) { return $cls ? '<span class="' . $cls . '">' . e($txt) . '</span>' : e($txt); };
    $out = '';
    foreach (token_get_all($src) as $t) {
        $cls = '';
        if (is_array($t)) {
            list($id, $txt) = $t;
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) $cls = 'c';
            elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) $cls = 's';
            elseif ($id === T_VARIABLE) $cls = 'v';
            elseif ($id === T_INLINE_HTML) $cls = 'h';
            elseif ($id === T_LNUMBER || $id === T_DNUMBER) $cls = 'n';
            elseif ($id === T_STRING && in_array(strtolower($txt), $kw)) $cls = 'k';
            elseif (!in_array($id, [T_WHITESPACE, T_STRING]) && ctype_alpha($txt[0] ?? '') && in_array(strtolower($txt), $kw)) $cls = 'k';
        } else $txt = $t;
        foreach (explode("\n", $txt) as $i => $part) { if ($i > 0) $out .= "\n"; $out .= $wrap($part, $cls); }
    }
    return explode("\n", $out);
}
log_event('CODE', "Menampilkan kode sumber $f");
$lines = php_highlight_lines(file_get_contents(ROOT . '/' . $f));
layout_head('Kode Backend', 'code'); ?>
<section class="panel">
  <h2>💻 Kode Backend (PHP Native)</h2>
  <div class="filetabs"><?php foreach ($files as $x): ?><a class="<?= $x === $f ? 'on' : '' ?>" href="?f=<?= urlencode($x) ?>"><?= e($x) ?></a><?php endforeach; ?></div>
  <p class="hint">
    <?php $d = ['lib/preprocess.php' => 'Fitur 2: pipeline pra-proses', 'lib/engine.php' => 'Fitur 3: indexing, TF-IDF, cosine VSM', 'search.php' => 'Fitur 4: halaman hasil', 'index.php' => 'Fitur 1: dashboard', 'lib/logger.php' => 'pencatat log proses']; echo e($d[$f] ?? ''); ?>
  </p>
</section>
<section class="panel codebox"><table class="code">
<?php foreach ($lines as $i => $l): $n = $i + 1; ?>
<tr id="L<?= $n ?>" class="<?= $n === $hl ? 'hl' : '' ?>"><td class="ln"><?= $n ?></td><td><pre><?= $l ?></pre></td></tr>
<?php endforeach; ?>
</table></section>
<?php layout_foot();
