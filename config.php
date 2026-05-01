<?php
// ============================================================
// STEAMhives RMS — Database Configuration
// Rename this file: config.php  (already named correctly)
// Edit the credentials below to match your cPanel database.
// ============================================================

define('DB_HOST', 'localhost');       // Usually localhost on cPanel
define('DB_NAME', 'your_db_name');    // e.g. myuser_steamhives
define('DB_USER', 'your_db_user');    // e.g. myuser_rms
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Developer admin key (change this!)
define('DEV_KEY_HASH', password_hash('DEVKEY_BABS_2024_ADMIN', PASSWORD_BCRYPT));

// Session security
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// ============================================================
// Database connection (PDO singleton)
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
    return $pdo;
}

// ============================================================
// Bootstrap: sessions + CORS
// ============================================================
function bootstrap(): void {
    // Session
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // CORS — adjust origin for production
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ============================================================
// Helpers
// ============================================================
function input(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) return $json;
    return $_POST ?: [];
}

function ok(array $data = []): void {
    echo json_encode(['ok' => true] + $data);
    exit;
}

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function requireSchoolSession(): string {
    if (empty($_SESSION['school_id'])) fail('Not authenticated', 401);
    return $_SESSION['school_id'];
}

function requireDevSession(): void {
    if (empty($_SESSION['dev_auth'])) fail('Developer access required', 401);
}

function simpleHash(string $s): string {
    // Mirrors JS version for password comparison; use only for school passwords
    $hash = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        $hash = (int)(fmod((($hash * 31) + ord($s[$i])), 4294967296));
        if ($hash >= 2147483648) $hash -= 4294967296;
    }
    return (string)$hash;
}

function logAudit(string $action, ?string $schoolId = null, string $details = ''): void {
    try {
        db()->prepare('INSERT INTO sh_audit_log (school_id, action, details, ip) VALUES (?,?,?,?)')
             ->execute([$schoolId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) { /* non-fatal */ }
}
