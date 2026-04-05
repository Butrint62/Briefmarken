<?php
session_start();
define('ADMIN_PASSWORD', 'Percusion123!Ariel');
define('CARDS_FILE', __DIR__ . '/cards.json');
define('UPLOAD_DIR', __DIR__ . '/images/');

// ── AUTH ──────────────────────────────────────────────────────────────────────
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = 'Falsches Passwort.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
$auth = !empty($_SESSION['admin']);

// ── HELPERS ───────────────────────────────────────────────────────────────────
function loadCards() {
    if (file_exists(CARDS_FILE)) {
        return json_decode(file_get_contents(CARDS_FILE), true) ?: [];
    }
    return ['schweiz' => [], 'china' => [], 'deutschland' => []];
}
function saveCards($cards) {
    file_put_contents(CARDS_FILE, json_encode($cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function handleUpload($category) {
    if (empty($_FILES['image']['name'])) return '';
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) return '';
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return '';
    $dir = UPLOAD_DIR . $category . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename);
    return 'images/' . $category . '/' . $filename;
}

// ── ACTIONS ───────────────────────────────────────────────────────────────────
if ($auth && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cards = loadCards();
    $cat   = $_POST['category'] ?? '';

    if ($_POST['action'] === 'add' && $cat) {
        $img = handleUpload($cat) ?: ($_POST['image_url'] ?? 'images/image_4.jpg');
        $cards[$cat][] = [
            'id'    => uniqid(),
            'name'  => trim($_POST['name'] ?? ''),
            'desc'  => trim($_POST['desc'] ?? ''),
            'price' => trim($_POST['price'] ?? ''),
            'image' => $img,
        ];
        saveCards($cards);
        header('Location: admin.php?cat=' . $cat . '&msg=added');
        exit;
    }

    if ($_POST['action'] === 'delete' && $cat) {
        $id = $_POST['id'] ?? '';
        $cards[$cat] = array_values(array_filter($cards[$cat], fn($c) => $c['id'] !== $id));
        saveCards($cards);
        header('Location: admin.php?cat=' . $cat . '&msg=deleted');
        exit;
    }

    if ($_POST['action'] === 'edit' && $cat) {
        $id = $_POST['id'] ?? '';
        foreach ($cards[$cat] as &$card) {
            if ($card['id'] === $id) {
                $card['name']  = trim($_POST['name']  ?? $card['name']);
                $card['desc']  = trim($_POST['desc']  ?? $card['desc']);
                $card['price'] = trim($_POST['price'] ?? $card['price']);
                $newImg = handleUpload($cat);
                if ($newImg) $card['image'] = $newImg;
                break;
            }
        }
        saveCards($cards);
        header('Location: admin.php?cat=' . $cat . '&msg=saved');
        exit;
    }
}

$cards  = $auth ? loadCards() : [];
$cats   = ['schweiz' => 'Schweizer Briefmarken', 'china' => 'Chinesische Briefmarken', 'deutschland' => 'Deutsche Briefmarken'];
$activeCat = $_GET['cat'] ?? 'schweiz';
if (!array_key_exists($activeCat, $cats)) $activeCat = 'schweiz';
$msg    = $_GET['msg'] ?? '';
$editId = $_GET['edit'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin – Briefmarken Basel</title>
  <link href="https://fonts.googleapis.com/css2?family=Bungee&family=Raleway:wght@400;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Raleway', sans-serif; background: #17427A; color: #fff; min-height: 100vh; }

    /* LOGIN */
    .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-box { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 48px 40px; width: 100%; max-width: 380px; text-align: center; }
    .login-box h1 { font-family: 'Bungee', sans-serif; font-size: 22px; letter-spacing: 0.04em; margin-bottom: 8px; }
    .login-box p { font-size: 13px; color: rgba(255,255,255,0.6); margin-bottom: 28px; }
    .login-box input[type=password] { width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); color: #fff; font-size: 15px; margin-bottom: 14px; outline: none; }
    .login-box input[type=password]::placeholder { color: rgba(255,255,255,0.4); }
    .btn-primary { background: #25D366; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-family: 'Raleway', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; width: 100%; }
    .btn-primary:hover { opacity: 0.9; }
    .error { color: #ffaaaa; font-size: 13px; margin-top: 10px; }

    /* LAYOUT */
    header { background: #0f2d54; padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
    header h1 { font-family: 'Bungee', sans-serif; font-size: 18px; letter-spacing: 0.04em; }
    header a { color: rgba(255,255,255,0.6); font-size: 13px; text-decoration: none; }
    header a:hover { color: #fff; }
    .container { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }

    /* TABS */
    .tabs { display: flex; gap: 4px; margin-bottom: 28px; border-bottom: 1px solid rgba(255,255,255,0.15); }
    .tab { font-family: 'Bungee', sans-serif; font-size: 13px; letter-spacing: 0.04em; padding: 12px 20px; color: rgba(255,255,255,0.5); text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .tab.active { color: #fff; border-bottom-color: #B0D2FF; }
    .tab:hover { color: #fff; }

    /* NOTICE */
    .notice { background: rgba(37,211,102,0.15); border: 1px solid rgba(37,211,102,0.4); border-radius: 8px; padding: 10px 16px; font-size: 13px; margin-bottom: 20px; }
    .notice.del { background: rgba(255,100,100,0.1); border-color: rgba(255,100,100,0.3); }

    /* ADD FORM */
    .section-title { font-family: 'Bungee', sans-serif; font-size: 16px; letter-spacing: 0.03em; margin-bottom: 16px; color: #B0D2FF; }
    .add-form { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 24px; margin-bottom: 36px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label { font-size: 12px; color: rgba(255,255,255,0.6); letter-spacing: 0.05em; text-transform: uppercase; }
    .form-group input, .form-group textarea { padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.06); color: #fff; font-family: 'Raleway', sans-serif; font-size: 14px; outline: none; }
    .form-group input::placeholder, .form-group textarea::placeholder { color: rgba(255,255,255,0.35); }
    .form-group input[type=file] { padding: 8px 12px; cursor: pointer; }
    .form-actions { margin-top: 18px; display: flex; gap: 10px; }
    .btn-add { background: #25D366; color: #fff; border: none; border-radius: 8px; padding: 10px 24px; font-family: 'Raleway', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-add:hover { opacity: 0.9; }
    .btn-cancel { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 10px 24px; font-family: 'Raleway', sans-serif; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }

    /* CARDS GRID */
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .admin-card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; overflow: hidden; }
    .admin-card img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; }
    .admin-card-body { padding: 12px; }
    .admin-card-name { font-family: 'Bungee', sans-serif; font-size: 13px; margin-bottom: 4px; }
    .admin-card-desc { font-size: 11px; color: rgba(255,255,255,0.55); margin-bottom: 4px; line-height: 1.4; }
    .admin-card-price { font-size: 13px; color: #B0D2FF; font-weight: 700; margin-bottom: 12px; }
    .admin-card-actions { display: flex; gap: 6px; }
    .btn-edit { background: #17427A; border: 1px solid rgba(255,255,255,0.2); color: #fff; border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer; font-family: 'Raleway', sans-serif; font-weight: 700; text-decoration: none; display: inline-block; }
    .btn-delete { background: rgba(220,50,50,0.2); border: 1px solid rgba(220,50,50,0.4); color: #ffaaaa; border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer; font-family: 'Raleway', sans-serif; font-weight: 700; }
    .btn-edit:hover { background: #1e5499; }
    .btn-delete:hover { background: rgba(220,50,50,0.4); }

    @media (max-width: 600px) {
      .form-grid { grid-template-columns: 1fr; }
      .cards-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<?php if (!$auth): ?>
<!-- LOGIN PAGE -->
<div class="login-wrap">
  <div class="login-box">
    <h1>BRIEFMARKEN BASEL</h1>
    <p>Admin-Bereich — Bitte anmelden</p>
    <form method="POST">
      <input type="password" name="password" placeholder="Passwort" autofocus />
      <button type="submit" class="btn-primary">Anmelden</button>
      <?php if (!empty($loginError)): ?>
        <p class="error"><?= htmlspecialchars($loginError) ?></p>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ADMIN PANEL -->
<header>
  <h1>BRIEFMARKEN ADMIN</h1>
  <a href="?logout=1">Abmelden</a>
</header>

<div class="container">

  <?php if ($msg === 'added'):   ?><div class="notice">✓ Briefmarke hinzugefügt.</div><?php endif; ?>
  <?php if ($msg === 'saved'):   ?><div class="notice">✓ Änderungen gespeichert.</div><?php endif; ?>
  <?php if ($msg === 'deleted'): ?><div class="notice del">Briefmarke gelöscht.</div><?php endif; ?>

  <!-- TABS -->
  <div class="tabs">
    <?php foreach ($cats as $key => $label): ?>
      <a href="?cat=<?= $key ?>" class="tab <?= $activeCat === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- ADD / EDIT FORM -->
  <?php
    $editing = null;
    if ($editId) {
      foreach ($cards[$activeCat] as $c) {
        if ($c['id'] === $editId) { $editing = $c; break; }
      }
    }
  ?>
  <p class="section-title"><?= $editing ? 'Briefmarke bearbeiten' : 'Neue Briefmarke hinzufügen' ?></p>
  <div class="add-form">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>"/>
      <input type="hidden" name="category" value="<?= $activeCat ?>"/>
      <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editing['id']) ?>"/>
      <?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="z.B. Helvetia sitzend" value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required/>
        </div>
        <div class="form-group">
          <label>Preis (CHF)</label>
          <input type="text" name="price" placeholder="z.B. 45 oder 45.50" value="<?= htmlspecialchars($editing['price'] ?? '') ?>"/>
        </div>
        <div class="form-group full">
          <label>Beschreibung</label>
          <input type="text" name="desc" placeholder="z.B. 1862, ungestempelt" value="<?= htmlspecialchars($editing['desc'] ?? '') ?>"/>
        </div>
        <div class="form-group full">
          <label>Bild hochladen</label>
          <input type="file" name="image" accept="image/*"/>
          <?php if ($editing && !empty($editing['image'])): ?>
            <small style="color:rgba(255,255,255,0.45); font-size:11px; margin-top:4px;">Aktuell: <?= htmlspecialchars($editing['image']) ?> — leer lassen um beizubehalten</small>
          <?php endif; ?>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-add"><?= $editing ? 'Speichern' : '+ Hinzufügen' ?></button>
        <?php if ($editing): ?>
          <a href="?cat=<?= $activeCat ?>" class="btn-cancel">Abbrechen</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- CARDS LIST -->
  <p class="section-title"><?= count($cards[$activeCat]) ?> Briefmarken — <?= $cats[$activeCat] ?></p>
  <div class="cards-grid">
    <?php foreach ($cards[$activeCat] as $card): ?>
    <div class="admin-card">
      <img src="<?= htmlspecialchars($card['image']) ?>" alt="<?= htmlspecialchars($card['name']) ?>"/>
      <div class="admin-card-body">
        <div class="admin-card-name"><?= htmlspecialchars($card['name']) ?></div>
        <div class="admin-card-desc"><?= htmlspecialchars($card['desc']) ?></div>
        <div class="admin-card-price"><?= $card['price'] ? 'CHF ' . htmlspecialchars($card['price']) : '—' ?></div>
        <div class="admin-card-actions">
          <a href="?cat=<?= $activeCat ?>&edit=<?= $card['id'] ?>" class="btn-edit">Bearbeiten</a>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Wirklich löschen?')">
            <input type="hidden" name="action" value="delete"/>
            <input type="hidden" name="category" value="<?= $activeCat ?>"/>
            <input type="hidden" name="id" value="<?= $card['id'] ?>"/>
            <button type="submit" class="btn-delete">Löschen</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>
<?php endif; ?>
</body>
</html>
