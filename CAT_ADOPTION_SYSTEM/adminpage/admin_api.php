<?php
// Purrfect Home - Admin API
// File: adminpage/admin_api.php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Bilangan minimum found report untuk layak dapat reward.
// Mesti sama dengan REWARD_MIN_FOUND dalam admin.js.
const REWARD_MIN_FOUND = 4;

function respond($success, $data = null, $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Admin sahaja dibenarkan menggunakan API ini.
if (
    !isset($_SESSION['role']) ||
    strtolower((string) $_SESSION['role']) !== 'admin'
) {
    respond(false, null, 'Sila log masuk sebagai admin.', 401);
}

require_once __DIR__ . '/../db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    respond(false, null, 'Sambungan PDO $pdo tidak ditemui dalam db.php.', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function tableColumns(PDO $pdo, $table)
{
    // Simpan senarai kolum supaya tidak query berulang kali dalam satu request.
    static $cache = [];

    $allowed = [
        'cats',
        'login_student',
        'login_admin',
        'adoption_applications',
        'found_cats',
        'notifications',
        'student_rewards',
        'reward_given'
    ];

    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Nama jadual tidak dibenarkan.');
    }

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return $cache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasTable(PDO $pdo, $table)
{
    return count(tableColumns($pdo, $table)) > 0;
}

function firstColumn(array $columns, array $candidates)
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

function pick(array $row, array $candidates, $default = '')
{
    foreach ($candidates as $key) {
        if (array_key_exists($key, $row) &&
            $row[$key] !== null &&
            $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function getRows(PDO $pdo, $table)
{
    if (!hasTable($pdo, $table)) {
        return [];
    }

    return $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
}

function countRows(PDO $pdo, $table)
{
    if (!hasTable($pdo, $table)) {
        return 0;
    }

    return (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}

function countByStatus(PDO $pdo, $table, $status)
{
    if (!hasTable($pdo, $table)) {
        return 0;
    }

    $columns = tableColumns($pdo, $table);
    $statusColumn = firstColumn($columns, ['status', 'application_status']);

    if (!$statusColumn) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `$table`
         WHERE LOWER(`$statusColumn`) = LOWER(?)"
    );
    $stmt->execute([$status]);

    return (int) $stmt->fetchColumn();
}

// Add the pickup columns automatically if they do not exist yet.
function ensurePickupColumns(PDO $pdo)
{
    $definitions = [
        'pickup_location' => 'VARCHAR(255) NULL',
        'pickup_date'     => 'DATE NULL',
        'pickup_time'     => 'TIME NULL',
        'admin_contact'   => 'VARCHAR(255) NULL',
        'pickup_notes'    => 'TEXT NULL',
        'picked_up_at'    => 'DATETIME NULL'
    ];

    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['adoption_applications']);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($definitions as $column => $definition) {
        if (in_array($column, $existing, true)) {
            continue;
        }
        try {
            $pdo->exec(
                "ALTER TABLE `adoption_applications` ADD COLUMN `$column` $definition"
            );
        } catch (PDOException $e) {
            error_log('Could not add column ' . $column . ': ' . $e->getMessage());
        }
    }
}

// Cipta jadual reward_given secara automatik jika belum wujud.
// Jadual ini menyimpan pelajar yang hadiahnya sudah diberi.
// Mesti dipanggil di luar transaction (CREATE TABLE menyebabkan implicit commit).
function ensureRewardTable(PDO $pdo)
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `reward_given` (
            `student_id` INT NOT NULL PRIMARY KEY,
            `given_at` DATETIME NOT NULL
        )'
    );
}

function buildDashboardData(PDO $pdo)
{
    ensureRewardTable($pdo);

    $rawCats = getRows($pdo, 'cats');
    $rawApps = getRows($pdo, 'adoption_applications');
    // Ensure every approved application has a pickup QR token when the schema supports it.
    $applicationColumns = tableColumns($pdo, 'adoption_applications');
    $applicationIdColumn = firstColumn($applicationColumns, ['application_id', 'id']);
    $applicationStatusColumn = firstColumn($applicationColumns, ['status', 'application_status']);
    $pickupTokenColumn = firstColumn($applicationColumns, ['pickup_token']);
    if ($applicationIdColumn && $applicationStatusColumn && $pickupTokenColumn) {
        $updateToken = $pdo->prepare(
            "UPDATE `adoption_applications`
             SET `$pickupTokenColumn` = ?
             WHERE `$applicationIdColumn` = ?
               AND LOWER(`$applicationStatusColumn`) = 'approved'
               AND (`$pickupTokenColumn` IS NULL OR `$pickupTokenColumn` = '')"
        );
        foreach ($rawApps as $existingApp) {
            $appStatus = strtolower((string) pick($existingApp, ['status', 'application_status']));
            $tokenValue = pick($existingApp, ['pickup_token'], '');
            if ($appStatus === 'approved' && $tokenValue === '') {
                $appId = pick($existingApp, ['application_id', 'id']);
                if ($appId !== '') {
                    $updateToken->execute([bin2hex(random_bytes(24)), $appId]);
                }
            }
        }
        $rawApps = getRows($pdo, 'adoption_applications');
    }
    $rawFound = getRows($pdo, 'found_cats');
    $rawStudents = getRows($pdo, 'login_student');

    // Pelajar yang hadiahnya sudah diberi: [student_id => given_at]
    $rewardGiven = [];
    foreach (getRows($pdo, 'reward_given') as $givenRow) {
        $rewardGiven[(string) $givenRow['student_id']] = (string) $givenRow['given_at'];
    }

    $studentMap = [];

    foreach ($rawStudents as $student) {
        $sid = (string) pick($student, ['student_id', 'id']);

        $studentMap[$sid] = [
            'name' => pick(
                $student,
                ['nama', 'name', 'student_name', 'full_name'],
                '-'
            ),
            'email' => pick($student, ['email', 'student_email'], '')
        ];
    }

    $catMap = [];
    $catDetailsMap = [];

    foreach ($rawCats as $cat) {
        $cid = (string) pick($cat, ['cat_id', 'id']);
        $catMap[$cid] = pick($cat, ['cat_name', 'name', 'catname'], '-');
        $catDetailsMap[$cid] = [
            'age' => pick($cat, ['age', 'cat_age'], ''),
            'gender' => pick($cat, ['gender', 'sex'], ''),
            'image' => pick($cat, ['image_path', 'photo_path', 'image', 'cat_image', 'photo'], '')
        ];
    }

    $cats = array_map(function ($cat) {
        return [
            'id' => pick($cat, ['cat_id', 'id']),
            'name' => pick($cat, ['cat_name', 'name', 'catname']),
            'age' => pick($cat, ['age', 'cat_age']),
            'gender' => pick($cat, ['gender', 'sex']),
            'status' => pick($cat, ['status'], 'Available')
        ];
    }, $rawCats);

    $adoptCount = [];

    $applications = array_map(
        function ($app) use ($studentMap, $catMap, $catDetailsMap, &$adoptCount) {
            $sid = (string) pick($app, ['student_id', 'user_id']);
            $cid = (string) pick($app, ['cat_id']);
            $status = pick($app, ['status', 'application_status'], 'Pending');

            if (strtolower($status) === 'approved' && $sid !== '') {
                $adoptCount[$sid] = ($adoptCount[$sid] ?? 0) + 1;
            }

            // Other submitted fields, shown in View Details.
            $skipKeys = [
                'application_id', 'id', 'student_id', 'user_id', 'cat_id',
                'status', 'application_status', 'created_at', 'application_date',
                'date_applied', 'applied_at', 'date', 'reason',
                'application_reason', 'adoption_reason', 'student_name', 'nama',
                'name', 'email', 'student_email', 'cat_name', 'cat', 'cat_age',
                'cat_gender', 'gender', 'sex', 'age', 'pickup_location',
                'pickup_date', 'pickup_time', 'admin_contact', 'pickup_notes',
                'pickup_token', 'pickup_verified', 'picked_up_at'
            ];
            $extra = [];
            foreach ($app as $key => $value) {
                if (in_array($key, $skipKeys, true) || $value === null || $value === '') {
                    continue;
                }
                $extra[$key] = (string) $value;
            }

            return [
                'extra' => (object) $extra,
                'id' => pick($app, ['application_id', 'id']),
                'student' => $studentMap[$sid]['name']
                    ?? pick($app, ['student_name', 'nama', 'name'], '-'),
                'email' => $studentMap[$sid]['email']
                    ?? pick($app, ['email', 'student_email'], ''),
                'cat' => $catMap[$cid]
                    ?? pick($app, ['cat_name', 'cat'], '-'),
                'date' => substr((string) pick(
                    $app,
                    ['created_at', 'application_date', 'date_applied', 'applied_at', 'date']
                ), 0, 10),
                'status' => $status,
                'student_id' => $sid,
                'cat_id' => $cid,
                'reason' => pick($app, ['reason', 'application_reason', 'adoption_reason'], ''),
                'cat_age' => $catDetailsMap[$cid]['age'] ?? pick($app, ['cat_age', 'age'], ''),
                'cat_gender' => $catDetailsMap[$cid]['gender'] ?? pick($app, ['cat_gender', 'gender', 'sex'], ''),
                'cat_image' => $catDetailsMap[$cid]['image'] ?? pick($app, ['cat_image', 'image_path', 'photo_path'], ''),
                'pickup_location' => pick($app, ['pickup_location'], 'College Management Office'),
                'pickup_date' => pick($app, ['pickup_date'], ''),
                'pickup_time' => pick($app, ['pickup_time'], ''),
                'admin_contact' => pick($app, ['admin_contact'], ''),
                'pickup_notes' => pick($app, ['pickup_notes'], ''),
                'pickup_token' => pick($app, ['pickup_token'], ''),
                'pickup_verified' => pick($app, ['pickup_verified'], '0'),
                'picked_up_at' => pick($app, ['picked_up_at'], '')
            ];
        },
        $rawApps
    );

    $foundCount = [];

    $found = array_map(
        function ($report) use ($studentMap, &$foundCount) {
            $sid = (string) pick(
                $report,
                ['student_id', 'user_id', 'reported_by']
            );

            if ($sid !== '') {
                $foundCount[$sid] = ($foundCount[$sid] ?? 0) + 1;
            }

            return [
                'id' => pick($report, ['found_id', 'report_id', 'id']),
                'student' => $studentMap[$sid]['name']
                    ?? pick($report, ['student_name', 'reporter_name', 'nama', 'name'], '-'),
                'location' => pick(
                    $report,
                    ['location', 'found_location', 'place', 'area']
                ),
                'date' => substr((string) pick(
                    $report,
                    ['found_date', 'date_found', 'created_at', 'submitted_at', 'report_date', 'date']
                ), 0, 10),
                'status' => pick($report, ['status', 'report_status'], 'Pending')
            ];
        },
        $rawFound
    );

    $students = array_map(
        function ($student) use ($adoptCount, $foundCount, $rewardGiven) {
            $sid = (string) pick($student, ['student_id', 'id']);

            return [
                'id' => $sid,
                'name' => pick(
                    $student,
                    ['nama', 'name', 'student_name', 'full_name'],
                    '-'
                ),
                'email' => pick($student, ['email', 'student_email'], ''),
                'status' => pick($student, ['status', 'account_status'], 'Active'),
                'adopt_count' => $adoptCount[$sid] ?? 0,
                'found_count' => $foundCount[$sid] ?? 0,
                'reward_given_at' => $rewardGiven[$sid] ?? ''
            ];
        },
        $rawStudents
    );

    return [
        'admin' => $_SESSION['admin_username'] ?? 'Admin',
        'cats' => $cats,
        'applications' => $applications,
        'found' => $found,
        'students' => $students
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // GET: baca data.
    if ($method === 'GET') {
        switch ($action) {
            case 'dashboard':
                respond(true, buildDashboardData($pdo), 'Dashboard berjaya dimuatkan.');

            case 'cats':
                respond(true, getRows($pdo, 'cats'));

            case 'applications':
                respond(true, getRows($pdo, 'adoption_applications'));

            case 'found':
            case 'found_cats':
                respond(true, getRows($pdo, 'found_cats'));

            case 'students':
                respond(true, buildDashboardData($pdo)['students']);

            case 'rewards':
                respond(true, getRows($pdo, 'student_rewards'));

            case 'reports':
                respond(true, [
                    'total_cats' => countRows($pdo, 'cats'),
                    'available_cats' => countByStatus($pdo, 'cats', 'Available'),
                    'adopted_cats' => countByStatus($pdo, 'cats', 'Adopted'),
                    'total_applications' => countRows($pdo, 'adoption_applications'),
                    'pending_applications' => countByStatus($pdo, 'adoption_applications', 'Pending'),
                    'approved_applications' => countByStatus($pdo, 'adoption_applications', 'Approved'),
                    'rejected_applications' => countByStatus($pdo, 'adoption_applications', 'Rejected'),
                    'total_students' => countRows($pdo, 'login_student'),
                    'total_found_reports' => countRows($pdo, 'found_cats')
                ]);

            default:
                respond(false, null, 'Action GET tidak dikenali.', 400);
        }
    }

    // POST: ubah data.
    if ($method === 'POST') {
        // Perlindungan asas CSRF: admin.js sentiasa menghantar header ini.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
            respond(false, null, 'Permintaan tidak sah.', 403);
        }

        $input = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true);

            if (!is_array($json)) {
                respond(false, null, 'Data JSON tidak sah.', 400);
            }

            $input = $json;
        }

        $action = $input['action'] ?? $action;

        switch ($action) {
            case 'add_cat':
                $columns = tableColumns($pdo, 'cats');

                $nameColumn = firstColumn($columns, ['cat_name', 'name', 'catname']);
                $ageColumn = firstColumn($columns, ['age', 'cat_age']);
                $genderColumn = firstColumn($columns, ['gender', 'sex']);
                $descriptionColumn = firstColumn($columns, ['description', 'details', 'cat_description']);
                $statusColumn = firstColumn($columns, ['status']);

                if (!$nameColumn) {
                    respond(false, null, 'Kolum nama kucing tidak ditemui.', 400);
                }

                $name = trim((string) ($input['cat_name'] ?? $input['name'] ?? ''));

                if ($name === '') {
                    respond(false, null, 'Nama kucing wajib diisi.', 400);
                }

                $values = [$nameColumn => $name];

                if ($ageColumn && trim((string) ($input['age'] ?? '')) !== '') {
                    $values[$ageColumn] = trim((string) $input['age']);
                }

                if ($genderColumn && trim((string) ($input['gender'] ?? '')) !== '') {
                    $values[$genderColumn] = trim((string) $input['gender']);
                }

                if ($descriptionColumn) {
                    $values[$descriptionColumn] = trim((string) ($input['description'] ?? ''));
                }

                if ($statusColumn) {
                    // Guna status pilihan admin (Available / Pending / Adopted).
                    $newStatus = trim((string) ($input['status'] ?? 'Available'));

                    $values[$statusColumn] = in_array(
                        strtolower($newStatus),
                        ['available', 'pending', 'adopted'],
                        true
                    ) ? $newStatus : 'Available';
                }

                $names = array_keys($values);
                $quoted = array_map(fn($column) => "`$column`", $names);
                $marks = array_fill(0, count($names), '?');

                $stmt = $pdo->prepare(
                    'INSERT INTO `cats` (' . implode(', ', $quoted) .
                    ') VALUES (' . implode(', ', $marks) . ')'
                );
                $stmt->execute(array_values($values));

                respond(true, ['cat_id' => $pdo->lastInsertId()], 'Kucing berjaya ditambah.');

            case 'update_application':
                $columns = tableColumns($pdo, 'adoption_applications');
                $idColumn = firstColumn($columns, ['application_id', 'id']);
                $statusColumn = firstColumn($columns, ['status', 'application_status']);

                $id = filter_var(
                    $input['application_id'] ?? $input['id'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $status = trim((string) ($input['status'] ?? ''));

                if (!$idColumn || !$statusColumn || !$id) {
                    respond(false, null, 'ID permohonan atau kolum status tidak sah.', 400);
                }

                if (!in_array(strtolower($status), ['pending', 'approved', 'rejected'], true)) {
                    respond(false, null, 'Status permohonan tidak sah.', 400);
                }

                $stmt = $pdo->prepare(
                    "UPDATE `adoption_applications` SET `$statusColumn` = ? WHERE `$idColumn` = ?"
                );
                $stmt->execute([$status, $id]);

                respond(true, ['updated' => $stmt->rowCount()], 'Status permohonan dikemas kini.');

            case 'save_pickup_details':
                ensurePickupColumns($pdo);
                $columns = tableColumns($pdo, 'adoption_applications');
                $idColumn = firstColumn($columns, ['application_id', 'id']);
                $statusColumn = firstColumn($columns, ['status', 'application_status']);
                $requiredPickupColumns = [
                    'pickup_location', 'pickup_date', 'pickup_time',
                    'admin_contact', 'pickup_notes'
                ];
                $missing = array_values(array_diff($requiredPickupColumns, $columns));

                if (!$idColumn || !$statusColumn) {
                    respond(false, null, 'Kolum ID atau status permohonan tidak ditemui.', 500);
                }
                if ($missing) {
                    respond(false, ['missing_columns' => $missing],
                        'Kolum pickup belum lengkap. Tambah kolum yang dinyatakan dalam README.', 500);
                }

                $id = filter_var($input['application_id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) {
                    respond(false, null, 'ID permohonan tidak sah.', 400);
                }

                $stmt = $pdo->prepare("SELECT `$statusColumn` FROM `adoption_applications` WHERE `$idColumn` = ? LIMIT 1");
                $stmt->execute([$id]);
                $currentStatus = $stmt->fetchColumn();
                if ($currentStatus === false) {
                    respond(false, null, 'Permohonan tidak ditemui.', 404);
                }
                if (strtolower((string)$currentStatus) !== 'approved') {
                    respond(false, null, 'Maklumat pickup hanya boleh ditetapkan untuk permohonan Approved.', 400);
                }

                $location = trim((string)($input['pickup_location'] ?? 'College Management Office'));
                $date = trim((string)($input['pickup_date'] ?? ''));
                $time = trim((string)($input['pickup_time'] ?? ''));
                $contact = trim((string)($input['admin_contact'] ?? ''));
                $notes = trim((string)($input['pickup_notes'] ?? ''));

                if ($location === '' || $date === '' || $time === '') {
                    respond(false, null, 'Pickup location, date dan time wajib diisi.', 400);
                }
                $dateObject = DateTime::createFromFormat('Y-m-d', $date);
                if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
                    respond(false, null, 'Format pickup date tidak sah.', 400);
                }
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                    respond(false, null, 'Format pickup time tidak sah.', 400);
                }

                $stmt = $pdo->prepare(
                    'UPDATE `adoption_applications` SET `pickup_location` = ?, `pickup_date` = ?, `pickup_time` = ?, `admin_contact` = ?, `pickup_notes` = ? WHERE `' . $idColumn . '` = ?'
                );
                $stmt->execute([$location, $date, $time, $contact, $notes, $id]);
                respond(true, ['application_id' => $id], 'Pickup details berjaya disimpan.');

            case 'mark_picked_up':
                ensurePickupColumns($pdo);
                $columns = tableColumns($pdo, 'adoption_applications');
                $idColumn = firstColumn($columns, ['application_id', 'id']);
                $statusColumn = firstColumn($columns, ['status', 'application_status']);

                if (!$idColumn || !$statusColumn || !in_array('picked_up_at', $columns, true)) {
                    respond(false, null, 'Kolum picked_up_at tidak dapat disediakan. Semak kebenaran ALTER TABLE.', 500);
                }

                $id = filter_var($input['application_id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) {
                    respond(false, null, 'ID permohonan tidak sah.', 400);
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT * FROM `adoption_applications` WHERE `$idColumn` = ? FOR UPDATE"
                );
                $stmt->execute([$id]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$application) {
                    $pdo->rollBack();
                    respond(false, null, 'Permohonan tidak ditemui.', 404);
                }
                if (strtolower((string) $application[$statusColumn]) !== 'approved') {
                    $pdo->rollBack();
                    respond(false, null, 'Hanya permohonan Approved boleh ditanda sebagai Picked Up.', 400);
                }
                if (empty($application['pickup_date']) || empty($application['pickup_time'])) {
                    $pdo->rollBack();
                    respond(false, null, 'Sila tetapkan maklumat pickup dahulu.', 400);
                }
                if (!empty($application['picked_up_at'])) {
                    $pdo->rollBack();
                    respond(false, null, 'Permohonan ini sudah ditanda sebagai Picked Up.', 400);
                }

                $stmt = $pdo->prepare(
                    "UPDATE `adoption_applications` SET `picked_up_at` = NOW() WHERE `$idColumn` = ?"
                );
                $stmt->execute([$id]);

                // Kucing yang telah diambil ditandakan sebagai Adopted.
                $catColumns = tableColumns($pdo, 'cats');
                $catIdColumn = firstColumn($catColumns, ['cat_id', 'id']);
                $catStatusColumn = firstColumn($catColumns, ['status']);
                $catId = pick($application, ['cat_id'], '');

                if ($catIdColumn && $catStatusColumn && $catId !== '') {
                    $stmt = $pdo->prepare(
                        "UPDATE `cats` SET `$catStatusColumn` = 'Adopted' WHERE `$catIdColumn` = ?"
                    );
                    $stmt->execute([$catId]);
                }

                $pdo->commit();
                respond(true, ['application_id' => $id], 'Kucing berjaya ditanda sebagai Picked Up.');

            case 'mark_reward_given':
                ensureRewardTable($pdo);

                $studentId = filter_var($input['student_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$studentId) {
                    respond(false, null, 'ID pelajar tidak sah.', 400);
                }

                // Semak pelajar benar-benar layak (cukup laporan found cat).
                $foundColumns = tableColumns($pdo, 'found_cats');
                $foundStudentColumn = firstColumn(
                    $foundColumns,
                    ['student_id', 'user_id', 'reported_by']
                );

                if (!$foundStudentColumn) {
                    respond(false, null, 'Kolum ID pelajar dalam found_cats tidak ditemui.', 500);
                }

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM `found_cats` WHERE `$foundStudentColumn` = ?"
                );
                $stmt->execute([$studentId]);

                if ((int) $stmt->fetchColumn() < REWARD_MIN_FOUND) {
                    respond(
                        false,
                        null,
                        'Pelajar ini belum cukup ' . REWARD_MIN_FOUND . ' laporan untuk hadiah.',
                        400
                    );
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO `reward_given` (`student_id`, `given_at`)
                     VALUES (?, NOW())
                     ON DUPLICATE KEY UPDATE `given_at` = `given_at`'
                );
                $stmt->execute([$studentId]);

                respond(true, ['student_id' => $studentId], 'Hadiah ditanda sebagai telah diberi.');

            case 'update_cat':
                $columns = tableColumns($pdo, 'cats');
                $idColumn = firstColumn($columns, ['cat_id', 'id']);
                $statusColumn = firstColumn($columns, ['status']);

                $id = filter_var($input['cat_id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
                $status = trim((string) ($input['status'] ?? ''));

                if (!$idColumn || !$statusColumn || !$id) {
                    respond(false, null, 'ID atau kolum status kucing tidak sah.', 400);
                }

                if (!in_array(strtolower($status), ['available', 'pending', 'reserved', 'adopted'], true)) {
                    respond(false, null, 'Status kucing tidak sah.', 400);
                }

                $stmt = $pdo->prepare(
                    "UPDATE `cats` SET `$statusColumn` = ? WHERE `$idColumn` = ?"
                );
                $stmt->execute([$status, $id]);

                respond(true, ['updated' => $stmt->rowCount()], 'Status kucing dikemas kini.');

            case 'update_found':
                $foundColumns = tableColumns($pdo, 'found_cats');
                $catColumns = tableColumns($pdo, 'cats');

                $foundIdColumn = firstColumn($foundColumns, ['found_id', 'report_id', 'id']);
                $foundStatusColumn = firstColumn($foundColumns, ['status', 'report_status']);
                $catIdColumn = firstColumn($catColumns, ['cat_id', 'id']);
                $catNameColumn = firstColumn($catColumns, ['cat_name', 'name', 'catname']);
                $catStatusColumn = firstColumn($catColumns, ['status']);
                $sourceColumn = firstColumn($catColumns, ['found_cat_id']);

                $id = filter_var(
                    $input['found_id'] ?? $input['report_id'] ?? $input['id'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $status = trim((string) ($input['status'] ?? ''));

                if (!$foundIdColumn || !$foundStatusColumn || !$id) {
                    respond(false, null, 'ID atau kolum status laporan tidak sah.', 400);
                }

                if (!in_array(strtolower($status), ['pending', 'resolved', 'rejected'], true)) {
                    respond(false, null, 'Status laporan tidak sah.', 400);
                }

                // Status selain Resolved hanya mengemas kini laporan.
                if (strtolower($status) !== 'resolved') {
                    $stmt = $pdo->prepare(
                        "UPDATE `found_cats` SET `$foundStatusColumn` = ? WHERE `$foundIdColumn` = ?"
                    );
                    $stmt->execute([$status, $id]);

                    respond(true, ['updated' => $stmt->rowCount(), 'cat_created' => false],
                        'Status laporan dikemas kini.');
                }

                if (!$catIdColumn || !$catNameColumn || !$catStatusColumn || !$sourceColumn) {
                    respond(
                        false,
                        null,
                        'Kolum cats.found_cat_id belum tersedia. Jalankan arahan SQL yang diberikan dahulu.',
                        500
                    );
                }

                $pdo->beginTransaction();

                // Kunci laporan supaya proses tidak dijalankan serentak.
                $stmt = $pdo->prepare(
                    "SELECT * FROM `found_cats` WHERE `$foundIdColumn` = ? FOR UPDATE"
                );
                $stmt->execute([$id]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$report) {
                    $pdo->rollBack();
                    respond(false, null, 'Laporan kucing tidak ditemui.', 404);
                }

                $catName = trim((string) pick(
                    $report,
                    ['cat_name', 'name', 'catname'],
                    ''
                ));

                // Jika laporan tiada nama kucing, guna nama sementara
                // supaya admin masih boleh Resolve (nama boleh diubah kemudian).
                if ($catName === '') {
                    $catName = 'Found Cat #' . $id;
                }

                // Tandakan laporan sebagai Resolved.
                $stmt = $pdo->prepare(
                    "UPDATE `found_cats` SET `$foundStatusColumn` = ? WHERE `$foundIdColumn` = ?"
                );
                $stmt->execute(['Resolved', $id]);

                // Semak sama ada laporan ini sudah diimport ke cats.
                $stmt = $pdo->prepare(
                    "SELECT `$catIdColumn` FROM `cats` WHERE `$sourceColumn` = ? LIMIT 1"
                );
                $stmt->execute([$id]);
                $existingCat = $stmt->fetchColumn();

                $catCreated = false;

                if (!$existingCat) {
                    $values = [
                        $catNameColumn => $catName,
                        $catStatusColumn => 'Available',
                        $sourceColumn => $id
                    ];

                    $descriptionColumn = firstColumn(
                        $catColumns,
                        ['description', 'details', 'cat_description']
                    );
                    $photoColumn = firstColumn(
                        $catColumns,
                        ['photo_path', 'image_path', 'image', 'cat_image', 'photo']
                    );
                    $ageColumn = firstColumn($catColumns, ['age', 'cat_age']);
                    $genderColumn = firstColumn($catColumns, ['gender', 'sex']);

                    if ($descriptionColumn) {
                        $values[$descriptionColumn] = pick(
                            $report,
                            ['description', 'details', 'cat_description'],
                            ''
                        );
                    }

                    if ($photoColumn) {
                        $values[$photoColumn] = pick(
                            $report,
                            ['photo_path', 'image_path', 'image', 'photo'],
                            ''
                        );
                    }

                    // Isi medan pilihan jika jadual cats memerlukannya.
                    if ($ageColumn) {
                        $values[$ageColumn] = pick($report, ['age', 'cat_age'], 'Unknown');
                    }

                    if ($genderColumn) {
                        $values[$genderColumn] = pick($report, ['gender', 'sex'], 'Unknown');
                    }

                    $names = array_keys($values);
                    $quoted = array_map(fn($column) => "`$column`", $names);
                    $marks = array_fill(0, count($names), '?');

                    $sql = 'INSERT INTO `cats` (' . implode(', ', $quoted) .
                        ') VALUES (' . implode(', ', $marks) . ')';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($values));

                    $catCreated = true;
                }

                $pdo->commit();

                respond(
                    true,
                    [
                        'found_id' => $id,
                        'cat_created' => $catCreated,
                        'cat_name' => $catName
                    ],
                    $catCreated
                        ? 'Laporan diselesaikan dan kucing berjaya ditambah ke halaman adoption.'
                        : 'Laporan diselesaikan. Kucing ini sudah wujud dalam senarai adoption.'
                );

            default:
                respond(false, null, 'Action POST tidak dikenali.', 400);
        }
    }

    respond(false, null, 'Kaedah permintaan tidak disokong.', 405);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Purrfect Home Admin API PDO error: ' . $e->getMessage());

    respond(
        false,
        null,
        'Ralat database. Semak struktur jadual cats dan found_cats dalam phpMyAdmin.',
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Purrfect Home Admin API error: ' . $e->getMessage());

    respond(false, null, 'Ralat semasa memproses permintaan.', 500);
}