<?php
// Admin API for self-hosting on a PHP server (e.g. Cafe24).
// Saves data/projects.json and uploaded images next to index.html, behind the password in config.php.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$ROOT = dirname(__DIR__);
$DATA_FILE = $ROOT . '/data/projects.json';
$IMAGE_DIR = $ROOT . '/images';
$BACKUP_DIR = __DIR__ . '/backup';
$ATTEMPTS_FILE = __DIR__ . '/login-attempts.json';
$MAX_BACKUPS = 30;
$MAX_UPLOAD = 20 * 1024 * 1024;

$ADMIN_PASSWORD = '';
if (is_file(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
$configured = is_string($ADMIN_PASSWORD) && $ADMIN_PASSWORD !== '' && $ADMIN_PASSWORD !== 'CHANGE-ME';

session_name('studio_admin');
session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
session_start();

function reply($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail($status, $code) { reply($status, array('ok' => false, 'error' => $code)); }
function logged_in() { return !empty($_SESSION['admin']); }
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function require_admin() {
    if (!logged_in()) fail(401, 'login_required');
    $sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (!hash_equals(csrf(), $sent)) fail(403, 'bad_token');
}
function json_body() {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 2 * 1024 * 1024) fail(413, 'too_large');
    $v = json_decode($raw, true);
    if (!is_array($v)) fail(400, 'bad_json');
    return $v;
}
function write_atomic($path, $bytes) {
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $bytes, LOCK_EX) === false) return false;
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

// Login throttling: 5 wrong passwords per address in 10 minutes.
function attempts_load($file) {
    $all = is_file($file) ? json_decode((string) file_get_contents($file), true) : array();
    $now = time(); $out = array();
    foreach ((is_array($all) ? $all : array()) as $ip => $times) {
        $keep = array_values(array_filter((array) $times, function ($t) use ($now) { return $t > $now - 600; }));
        if ($keep) $out[$ip] = $keep;
    }
    return $out;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

if ($action === 'status') {
    $body = array('ok' => true, 'configured' => $configured, 'loggedIn' => logged_in());
    if (logged_in()) $body['csrf'] = csrf();
    reply(200, $body);
}

if ($method !== 'POST') fail(405, 'method');

if ($action === 'login') {
    if (!$configured) fail(503, 'not_configured');
    $attempts = attempts_load($ATTEMPTS_FILE);
    if (isset($attempts[$ip]) && count($attempts[$ip]) >= 5) fail(429, 'too_many_attempts');
    $in = json_body();
    $password = isset($in['password']) ? (string) $in['password'] : '';
    if (!hash_equals((string) $ADMIN_PASSWORD, $password)) {
        $attempts[$ip][] = time();
        @file_put_contents($ATTEMPTS_FILE, json_encode($attempts), LOCK_EX);
        sleep(1);
        fail(401, 'wrong_password');
    }
    unset($attempts[$ip]);
    @file_put_contents($ATTEMPTS_FILE, json_encode($attempts), LOCK_EX);
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    reply(200, array('ok' => true, 'csrf' => $_SESSION['csrf']));
}

if ($action === 'logout') {
    $_SESSION = array();
    session_destroy();
    reply(200, array('ok' => true));
}

if ($action === 'save') {
    require_admin();
    $in = json_body();
    if (!isset($in['site']) || !is_array($in['site']) || !isset($in['projects']) || !is_array($in['projects'])) fail(400, 'bad_shape');
    $json = json_encode(array('site' => $in['site'], 'projects' => array_values($in['projects'])),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    // Keep the previous version so a bad save can be undone by hand.
    if (is_file($DATA_FILE)) {
        if (!is_dir($BACKUP_DIR)) @mkdir($BACKUP_DIR, 0755, true);
        @copy($DATA_FILE, $BACKUP_DIR . '/projects-' . date('Ymd-His') . '.json');
        $old = glob($BACKUP_DIR . '/projects-*.json');
        sort($old);
        while (count($old) > $MAX_BACKUPS) @unlink(array_shift($old));
    }
    if (!write_atomic($DATA_FILE, $json)) fail(500, 'write_failed');
    reply(200, array('ok' => true));
}

if ($action === 'upload') {
    require_admin();
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = empty($_FILES['file']) ? -1 : $_FILES['file']['error'];
        fail($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 413 : 400, $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'too_large' : 'no_file');
    }
    $f = $_FILES['file'];
    if ($f['size'] > $MAX_UPLOAD) fail(413, 'too_large');
    // Trust the image bytes, not the name or the browser's type.
    $info = @getimagesize($f['tmp_name']);
    $types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif');
    if (defined('IMAGETYPE_WEBP')) $types[IMAGETYPE_WEBP] = 'webp';
    if (!$info || !isset($types[$info[2]])) fail(415, 'not_image');
    $base = strtolower(preg_replace('/\.[^.]+$/', '', (string) $f['name']));
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-');
    $name = base_convert((string) time(), 10, 36) . '-' . bin2hex(random_bytes(3)) . ($base !== '' ? '-' . substr($base, 0, 40) : '') . '.' . $types[$info[2]];
    if (!is_dir($IMAGE_DIR)) @mkdir($IMAGE_DIR, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $IMAGE_DIR . '/' . $name)) fail(500, 'write_failed');
    @chmod($IMAGE_DIR . '/' . $name, 0644);
    reply(200, array('ok' => true, 'path' => 'images/' . $name));
}

fail(404, 'unknown_action');
