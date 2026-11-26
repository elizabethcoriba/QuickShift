<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ---- Security settings ----
const MIN_MS = 60000;
const IDLE_MS = 15 * MIN_MS;
const OTP_TTL_MS = 5 * MIN_MS;
const TIERS = [15, 30, 60];
const IP_LIMIT = 10;
const IP_WINDOW_MS = 10 * MIN_MS;

$QS = [
    'What was the name of your first pet?',
    'What is your mother’s maiden name?',
    'What was the name of your elementary school?',
    'In what city or barangay were you born?',
    'What was your childhood nickname?',
    'Who was your favorite teacher?',
    'What is your favorite food from childhood?',
    'What is the name of your best friend in grade school?'
];

function now_ms(): int {
    return (int) round(microtime(true) * 1000);
}

function uid(): string {
    return base_convert((string) now_ms(), 10, 36) . bin2hex(random_bytes(3));
}

function nrm($a): string {
    return mb_strtolower(trim((string) ($a ?? '')), 'UTF-8');
}

function str_clip($v, int $n): string {
    return mb_substr(trim((string) ($v ?? '')), 0, $n, 'UTF-8');
}

function send_json(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_bearer_token(): string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$auth && function_exists('getallheaders')) {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
}

function get_lock_wait(PDO $pdo, string $uname, int $now): int {
    $st = $pdo->prepare("SELECT until_ts FROM login_locks WHERE username = ?");
    $st->execute([$uname]);
    $row = $st->fetch();
    if ($row && (int) $row['until_ts'] > $now) {
        return (int) ceil(((int) $row['until_ts'] - $now) / 1000);
    }
    return 0;
}

function record_user_fail(PDO $pdo, string $uname, int $now): array {
    $st = $pdo->prepare("SELECT * FROM login_locks WHERE username = ?");
    $st->execute([$uname]);
    $x = $st->fetch() ?: ['username' => $uname, 'n' => 0, 's' => 0, 't' => 0, 'until_ts' => 0];

    $x['t'] = (int) $x['t'] + 1;
    $x['n'] = (int) $x['n'] + 1;
    if ($x['n'] >= 3) {
        $tierSec = TIERS[min((int) $x['s'], 2)];
        $x['until_ts'] = $now + ($tierSec * 1000);
        $x['s'] = (int) $x['s'] + 1;
        $x['n'] = 0;
    }

    $up = $pdo->prepare("
        INSERT INTO login_locks (username, n, s, t, until_ts)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE n = VALUES(n), s = VALUES(s), t = VALUES(t), until_ts = VALUES(until_ts)
    ");
    $up->execute([$uname, $x['n'], $x['s'], $x['t'], $x['until_ts']]);
    return $x;
}

function get_ip_wait(PDO $pdo, string $ip, int $now): int {
    $st = $pdo->prepare("SELECT until_ts FROM ip_blocks WHERE ip = ?");
    $st->execute([$ip]);
    $row = $st->fetch();
    if ($row && (int) $row['until_ts'] > $now) {
        return (int) ceil(((int) $row['until_ts'] - $now) / 1000);
    }
    return 0;
}

function record_ip_fail(PDO $pdo, string $ip, int $now): void {
    $cutoff = $now - IP_WINDOW_MS;
    $pdo->prepare("DELETE FROM ip_attempts WHERE ip = ? AND ts <= ?")->execute([$ip, $cutoff]);
    $pdo->prepare("INSERT INTO ip_attempts (ip, ts) VALUES (?, ?)")->execute([$ip, $now]);

    $cntSt = $pdo->prepare("SELECT COUNT(*) FROM ip_attempts WHERE ip = ?");
    $cntSt->execute([$ip]);
    if ((int) $cntSt->fetchColumn() >= IP_LIMIT) {
        $until = $now + IP_WINDOW_MS;
        $pdo->prepare("
            INSERT INTO ip_blocks (ip, until_ts) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE until_ts = VALUES(until_ts)
        ")->execute([$ip, $until]);
        $pdo->prepare("DELETE FROM ip_attempts WHERE ip = ?")->execute([$ip]);
    }
}

function create_session(PDO $pdo, string $email, int $now): string {
    $tok = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO sessions (token, email, last_active) VALUES (?, ?, ?)")
        ->execute([$tok, $email, $now]);
    return $tok;
}

$action = $_GET['action'] ?? '';
$rawBody = file_get_contents('php://input');
$b = $rawBody ? (json_decode($rawBody, true) ?: []) : [];
$tok = get_bearer_token();
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$now = now_ms();

if ($action === 'questions') {
    http_response_code(200);
    echo json_encode($QS, JSON_UNESCAPED_UNICODE);
    exit;
}

if (in_array($action, ['signup', 'verify_signup', 'login', 'forgot', 'otp', 'reset'], true)) {
    $bw = get_ip_wait($pdo, $ip, $now);
    if ($bw > 0) {
        send_json(429, [
            'error' => 'Too many failed attempts from this device. Try again later.',
            'wait' => $bw,
            'forgot' => true
        ]);
    }
}

$uname = strtolower(trim((string) ($b['email'] ?? '')));

// ---- SIGNUP (SEND GMAIL OTP) ----
if ($action === 'signup') {
    $first = trim((string) ($b['first'] ?? ''));
    $last = trim((string) ($b['last'] ?? ''));
    $gmail = strtolower(trim((string) ($b['gmail'] ?? '')));
    $pw = (string) ($b['pass'] ?? '');
    $qa = is_array($b['qa'] ?? null) ? $b['qa'] : [];
    $ids = array_map(fn($x) => (int) ($x['i'] ?? -1), $qa);

    if ($first === '' || $last === '') {
        send_json(400, ['error' => 'Enter your first and last name.']);
    }
    if (mb_strlen($first) > 50 || mb_strlen($last) > 50) {
        send_json(400, ['error' => 'Each name field can be at most 50 characters.']);
    }
    if (!preg_match('/^[a-z0-9_.]{6,20}$/', $uname)) {
        send_json(400, ['error' => 'Username must be 6–20 characters: letters, numbers, _ or .']);
    }
    if (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        send_json(400, ['error' => 'Enter a valid Gmail address.']);
    }
    if (strlen($pw) < 8 || strlen($pw) > 100) {
        send_json(400, ['error' => 'Password needs at least 8 characters.']);
    }
    if (
        count($qa) !== 3 ||
        count(array_unique($ids)) !== 3 ||
        count(array_filter($ids, fn($i) => $i >= 0 && $i < count($QS))) !== 3 ||
        count(array_filter($qa, fn($x) => nrm($x['a'] ?? '') === '' || mb_strlen((string) ($x['a'] ?? '')) > 100)) > 0
    ) {
        send_json(400, ['error' => 'Pick 3 different security questions and answer each one.']);
    }

    $chk = $pdo->prepare("SELECT email, is_verified FROM users WHERE email = ? OR gmail = ?");
    $chk->execute([$uname, $gmail]);
    $existing = $chk->fetch();
    if ($existing && (int) $existing['is_verified'] === 1) {
        send_json(409, ['error' => 'That username or Gmail is already registered.']);
    }

    $qaStored = [];
    foreach ($qa as $item) {
        $qaStored[] = [
            'i' => (int) $item['i'],
            'h' => password_hash(nrm($item['a']), PASSWORD_DEFAULT)
        ];
    }

    $role = ($b['role'] ?? '') === 'owner' ? 'owner' : 'applicant';
    $phone = str_clip($b['phone'] ?? '', 20);
    $pwHash = password_hash($pw, PASSWORD_DEFAULT);

    if ($existing) {
        $pdo->prepare("
            UPDATE users SET name = ?, email = ?, gmail = ?, role = ?, is_verified = 0, phone = ?, password_hash = ?, qa_json = ?
            WHERE email = ?
        ")->execute([$first . ' ' . $last, $uname, $gmail, $role, $phone, $pwHash, json_encode($qaStored), $existing['email']]);
    } else {
        $pdo->prepare("
            INSERT INTO users (name, email, gmail, role, is_verified, phone, password_hash, qa_json)
            VALUES (?, ?, ?, ?, 0, ?, ?, ?)
        ")->execute([$first . ' ' . $last, $uname, $gmail, $role, $phone, $pwHash, json_encode($qaStored)]);
    }

    $code = (string) random_int(100000, 999999);
    $exp = $now + OTP_TTL_MS;
    $pdo->prepare("
        INSERT INTO signup_otps (username, gmail, code, exp, tries)
        VALUES (?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE gmail = VALUES(gmail), code = VALUES(code), exp = VALUES(exp), tries = 0
    ")->execute([$uname, $gmail, $code, $exp]);

    if (!send_otp_email($gmail, $code, 'Account Verification')) {
        send_json(500, ['error' => 'Could not send OTP to your Gmail. Check mailer.php settings.']);
    }

    send_json(200, ['needVerify' => true, 'username' => $uname]);
}

// ---- VERIFY SIGNUP OTP ----
if ($action === 'verify_signup') {
    $oSt = $pdo->prepare("SELECT * FROM signup_otps WHERE username = ?");
    $oSt->execute([$uname]);
    $o = $oSt->fetch();

    if (!$o || (int) $o['exp'] < $now) {
        send_json(400, ['error' => 'OTP expired or not found. Please sign up again to get a new OTP.']);
    }
    if (trim((string) ($b['code'] ?? '')) !== $o['code']) {
        record_ip_fail($pdo, $ip, $now);
        $tries = (int) $o['tries'] + 1;
        if ($tries >= 5) {
            $pdo->prepare("DELETE FROM signup_otps WHERE username = ?")->execute([$uname]);
        } else {
            $pdo->prepare("UPDATE signup_otps SET tries = ? WHERE username = ?")->execute([$tries, $uname]);
        }
        send_json(400, ['error' => 'Wrong OTP code.']);
    }

    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE email = ?")->execute([$uname]);
    $pdo->prepare("DELETE FROM signup_otps WHERE username = ?")->execute([$uname]);

    $t = create_session($pdo, $uname, $now);
    send_json(200, ['token' => $t]);
}

// ---- LOGIN ----
if ($action === 'login') {
    $w = get_lock_wait($pdo, $uname, $now);
    if ($w > 0) {
        send_json(429, ['error' => 'Too many failed attempts.', 'wait' => $w, 'forgot' => true]);
    }

    $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([$uname]);
    $u = $st->fetch();

    $pass = (string) ($b['pass'] ?? '');
    if (!$u || !password_verify($pass, $u['password_hash'] ?? '')) {
        $x = record_user_fail($pdo, $uname, $now);
        record_ip_fail($pdo, $ip, $now);
        $w2 = max(get_lock_wait($pdo, $uname, $now), get_ip_wait($pdo, $ip, $now));
        $forgot = ((int) $x['t']) >= 2;

        if ($w2 > 0) {
            send_json(429, ['error' => 'Too many failed attempts.', 'wait' => $w2, 'forgot' => $forgot]);
        }
        send_json(401, [
            'error' => 'Wrong username or password.',
            'left' => 3 - (int) $x['n'],
            'forgot' => $forgot
        ]);
    }

    if ((int) ($u['is_verified'] ?? 1) === 0 && !empty($u['gmail'])) {
        $code = (string) random_int(100000, 999999);
        $exp = $now + OTP_TTL_MS;
        $pdo->prepare("
            INSERT INTO signup_otps (username, gmail, code, exp, tries)
            VALUES (?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE code = VALUES(code), exp = VALUES(exp), tries = 0
        ")->execute([$uname, $u['gmail'], $code, $exp]);

        if (!send_otp_email($u['gmail'], $code, 'Account Verification')) {
            send_json(500, ['error' => 'Could not send OTP to your Gmail. Check mailer.php settings.']);
        }
        send_json(403, [
            'needVerify' => true,
            'username' => $uname,
            'error' => 'Your Gmail is not verified yet. A new OTP was sent to your Gmail.'
        ]);
    }

    $pdo->prepare("DELETE FROM login_locks WHERE username = ?")->execute([$uname]);
    $t = create_session($pdo, $uname, $now);
    send_json(200, ['token' => $t]);
}

// ---- FORGOT PASSWORD: STEP 1 (OTP TO GMAIL) ----
if ($action === 'forgot') {
    $st = $pdo->prepare("SELECT email, gmail FROM users WHERE email = ? OR gmail = ?");
    $st->execute([$uname, $uname]);
    $u = $st->fetch();
    if (!$u || empty($u['gmail'])) {
        send_json(200, ['ok' => 1]);
    }

    $code = (string) random_int(100000, 999999);
    $exp = $now + OTP_TTL_MS;
    $pdo->prepare("
        INSERT INTO otp_resets (username, code, exp, tries, rt, rexp, rtries)
        VALUES (?, ?, ?, 0, NULL, 0, 0)
        ON DUPLICATE KEY UPDATE code = VALUES(code), exp = VALUES(exp), tries = 0, rt = NULL, rexp = 0, rtries = 0
    ")->execute([$u['email'], $code, $exp]);

    if (!send_otp_email($u['gmail'], $code, 'Password Reset')) {
        send_json(500, ['error' => 'Could not send OTP to your Gmail. Check mailer.php settings.']);
    }

    send_json(200, ['ok' => 1, 'resolvedUser' => $u['email']]);
}

// ---- FORGOT PASSWORD: STEP 2 (VERIFY OTP) ----
if ($action === 'otp') {
    $uSt = $pdo->prepare("SELECT qa_json FROM users WHERE email = ?");
    $uSt->execute([$uname]);
    $u = $uSt->fetch();

    $oSt = $pdo->prepare("SELECT * FROM otp_resets WHERE username = ?");
    $oSt->execute([$uname]);
    $o = $oSt->fetch();

    if (!$u || !$o || (int) $o['exp'] < $now) {
        send_json(400, ['error' => 'OTP expired or not found. Request a new one.']);
    }
    if (trim((string) ($b['code'] ?? '')) !== $o['code']) {
        record_ip_fail($pdo, $ip, $now);
        $tries = (int) $o['tries'] + 1;
        if ($tries >= 5) {
            $pdo->prepare("DELETE FROM otp_resets WHERE username = ?")->execute([$uname]);
        } else {
            $pdo->prepare("UPDATE otp_resets SET tries = ? WHERE username = ?")->execute([$tries, $uname]);
        }
        send_json(400, ['error' => 'Wrong OTP.']);
    }

    $rt = bin2hex(random_bytes(16));
    $rexp = $now + OTP_TTL_MS;
    $pdo->prepare("UPDATE otp_resets SET rt = ?, rexp = ?, rtries = 0 WHERE username = ?")
        ->execute([$rt, $rexp, $uname]);

    $qaList = json_decode($u['qa_json'], true) ?: [];
    $questions = array_map(fn($x) => $QS[(int) $x['i']] ?? '', $qaList);

    send_json(200, ['rt' => $rt, 'questions' => $questions]);
}

// ---- FORGOT PASSWORD: STEP 3 (VERIFY SECURITY QUESTIONS + RESET) ----
if ($action === 'reset') {
    $uSt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $uSt->execute([$uname]);
    $u = $uSt->fetch();

    $oSt = $pdo->prepare("SELECT * FROM otp_resets WHERE username = ?");
    $oSt->execute([$uname]);
    $o = $oSt->fetch();

    $pw = (string) ($b['pass'] ?? '');
    $ans = is_array($b['answers'] ?? null) ? $b['answers'] : [];

    if (!$u || !$o || empty($o['rt']) || (int) $o['rexp'] < $now || $o['rt'] !== ($b['rt'] ?? '')) {
        send_json(400, ['error' => 'Your reset session expired. Start again.']);
    }
    if (strlen($pw) < 8 || strlen($pw) > 100) {
        send_json(400, ['error' => 'New password needs at least 8 characters.']);
    }

    $qaList = json_decode($u['qa_json'], true) ?: [];
    $allOk = count($qaList) === 3;
    foreach ($qaList as $k => $item) {
        $given = nrm($ans[$k] ?? '');
        if (!password_verify($given, $item['h'] ?? '')) {
            $allOk = false;
            break;
        }
    }

    if (!$allOk) {
        record_ip_fail($pdo, $ip, $now);
        $rtries = (int) $o['rtries'] + 1;
        if ($rtries >= 5) {
            $pdo->prepare("DELETE FROM otp_resets WHERE username = ?")->execute([$uname]);
        } else {
            $pdo->prepare("UPDATE otp_resets SET rtries = ? WHERE username = ?")->execute([$rtries, $uname]);
        }
        send_json(400, ['error' => 'One or more answers are wrong.']);
    }

    $newHash = password_hash($pw, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
        ->execute([$newHash, $uname]);
    $pdo->prepare("DELETE FROM otp_resets WHERE username = ?")->execute([$uname]);
    $pdo->prepare("DELETE FROM login_locks WHERE username = ?")->execute([$uname]);
    $pdo->prepare("DELETE FROM sessions WHERE email = ?")->execute([$uname]);

    send_json(200, ['ok' => 1]);
}

// ---- AUTHENTICATION MIDDLEWARE (15-min idle timeout) ----
$sSt = $pdo->prepare("SELECT * FROM sessions WHERE token = ?");
$sSt->execute([$tok]);
$ss = $sSt->fetch();

if ($ss && ($now - (int) $ss['last_active'] > IDLE_MS)) {
    $pdo->prepare("DELETE FROM sessions WHERE token = ?")->execute([$tok]);
    send_json(401, ['error' => 'You were logged out after 15 minutes of inactivity.']);
}

$me = null;
if ($ss) {
    $mSt = $pdo->prepare("SELECT name, email, role, phone FROM users WHERE email = ?");
    $mSt->execute([$ss['email']]);
    $me = $mSt->fetch();
}
if (!$me) {
    send_json(401, ['error' => 'Please log in again.']);
}

$isPoll = ($_SERVER['HTTP_X_POLL'] ?? '') === '1';
if (!$isPoll) {
    $pdo->prepare("UPDATE sessions SET last_active = ? WHERE token = ?")->execute([$now, $tok]);
}

if ($action === 'ping') {
    send_json(200, ['ok' => 1]);
}

if ($action === 'logout') {
    $pdo->prepare("DELETE FROM sessions WHERE token = ?")->execute([$tok]);
    send_json(200, []);
}

// ---- FETCH ALL APP DATA ----
if ($action === 'data') {
    $users = $pdo->query("SELECT name, email, role FROM users")->fetchAll();

    $jobsRaw = $pdo->query("
        SELECT j.*,
               (SELECT COUNT(*) FROM apps a WHERE a.job_id = j.id AND a.status = 'Hired') AS hired_cnt
        FROM jobs j
        ORDER BY j.ts DESC
    ")->fetchAll();

    $jobs = array_map(fn($j) => [
        'id' => $j['id'],
        'title' => $j['title'],
        'cat' => $j['cat'],
        'loc' => $j['loc'],
        'pay' => (float) $j['pay'],
        'unit' => $j['unit'],
        'days' => json_decode($j['days_json'], true) ?: [],
        't1' => $j['t1'],
        't2' => $j['t2'],
        'slots' => (int) $j['slots'],
        'desc' => $j['descr'] ?? '',
        'owner' => $j['owner'],
        'ownerName' => $j['owner_name'],
        'ts' => (int) $j['ts'],
        'open' => (bool) $j['open'],
        'hired' => (int) $j['hired_cnt']
    ], $jobsRaw);

    if ($me['role'] === 'owner') {
        $aSt = $pdo->prepare("
            SELECT a.* FROM apps a
            INNER JOIN jobs j ON j.id = a.job_id
            WHERE j.owner = ?
        ");
        $aSt->execute([$me['email']]);
    } else {
        $aSt = $pdo->prepare("SELECT * FROM apps WHERE applicant = ?");
        $aSt->execute([$me['email']]);
    }
    $apps = array_map(fn($a) => [
        'id' => $a['id'],
        'jobId' => $a['job_id'],
        'title' => $a['title'],
        'applicant' => $a['applicant'],
        'name' => $a['name'],
        'phone' => $a['phone'],
        'note' => $a['note'] ?? '',
        'status' => $a['status'],
        'ts' => (int) $a['ts']
    ], $aSt->fetchAll());

    $mSt = $pdo->prepare("SELECT * FROM msgs WHERE sender = ? OR recipient = ? ORDER BY ts ASC");
    $mSt->execute([$me['email'], $me['email']]);
    $msgs = array_map(function ($m) {
        $row = [
            'id' => $m['id'],
            'j' => $m['job_id'],
            'ap' => $m['ap'],
            'from' => $m['sender'],
            'to' => $m['recipient'],
            'text' => $m['text'],
            'ts' => (int) $m['ts'],
            'read' => (bool) $m['is_read']
        ];
        if ((int) $m['is_sys'] === 1) {
            $row['sys'] = true;
        }
        return $row;
    }, $mSt->fetchAll());

    send_json(200, [
        'me' => $me,
        'users' => $users,
        'jobs' => $jobs,
        'apps' => $apps,
        'msgs' => $msgs
    ]);
}

// ---- OPERATIONS ----
if ($action === 'op') {
    $isO = $me['role'] === 'owner';
    $op = $b['op'] ?? '';
    $id = (string) ($b['id'] ?? '');

    $hiredCount = function (string $jobId) use ($pdo): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM apps WHERE job_id = ? AND status = 'Hired'");
        $st->execute([$jobId]);
        return (int) $st->fetchColumn();
    };

    switch ($op) {
        case 'post': {
            if (!$isO) send_json(403, ['error' => 'Only owners can post jobs.']);
            $days = array_values(array_filter(
                array_map('intval', is_array($b['days'] ?? null) ? $b['days'] : []),
                fn($d) => $d >= 0 && $d < 7
            ));
            $title = str_clip($b['title'] ?? '', 60);
            $pay = (float) ($b['pay'] ?? 0);
            $t1 = str_clip($b['t1'] ?? '', 5);
            $t2 = str_clip($b['t2'] ?? '', 5);

            if ($title === '' || $pay <= 0 || empty($days) || $t1 === '' || $t2 === '') {
                send_json(400, ['error' => 'Fill in the title, pay, days and hours.']);
            }

            $cat = str_clip($b['cat'] ?? '', 20);
            $loc = str_clip($b['loc'] ?? '', 40) ?: 'Cagayan de Oro';
            $unit = in_array($b['unit'] ?? '', ['hour', 'day', 'week'], true) ? $b['unit'] : 'day';
            $slots = max(1, min(99, (int) ($b['slots'] ?? 1)));
            $desc = str_clip($b['desc'] ?? '', 500);

            $pdo->prepare("
                INSERT INTO jobs (id, title, cat, loc, pay, unit, days_json, t1, t2, slots, descr, owner, owner_name, ts, open)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([uid(), $title, $cat, $loc, $pay, $unit, json_encode($days), $t1, $t2, $slots, $desc, $me['email'], $me['name'], $now]);

            send_json(200, ['ok' => 1]);
        }

        case 'toggle': {
            $jSt = $pdo->prepare("SELECT owner, open FROM jobs WHERE id = ?");
            $jSt->execute([$id]);
            $job = $jSt->fetch();
            if (!$job || $job['owner'] !== $me['email']) send_json(403, ['error' => 'Not allowed.']);

            $newOpen = ((int) $job['open'] === 1) ? 0 : 1;
            $pdo->prepare("UPDATE jobs SET open = ? WHERE id = ?")->execute([$newOpen, $id]);
            send_json(200, ['ok' => 1]);
        }

        case 'del': {
            $jSt = $pdo->prepare("SELECT owner FROM jobs WHERE id = ?");
            $jSt->execute([$id]);
            $job = $jSt->fetch();
            if (!$job || $job['owner'] !== $me['email']) send_json(403, ['error' => 'Not allowed.']);

            $pdo->prepare("DELETE FROM jobs WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM apps WHERE job_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM msgs WHERE job_id = ?")->execute([$id]);
            send_json(200, ['ok' => 1]);
        }

        case 'apply': {
            if ($isO) send_json(403, ['error' => 'Owners can’t apply for jobs.']);
            $jSt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
            $jSt->execute([$id]);
            $job = $jSt->fetch();

            if (!$job || (int) $job['open'] !== 1 || $hiredCount($job['id']) >= (int) $job['slots']) {
                send_json(400, ['error' => 'This job is no longer open.']);
            }

            $dup = $pdo->prepare("SELECT 1 FROM apps WHERE job_id = ? AND applicant = ?");
            $dup->execute([$job['id'], $me['email']]);
            if ($dup->fetch()) {
                send_json(400, ['error' => 'You already applied.']);
            }

            $note = str_clip($b['note'] ?? '', 300);
            $pdo->prepare("
                INSERT INTO apps (id, job_id, title, applicant, name, phone, note, status, ts)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ")->execute([uid(), $job['id'], $job['title'], $me['email'], $me['name'], $me['phone'], $note, $now]);

            if ($note !== '') {
                $pdo->prepare("
                    INSERT INTO msgs (id, job_id, ap, sender, recipient, text, ts, is_read, is_sys)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
                ")->execute([uid(), $job['id'], $me['email'], $me['email'], $job['owner'], $note, $now]);
            }
            send_json(200, ['ok' => 1]);
        }

        case 'st': {
            $aSt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
            $aSt->execute([$id]);
            $app = $aSt->fetch();

            $jSt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
            $jSt->execute([$app['job_id'] ?? '']);
            $j = $jSt->fetch();

            if (!$j || $j['owner'] !== $me['email']) send_json(403, ['error' => 'Not allowed.']);
            $status = (string) ($b['s'] ?? '');
            if (!in_array($status, ['Reviewed', 'Hired', 'Rejected'], true)) {
                send_json(400, ['error' => 'Bad status.']);
            }
            if ($status === 'Hired' && $app['status'] !== 'Hired' && $hiredCount($j['id']) >= (int) $j['slots']) {
                send_json(400, ['error' => 'All slots for this job are filled.']);
            }

            $pdo->prepare("UPDATE apps SET status = ? WHERE id = ?")->execute([$status, $id]);
            $sysMsg = "Your application for {$j['title']} is now {$status}.";
            $pdo->prepare("
                INSERT INTO msgs (id, job_id, ap, sender, recipient, text, ts, is_read, is_sys)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)
            ")->execute([uid(), $j['id'], $app['applicant'], $me['email'], $app['applicant'], $sysMsg, $now]);

            send_json(200, ['ok' => 1]);
        }

        case 'wd': {
            $aSt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
            $aSt->execute([$id]);
            $app = $aSt->fetch();

            if (!$app || $app['applicant'] !== $me['email'] || !in_array($app['status'], ['Pending', 'Reviewed'], true)) {
                send_json(400, ['error' => 'Can’t withdraw this one.']);
            }
            $pdo->prepare("DELETE FROM apps WHERE id = ?")->execute([$id]);
            send_json(200, ['ok' => 1]);
        }

        case 'send': {
            $jId = (string) ($b['j'] ?? '');
            $ap = (string) ($b['ap'] ?? '');
            $text = str_clip($b['text'] ?? '', 1000);

            $jSt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
            $jSt->execute([$jId]);
            $j = $jSt->fetch();

            $uSt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
            $uSt->execute([$ap]);

            if (!$j || $text === '' || !$uSt->fetch()) {
                send_json(400, ['error' => 'Can’t send that message.']);
            }
            if ($me['email'] !== $j['owner'] && $me['email'] !== $ap) {
                send_json(403, ['error' => 'Not allowed.']);
            }
            if ($me['email'] === $j['owner']) {
                $chkApp = $pdo->prepare("SELECT 1 FROM apps WHERE job_id = ? AND applicant = ?");
                $chkApp->execute([$j['id'], $ap]);
                if (!$chkApp->fetch()) {
                    send_json(400, ['error' => 'That person hasn’t applied to this job.']);
                }
            }

            $to = ($me['email'] === $j['owner']) ? $ap : $j['owner'];
            $pdo->prepare("
                INSERT INTO msgs (id, job_id, ap, sender, recipient, text, ts, is_read, is_sys)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
            ")->execute([uid(), $j['id'], $ap, $me['email'], $to, $text, $now]);

            send_json(200, ['ok' => 1]);
        }

        case 'read': {
            $jId = (string) ($b['j'] ?? '');
            $ap = (string) ($b['ap'] ?? '');
            $pdo->prepare("UPDATE msgs SET is_read = 1 WHERE job_id = ? AND ap = ? AND recipient = ?")
                ->execute([$jId, $ap, $me['email']]);
            send_json(200, ['ok' => 1]);
        }
    }

    send_json(400, ['error' => 'Unknown action.']);
}

send_json(404, ['error' => 'Not found']);