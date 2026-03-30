<?php
// ─── Database ───────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'elearning_adaptif');
define('DB_USER', 'elearning_user');
define('DB_PASS', 'kr3$N@n@r@y@n4');

// ─── Koneksi PDO ────────────────────────────────────────
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ─── Python AI ──────────────────────────────────────────
define('PYTHON_BIN',    '/var/www/html/aibotlms/ai/venv/bin/python');
define('CLASSIFY_SCRIPT', '/var/www/html/aibotlms/ai/scripts/classify.py');

// ─── Fonnte WA Gateway ──────────────────────────────────
define('FONNTE_TOKEN', 'btDkU5qGcEGGnVgDjNGQ');
define('FONNTE_URL',   'https://api.fonnte.com/send');

// ─── LLM API (WA Bot asisten) ───────────────────────────
define('LLM_API_KEY', 'btDkU5qGcEGGnVgDjNGQ');
define('LLM_API_URL', 'https://api.anthropic.com/v1/messages');
define('LLM_MODEL',   'claude-sonnet-4-20250514');

// ─── App ────────────────────────────────────────────────
define('APP_URL',  'http://103.67.78.4');
define('APP_NAME', 'AdaptLearn PRE — SMK Bansari');
define('APP_ENV',  'development');

?>
