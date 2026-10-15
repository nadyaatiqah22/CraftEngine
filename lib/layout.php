<?php
function layout_head(string $title, string $active = ''): void { ?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Craft Search</title>
<link rel="stylesheet" href="assets/style.css">
</head><body>
<header class="nav">
  <a class="brand" href="index.php">⛏ CRAFT SEARCH</a>
  <nav>
    <a class="<?= $active === 'home' ? 'on' : '' ?>" href="index.php">Beranda</a>
    <a class="<?= $active === 'log' ? 'on' : '' ?>" href="log.php">Log Proses</a>
    <a class="<?= $active === 'code' ? 'on' : '' ?>" href="code.php">Backend</a>
  </nav>
</header>
<main>
<?php }
function layout_foot(): void { ?>
</main>
<footer>VSM + TF-IDF • PHP Native • Run ID: <?= e($GLOBALS['RUN_ID']) ?></footer>
</body></html>
<?php }
