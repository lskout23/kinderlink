<?php
/**
 * LOCAL REVIEW ONLY. Router for PHP's loopback development server.
 * Never loads application configuration, database, authentication or mailer.
 * Do not deploy this file. Unknown paths and write APIs are rejected.
 */
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/public/css/style.css' => 'text/css',
    '/public/css/ui.css' => 'text/css',
    '/public/css/dashboard.css' => 'text/css',
    '/public/css/kinderlink.css' => 'text/css',
    '/public/js/ui.js' => 'application/javascript',
    '/public/js/dialogs.js' => 'application/javascript',
    '/public/css/dialogs.css' => 'text/css',
    '/public/vendor/lucide/lucide-0.468.0.min.js' => 'application/javascript',
    '/public/css/ui-compact.css' => 'text/css',
    '/public/js/ui-compact.js' => 'application/javascript',
    '/tools/ui-compact.js' => 'application/javascript',
    '/public/kinderlink-mark.svg' => 'image/svg+xml',
    '/public/icons/icon-192.png' => 'image/png',
    '/public/icons/icon-512.png' => 'image/png',
];
header('Cache-Control: no-store');
if (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path]);
    readfile($root . $path);
    return;
}
if ($path === '/favicon.ico') { http_response_code(204); return; }

define('BASE_URL', '');
define('APP_URL', '');
define('APP_NAME_GR', 'KinderLink');
$role = $_GET['role'] ?? $_COOKIE['kinderlink_preview_role'] ?? 'admin';
$compact = ($_GET['design'] ?? 'compact') === 'compact';
if (!in_array($role, ['admin', 'teacher', 'parent'], true)) $role = 'admin';
setcookie('kinderlink_preview_role', $role, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
// Minimal template dependencies; no real authentication or session is started.
class Controller { public function csrfToken() { return 'local-ui-preview-not-a-real-token'; } }
if ($path === '/manifest.json' || $path === '/sw.js') {
    require $root . '/controllers/PwaController.php';
    $pwa = new PwaController();
    if ($path === '/manifest.json') $pwa->manifest();
    else $pwa->serviceWorker();
    return;
}
class Auth {
    public static function isAdmin() { return $GLOBALS['role'] === 'admin'; }
    public static function isParent() { return $GLOBALS['role'] === 'parent'; }
    public static function check() { return false; }
}
class InboxController { public function countUnread($user) { return 2; } }
// Only needed when the local CLI lacks mbstring. Production uses real mbstring.
if (!function_exists('mb_substr')) {
    function mb_substr($value, $start, $length, $encoding = 'UTF-8') {
        return implode('', array_slice(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY), $start, $length));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($value, $encoding = 'UTF-8') { return strtoupper($value); }
}
$today = date('Y-m-d');
$user = ['id' => 999, 'name' => ['admin' => 'Διαχειριστής Demo', 'teacher' => 'Εκπαιδευτικός Demo', 'parent' => 'Γονέας Demo'][$role], 'role' => $role];
$groups = [['id' => 1, 'name' => 'Μικροί Εξερευνητές'], ['id' => 2, 'name' => 'Ηλιαχτίδες']];
$children = [['id' => 1, 'first_name' => 'Παιδί', 'last_name' => 'Δοκιμής Α'], ['id' => 2, 'first_name' => 'Παιδί', 'last_name' => 'Δοκιμής Β']];
$parents = [['id' => 999, 'name' => 'Γονέας Demo', 'username' => 'parent-demo']];
$childRecords = array_map(function ($child) {
    return $child + [
        'dob' => '2022-01-15', 'mother_mobile' => '', 'father_mobile' => '',
        'email1' => 'parent-demo@example.invalid', 'email2' => '',
        'parent_user_id' => 999, 'parent_name' => 'Γονέας Demo', 'parent_username' => 'parent-demo',
        'send_email1' => 1, 'send_email2' => 0, 'active' => 1,
    ];
}, $children);
$rows = [];
foreach ($children as $index => $child) {
    $rows[] = $child + [
        'msg_date' => date('d/m/Y'), 'message_date' => $today, 'group_name' => $groups[$index]['name'],
        'breakfast' => 4, 'lunch' => 3, 'mood' => 4, 'sleep_minutes' => 45, 'wc' => 1,
        'activities' => 'Ζωγραφική, μουσική και παιχνίδι στην αυλή.',
        'activities_txt' => 'Ζωγραφική, μουσική και παιχνίδι στην αυλή.',
        'comments' => 'Συμμετείχε με χαρά στις ομαδικές δραστηριότητες.',
        'email_status' => $index ? 'pending' : 'sent',
    ];
}
if (str_starts_with($path, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');
    switch ($path) {
        case '/api/auth/ping': $data = ['alive' => true]; break;
        case '/api/children':
            if ($role !== 'admin') { http_response_code(403); $data = ['error' => 'Admin preview only']; break; }
            $search = trim($_POST['search'] ?? '');
            $filtered = array_values(array_filter($childRecords, fn($child) => $search === '' || stripos(implode(' ', $child), $search) !== false));
            $size = max(1, min(200, (int)($_POST['page_size'] ?? 16)));
            $page = max(1, (int)($_POST['page'] ?? 1));
            $data = ['total' => count($filtered), 'rows' => array_slice($filtered, ($page - 1) * $size, $size)];
            break;
        case '/api/messages/search': $data = ['rows' => $rows]; break;
        case '/api/messages/list-by-group':
            $data = ['rows' => array_map(fn($row) => $row + ['is_absent' => false], $rows), 'attendance_ready' => true];
            break;
        case '/api/parent/messages':
            $childId = (int)($_POST['child_id'] ?? 1);
            $data = ['rows' => array_values(array_filter($rows, fn($row) => $row['id'] === $childId))];
            break;
        case '/api/messages/email-preview':
            $data = ['html' => '<h2>Δοκιμαστική ημερήσια ενημέρωση</h2><p>Ζωγραφική, μουσική και παιχνίδι στην αυλή.</p><p>Απομονωμένη προεπισκόπηση — δεν αποστέλλεται email.</p>'];
            break;
        case '/api/inbox/thread-list': $data = ['threads' => []]; break;
        default:
            http_response_code(403);
            $data = ['error' => 'Τοπική προεπισκόπηση UI: η ενέργεια δεν εκτελείται. Δεν αποθηκεύονται δεδομένα και δεν αποστέλλονται email.'];
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    return;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(403);
    echo 'Local UI preview: submissions are disabled.';
    return;
}
$routes = [
    '/' => ['auth/login.php', 'Σύνδεση'],
    '/forgot-password' => ['auth/forgot-password.php', 'Ανάκτηση Κωδικού'],
    '/forgot-username' => ['auth/forgot-username.php', 'Υπενθύμιση Ονόματος'],
    '/dashboard' => ['dashboard/index.php', 'Κέντρο Ελέγχου'],
    '/administration/setup-children' => ['children/index.php', 'Ορισμός Παιδιών'],
    '/administration/setup-parameters' => ['parameters/index.php', 'Παράμετροι'],
    '/messages/create-messages' => ['messages/create.php', 'Δημιουργία Μηνυμάτων'],
    '/messages/message-list' => ['messages/list.php', 'Λίστα Μηνυμάτων'],
    '/parent/dashboard' => ['parent/dashboard.php', 'Δραστηριότητες Παιδιού'],
    '/inbox' => ['inbox/index.php', 'Εισερχόμενα'],
];
if (!isset($routes[$path])) {
    http_response_code(404);
    echo '<meta charset="utf-8"><p>Αυτή η οθόνη δεν περιλαμβάνεται στην απομονωμένη προεπισκόπηση.</p><a href="/dashboard">Επιστροφή</a>';
    return;
}
[$view, $pageTitle] = $routes[$path];
if (in_array($view, ['children/index.php', 'parameters/index.php'], true) && $role !== 'admin') {
    http_response_code(403);
    echo 'Admin preview only.';
    return;
}
if ($path === '/parent/dashboard') $user['role'] = $role = 'parent';
$stats = ['children' => 48, 'groups' => 4, 'messages_today' => 36, 'emails_sent_today' => 32, 'absent_today' => 4];
$actBoth = [['name' => 'Ζωγραφική & δημιουργίες'], ['name' => 'Μουσικοκινητική αγωγή'], ['name' => 'Παιχνίδι στην αυλή']];
$actEmailOnly = [['name' => 'Συμμετοχή στις ομαδικές δραστηριότητες'], ['name' => 'Καλή συνεργασία με την ομάδα']];
$photoHiddenGraceDays = 15;
$photoRetentionDays = 180;
$attendanceReady = false;
$params = ['school_name' => 'Δοκιμαστικό σχολείο', 'email_mode' => 'virtual'];
$smtpHealth = ['configured' => false];
$todayCount = 2;
// Match MessagesController::create(): this legacy flag enables photo controls
// for both staff roles. Other screens retain their admin-only checks.
$isAdmin = $view === 'messages/create.php'
    ? in_array($role, ['admin', 'teacher'], true)
    : $role === 'admin';
ob_start();
require $root . '/views/' . $view;
$content = ob_get_clean();
$comparisonScreens = ['/messages/create-messages', '/administration/setup-children'];
if (in_array($path, $comparisonScreens, true)) {
    $safeRole = rawurlencode($role);
    $safePath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    $designQuery = $compact ? '&amp;design=compact' : '';
    $variant = $compact ? 'Β · Συμπαγές / επάνω μενού' : 'Α · Πλαϊνό μενού';
    $reviewBar = '<aside class="review-bar compare-bar" aria-label="Σύγκριση σχεδιασμού">'
        . '<strong>ΠΡΟΕΠΙΣΚΟΠΗΣΗ ' . $variant . '</strong>'
        . '<span>Μόνο δοκιμαστικά δεδομένα · καμία αποθήκευση ή αποστολή</span>'
        . '<nav aria-label="Εκδοχή σχεδιασμού">'
        . '<a href="' . $safePath . '?role=' . $safeRole . '&amp;design=sidebar"' . (!$compact ? ' aria-current="page"' : '') . '>Α · Sidebar</a>'
        . '<a href="' . $safePath . '?role=' . $safeRole . '&amp;design=compact"' . ($compact ? ' aria-current="page"' : '') . '>Β · Συμπαγές</a>'
        . '</nav><nav aria-label="Οθόνες σύγκρισης">'
        . '<a href="/messages/create-messages?role=' . $safeRole . $designQuery . '">Δημιουργία μηνυμάτων</a>'
        . ($role === 'admin' ? '<a href="/administration/setup-children?role=admin' . $designQuery . '">Ορισμός παιδιών</a>' : '')
        . '</nav></aside>';
}
$reviewBar = $reviewBar ?? '<aside class="review-bar" aria-label="Τοπική προεπισκόπηση"><strong>LOCAL UI REVIEW</strong><span>Δοκιμαστικά δεδομένα · χωρίς αποστολές ή αποθήκευση</span><nav><a href="/">Login</a><a href="/dashboard?role=admin">Admin</a><a href="/dashboard?role=teacher">Teacher</a><a href="/messages/create-messages?role=teacher">Δημιουργία</a><a href="/messages/message-list?role=admin">Λίστα</a><a href="/parent/dashboard?role=parent">Γονέας</a></nav></aside>';
$content = $reviewBar . $content;
$reviewCss = '<style>.review-bar{font:11px/1.6 system-ui,sans-serif;background:#fff7df;border:1px solid #e9d9a3;color:#725721;border-radius:10px;padding:10px 14px;margin-bottom:22px;display:flex;gap:8px 16px;align-items:center;flex-wrap:wrap}.review-bar nav{display:flex;gap:12px;flex-wrap:wrap}.review-bar a{color:#66501d;text-decoration:underline}.public-page .login-wrapper{flex-wrap:wrap}.public-page .review-bar{width:100%;max-width:1000px}</style>';
ob_start();
require $root . '/views/layouts/' . (in_array($path, ['/', '/forgot-password', '/forgot-username'], true) ? 'public.php' : 'main.php');
$html = ob_get_clean();
if ($compact) {
    // Use the same compact assets as the application; only review links are extra.
    $reviewCss .= '<style>.compact-ui .compare-bar{margin-bottom:14px;background:#fffdf5;border-color:#e5dfc7;font-size:11px}.compare-bar nav a[aria-current="page"]{font-weight:700;text-decoration-thickness:2px}</style>';
    $html = str_replace('</body>', '<script src="/tools/ui-compact.js?v=3" defer></script></body>', $html);
} else {
    // Retain the sidebar comparison without changing the real application.
    $html = str_replace('class="modern-ui app-page compact-ui"', 'class="modern-ui app-page"', $html);
    $html = preg_replace('~<(?:link|script)\b[^>]*(?:href|src)="/public/(?:css|js)/ui-compact\.(?:css|js)\?[^"<>]*"[^>]*>(?:</script>)?~', '', $html);
}
echo str_replace('</head>', $reviewCss . '</head>', $html);