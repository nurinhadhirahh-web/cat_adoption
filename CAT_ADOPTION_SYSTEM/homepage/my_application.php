
<?php
session_start();
require_once __DIR__ . '/../db.php';

$pdo = $pdo ?? $conn ?? null;

if (!$pdo instanceof PDO) {
    exit('Database connection error. Please check db.php.');
}

if (
    !isset($_SESSION['student_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    exit('Please log in as a student to view your applications.');
}

$studentId = (int) $_SESSION['student_id'];
$applications = [];
$error = '';

/* Google Maps pickup location */
$googleMapsUrl = 'https://maps.app.goo.gl/ouQ2P3LA6Zz6iiwH9';
$pickupLocationName = 'Cat Management Office.';

/* Link asas untuk QR (auto ikut alamat yang awak guna untuk buka website) */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$rootDir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$rootDir = implode('/', array_map('rawurlencode', explode('/', $rootDir)));
$adminFolder = 'adminpage'; // tukar kalau nama folder admin awak lain
$verifyBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $rootDir . '/' . rawurlencode($adminFolder) . '/verify_pickup.php';

try {
    $sql = "SELECT
                aa.id,
                aa.cat_id,
                aa.reason,
                aa.status,
                aa.applied_at,
                aa.pickup_location,
                aa.pickup_date,
                aa.pickup_time,
                aa.admin_contact,
                aa.pickup_notes,
                aa.pickup_token,
                aa.pickup_verified,
                c.cat_name,
                c.age,
                c.gender,
                c.image_path,
                c.status AS cat_status
            FROM adoption_applications aa
            LEFT JOIN cats c ON c.cat_id = aa.cat_id
            WHERE aa.student_id = ?
            ORDER BY aa.applied_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Generate a QR token for approved applications that do not have one */
    foreach ($applications as &$app) {
        if (
            strtolower(trim((string) ($app['status'] ?? ''))) === 'approved'
            && empty($app['pickup_token'])
        ) {
            $token = bin2hex(random_bytes(24));

            $update = $pdo->prepare(
                "UPDATE adoption_applications
                 SET pickup_token = ?
                 WHERE id = ?
                   AND student_id = ?
                   AND (pickup_token IS NULL OR pickup_token = '')"
            );

            $update->execute([
                $token,
                (int) $app['id'],
                $studentId
            ]);

            $read = $pdo->prepare(
                "SELECT pickup_token
                 FROM adoption_applications
                 WHERE id = ? AND student_id = ?"
            );

            $read->execute([
                (int) $app['id'],
                $studentId
            ]);

            $app['pickup_token'] = $read->fetchColumn();
        }
    }
    unset($app);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load applications. Please check the database query.';
}

function safeOutput($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDate($value, string $format = 'd M Y, h:i A'): string
{
    if (empty($value)) {
        return 'Not available';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp === false
        ? 'Not available'
        : date($format, $timestamp);
}

$totalApplications = count($applications);
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($applications as $app) {
    $status = strtolower(trim((string) ($app['status'] ?? 'pending')));

    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } elseif ($status !== 'cancelled') {
        $pendingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Applications | Purrfect Home</title>

<style>
:root {
    --pink: #d94f85;
    --pink-dark: #bd386d;
    --pink-soft: #fff1f6;
    --pink-border: #f8cddd;
    --text: #432b3b;
    --muted: #806b7b;
    --green: #159653;
    --shadow: 0 8px 25px rgba(180,70,115,.07);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background: #fffafd;
}

a {
    color: inherit;
}

.topbar {
    position: sticky;
    top: 0;
    z-index: 10;
    min-height: 76px;
    padding: 12px max(20px, calc((100% - 1350px)/2));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    background: #fffafd;
    border-bottom: 1px solid #f5dce6;
    box-shadow: 0 3px 14px rgba(180,85,120,.05);
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #f37cab, #cf4079);
    border-radius: 15px;
    color: #fff;
    font-size: 24px;
}

.brand-name {
    display: block;
    color: #c95683;
    font-weight: 800;
    font-size: 18px;
}

.brand-tagline {
    display: block;
    color: #b78098;
    font-size: 11px;
    margin-top: 3px;
}

.top-nav {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}

.top-nav a {
    padding: 9px 11px;
    text-decoration: none;
    color: #706574;
    font-size: 12px;
    font-weight: 650;
    border-radius: 10px;
}

.top-nav a:hover,
.top-nav a.active {
    background: #fde8f0;
    color: var(--pink-dark);
}

.top-nav a.logout {
    background: var(--pink);
    color: #fff;
    border-radius: 22px;
    padding-inline: 15px;
}

.page {
    width: min(1450px, 94%);
    margin: 30px auto 45px;
}

.hero {
    position: relative;
    overflow: hidden;
    padding: 26px 28px 22px;
    border: 1px solid var(--pink-border);
    border-radius: 22px;
    background: linear-gradient(120deg, #fff6fa, #fffafd);
    margin-bottom: 20px;
}

.hero:after {
    content: "";
    position: absolute;
    right: 25px;
    top: 12px;
    width: 110px;
    height: 110px;
    background-image: url("cat_header.jpg");
    background-size: cover;
    background-position: center 35%;
    background-repeat: no-repeat;
    border-radius: 50%;
    border: 3px solid #f8cddd;
    box-shadow: 0 4px 12px rgba(217,79,133,.15);
}

.hero h1 {
    margin: 0;
    font-size: clamp(29px, 3.5vw, 42px);
    letter-spacing: -1px;
}

.hero h1 span {
    color: var(--pink);
}

.hero p {
    margin: 8px 80px 0 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.5;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 17px 19px;
    background: #fff;
    border: 1px solid var(--pink-border);
    border-radius: 16px;
    box-shadow: var(--shadow);
}

.summary-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 50%;
    background: #ffe5ef;
    color: var(--pink);
    font-size: 24px;
    font-weight: 800;
}

.pending-card .summary-icon {
    background: #fff2d2;
    color: #b17a10;
}

.approved-card .summary-icon {
    background: #def7e8;
    color: #2cb878;
}

.rejected-card .summary-icon {
    background: #ffe5ec;
    color: #e63f79;
}

.summary-label {
    display: block;
    color: var(--muted);
    font-size: 13px;
}

.summary-number {
    display: block;
    margin-top: 3px;
    font-size: 27px;
    line-height: 1.1;
}

.table-panel {
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--pink-border);
    border-radius: 19px;
    box-shadow: var(--shadow);
}

.panel-heading {
    padding: 20px 24px;
    border-bottom: 1px solid #f4dce6;
}

.panel-heading h2 {
    margin: 0;
    font-size: 20px;
}

.panel-heading p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
    text-align: left;
}

thead {
    background: #fff0f6;
}

th {
    padding: 14px 18px;
    color: #8a6077;
    font-size: 11px;
    letter-spacing: .6px;
    text-transform: uppercase;
}

td {
    padding: 16px 18px;
    border-top: 1px solid #f5e3eb;
    vertical-align: middle;
    font-size: 13px;
}

.application-id {
    color: var(--pink);
    font-weight: 800;
}

.cat-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 200px;
}

.cat-photo {
    width: 62px;
    height: 62px;
    flex-shrink: 0;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid #f5d6e2;
    background: #fff3f8;
}

.cat-photo-placeholder {
    display: grid;
    place-items: center;
    font-size: 28px;
}

.cat-name {
    display: block;
    color: #d83e7d;
    font-size: 15px;
    font-weight: 800;
}

.cat-subtext {
    display: block;
    margin-top: 3px;
    color: var(--muted);
    font-size: 12px;
}

.date-cell {
    white-space: nowrap;
    color: #625469;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 22px;
    font-size: 12px;
    font-weight: 750;
    white-space: nowrap;
}

.status:before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}

.status.pending {
    color: #a36b00;
    background: #fff2cf;
}

.status.approved {
    color: #11864c;
    background: #dcf7e7;
}

.status.rejected {
    color: #c52c58;
    background: #ffe4eb;
}

.status.other {
    color: #706579;
    background: #f0edf5;
}

.expand-cell {
    text-align: right;
    width: 45px;
}

.approved-details-row td {
    padding: 0 18px 18px;
    background: #fff;
}

.approved-layout {
    display: grid;
    grid-template-columns:
        minmax(220px, .9fr)
        minmax(260px, 1.15fr)
        minmax(230px, 1fr);
    gap: 14px;
    padding: 14px;
    border: 1px solid #f5bfd4;
    border-radius: 16px;
    background: linear-gradient(135deg, #fff8fb, #fff);
    align-items: stretch;
}

.detail-card {
    min-width: 0;
    padding: 16px;
    border: 1px solid #f8d8e5;
    border-radius: 14px;
    background: #fffafd;
}

.detail-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 800;
}

.detail-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 50%;
    background: #ffe3ef;
    color: var(--pink);
    font-size: 18px;
}

.detail-caption {
    margin: -5px 0 12px 44px;
    color: var(--muted);
    font-size: 11px;
}

.qr-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 160px;
    padding: 15px;
    text-align: center;
    background: #fff;
    border: 1px dashed #ee9fbd;
    border-radius: 12px;
}

.qr-placeholder > div[id^="qr-"] {
    display: flex;
    justify-content: center;
    align-items: center;
    max-width: 100%;
}

.qr-placeholder canvas,
.qr-placeholder img {
    display: block;
    max-width: 100%;
    height: auto;
}

.qr-placeholder strong {
    margin-top: 8px;
    color: #a35d7a;
    font-size: 13px;
}

.qr-placeholder p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.save-qr-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    margin-top: 14px;
    padding: 13px 15px;
    background: linear-gradient(135deg, #f56fa5, #d93679);
    color: #fff;
    border: 0;
    border-radius: 12px;
    font-family: "Segoe UI", Arial, sans-serif;
    font-size: 13px;
    font-weight: 750;
    box-shadow: 0 5px 14px rgba(217,79,133,.25);
    cursor: pointer;
    transition: .2s ease;
}

.save-qr-button:hover {
    background: linear-gradient(135deg, #e84d8c, #bd2868);
    box-shadow: 0 8px 20px rgba(217,79,133,.3);
    transform: translateY(-2px);
}

.save-qr-button:focus-visible {
    outline: 3px solid #f7a9c8;
    outline-offset: 3px;
}

.info-list {
    display: grid;
    gap: 0;
    padding: 0 11px;
    background: #fff2f7;
    border: 1px solid #fbe0ea;
    border-radius: 11px;
}

.info-item {
    display: grid;
    grid-template-columns: 27px 1fr;
    gap: 8px;
    padding: 11px 0;
    border-bottom: 1px solid #f5d9e5;
}

.info-item:last-child {
    border-bottom: 0;
}

.info-item .mini-icon {
    color: var(--pink);
    font-size: 17px;
}

.info-item small {
    display: block;
    color: #998399;
    font-size: 11px;
    margin-bottom: 3px;
}

.info-item strong {
    display: block;
    font-size: 12px;
    font-weight: 650;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.approved-message {
    display: flex;
    gap: 10px;
    margin-top: 13px;
    padding: 12px;
    border-radius: 11px;
    background: #e8f9ef;
    color: #187d48;
    border: 1px solid #d2f0de;
}

.approved-message .check {
    font-size: 23px;
}

.approved-message strong {
    display: block;
    font-size: 13px;
}

.approved-message p {
    margin: 4px 0 0;
    font-size: 11px;
    line-height: 1.5;
}

.location-card {
    display: flex;
    flex-direction: column;
}

.location-description {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin: 8px 0 12px;
}

.location-map-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: #ffe5ef;
    color: var(--pink);
    font-size: 22px;
}

.location-description strong {
    display: block;
    font-size: 13px;
    line-height: 1.5;
}

.location-description p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.map-illustration {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    min-height: 130px;
    margin: 5px 0 14px;
    overflow: hidden;
    border: 1px solid #f8d8e5;
    border-radius: 12px;
    background:
        linear-gradient(30deg, transparent 46%, #f4cbdc 47%, #f4cbdc 50%, transparent 51%),
        linear-gradient(120deg, transparent 43%, #f4cbdc 44%, #f4cbdc 47%, transparent 48%),
        linear-gradient(135deg, #fff2f7, #fffafd);
}

.map-illustration .map-symbol {
    font-size: 66px;
    opacity: .8;
}

.map-illustration .map-pin {
    position: absolute;
    top: 17px;
    left: 50%;
    font-size: 32px;
    transform: translateX(-50%);
}

.location-button {
    display: block;
    width: 100%;
    margin-top: auto;
    padding: 13px 12px;
    border: 0;
    border-radius: 25px;
    text-align: center;
    text-decoration: none;
    background: linear-gradient(100deg, #e84689, #cf3374);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 5px 13px rgba(217,79,133,.2);
    transition: transform .2s ease, box-shadow .2s ease;
}

.location-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 17px rgba(217,79,133,.3);
}

.empty-state,
.error-state {
    padding: 45px 20px;
    text-align: center;
    color: var(--muted);
}

.empty-state h3 {
    color: var(--text);
    margin-bottom: 7px;
}

.browse-link {
    display: inline-block;
    margin-top: 8px;
    padding: 11px 17px;
    background: var(--pink);
    color: #fff;
    text-decoration: none;
    border-radius: 22px;
    font-size: 13px;
    font-weight: 750;
}

.footer {
    text-align: center;
    color: #a38a98;
    font-size: 12px;
    padding: 20px;
}

@media (max-width: 1050px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .approved-layout {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .location-card {
        grid-column: 1 / -1;
    }

    .map-illustration {
        min-height: 110px;
    }
}

@media (max-width: 650px) {
    .topbar {
        padding: 12px 15px;
    }

    .page {
        width: calc(100% - 22px);
        margin-top: 18px;
    }

    .hero {
        padding: 22px 18px;
    }

    .hero:after {
        width: 65px;
        height: 65px;
        right: 12px;
        top: 12px;
    }

    .hero p {
        margin-right: 45px;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .summary-card {
        padding: 13px 11px;
        gap: 9px;
    }

    .summary-icon {
        width: 38px;
        height: 38px;
        font-size: 20px;
    }

    .summary-number {
        font-size: 23px;
    }

    .approved-layout {
        grid-template-columns: minmax(0, 1fr);
        padding: 8px;
        gap: 10px;
    }

    .approved-layout > .detail-card {
        padding: 14px;
    }

    .location-card {
        grid-column: auto;
    }

    .approved-details-row td {
        padding: 0 8px 12px;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<body>

<header class="topbar">
    <a class="brand" href="homepage.html">
        <span class="brand-mark">🐾</span>
        <span>
            <span class="brand-name">Purrfect Home</span>
            <span class="brand-tagline">Adopt ♥ Rescue ♥ Give Love</span>
        </span>
    </a>

    <nav class="top-nav" aria-label="Main navigation">
        <a href="homepage.html">Home</a>
        <a href="adopt_cat.php">Adopt a Cat</a>
        <a href="found_cat.php">Found Cats</a>
        <a href="about_us.php">About Us</a>
        <a class="active" href="my_application.php">My Applications</a>
        <a class="logout"
           href="../login%20page/logout.php"
           onclick="return confirm('Are you sure you want to log out?');">
            ⇥ Log Out
        </a>
    </nav>
</header>

<main class="page">

    <section class="hero">
        <h1>🐾 My <span>Applications</span></h1>
        <p>Keep track of your adoption requests and check the latest status.</p>
    </section>

    <section class="summary-grid" aria-label="Application summary">
        <article class="summary-card">
            <span class="summary-icon">▤</span>
            <div>
                <span class="summary-label">Total</span>
                <strong class="summary-number"><?= $totalApplications ?></strong>
            </div>
        </article>

        <article class="summary-card pending-card">
            <span class="summary-icon">◷</span>
            <div>
                <span class="summary-label">Pending</span>
                <strong class="summary-number"><?= $pendingCount ?></strong>
            </div>
        </article>

        <article class="summary-card approved-card">
            <span class="summary-icon">✓</span>
            <div>
                <span class="summary-label">Approved</span>
                <strong class="summary-number"><?= $approvedCount ?></strong>
            </div>
        </article>

        <article class="summary-card rejected-card">
            <span class="summary-icon">×</span>
            <div>
                <span class="summary-label">Rejected</span>
                <strong class="summary-number"><?= $rejectedCount ?></strong>
            </div>
        </article>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <h2>Application Records</h2>
            <p>Details of the adoption requests you have submitted.</p>
        </div>

        <?php if ($error !== ''): ?>

            <div class="error-state"><?= safeOutput($error) ?></div>

        <?php elseif ($totalApplications === 0): ?>

            <div class="empty-state">
                <div style="font-size:42px">🐾</div>
                <h3>No Applications Yet</h3>
                <p>Your submitted adoption requests will appear here.</p>
                <a class="browse-link" href="adopt_cat.php">Browse Available Cats</a>
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cat Details</th>
                            <th>Date Applied</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($applications as $app):

                        $rawStatus = strtolower(
                            trim((string) ($app['status'] ?? 'pending'))
                        );

                        $statusClass = in_array(
                            $rawStatus,
                            ['pending', 'approved', 'rejected'],
                            true
                        ) ? $rawStatus : 'other';

                        $catName = trim((string) ($app['cat_name'] ?? ''));

                        if ($catName === '') {
                            $catName = 'Cat #' . (string) ($app['cat_id'] ?? '');
                        }

                        $imagePath = trim((string) ($app['image_path'] ?? ''));
                        $detailsId = 'application-details-' . (int) $app['id'];
                        $isApproved = $rawStatus === 'approved';

                        $pickupLocation = trim(
                            (string) ($app['pickup_location'] ?? '')
                        );

                        $pickupDate = $app['pickup_date'] ?? '';
                        $pickupTime = $app['pickup_time'] ?? '';

                        $adminContact = trim(
                            (string) ($app['admin_contact'] ?? '')
                        );

                        $pickupNotes = trim(
                            (string) ($app['pickup_notes'] ?? '')
                        );
                    ?>

                        <!-- Main application row -->
                        <tr>
                            <td>
                                <span class="application-id">
                                    #<?= safeOutput($app['id']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="cat-info">
                                    <?php if ($imagePath !== ''): ?>
                                        <img
                                            class="cat-photo"
                                            src="<?= safeOutput($imagePath) ?>"
                                            alt="<?= safeOutput($catName) ?>"
                                            onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">

                                        <span
                                            class="cat-photo cat-photo-placeholder"
                                            style="display:none">🐱</span>
                                    <?php else: ?>
                                        <span class="cat-photo cat-photo-placeholder">🐱</span>
                                    <?php endif; ?>

                                    <span>
                                        <span class="cat-name">
                                            <?= safeOutput($catName) ?>
                                        </span>

                                        <span class="cat-subtext">
                                            <?= safeOutput($app['gender'] ?? 'Gender unavailable') ?>
                                            <?= !empty($app['age']) ? ' · ' . safeOutput($app['age']) : '' ?>
                                        </span>
                                    </span>
                                </div>
                            </td>

                            <td class="date-cell">
                                <?= safeOutput(formatDate($app['applied_at'] ?? null)) ?>
                            </td>

                            <td>
                                <span class="status <?= safeOutput($statusClass) ?>">
                                    <?= safeOutput(ucfirst($rawStatus)) ?>
                                </span>
                            </td>

                            <td class="expand-cell">
                                <?php if ($isApproved): ?>
                                    <span style="color:var(--green);font-weight:bold;">✓</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Exactly ONE details row for each approved application -->
                        <?php if ($isApproved): ?>
                        <tr
                            class="approved-details-row"
                            id="<?= safeOutput($detailsId) ?>">

                            <td colspan="5">
                                <div class="approved-layout">

                                    <!-- LEFT: QR VERIFICATION -->
                                    <section class="detail-card qr-card">
                                        <h3 class="detail-title">
                                            <span class="detail-icon">🛡</span>
                                            Pickup Verification
                                        </h3>

                                        <p class="detail-caption">
                                            Show this QR code to admin during pickup.
                                        </p>

                                        <?php if (!empty($app['pickup_token'])): ?>

                                            <div class="qr-placeholder">
                                                <div
                                                    id="qr-<?= (int) $app['id'] ?>"
                                                    data-payload="<?= safeOutput(
    $verifyBaseUrl . '?token=' . urlencode($app['pickup_token'])
) ?>">
                                                </div>

                                                <strong>Pickup QR Code</strong>
                                                <p>Show this QR code to the admin during pickup.</p>
                                            </div>

                                            <button
                                                class="save-qr-button"
                                                type="button"
                                                onclick="saveQr(<?= (int) $app['id'] ?>)">
                                                <span class="download-icon">↓</span>
                                                <span>Save QR Code (PNG)</span>
                                            </button>

                                        <?php else: ?>

                                            <p>QR code is not available yet.</p>

                                        <?php endif; ?>
                                    </section>

                                    <!-- CENTRE: PICKUP INFORMATION -->
                                    <section class="detail-card info-card">
                                        <h3 class="detail-title">
                                            <span class="detail-icon">▦</span>
                                            Pickup Information
                                        </h3>

                                        <div class="info-list">

                                            <div class="info-item">
                                                <span class="mini-icon">📍</span>
                                                <div>
                                                    <small>Location</small>
                                                    <strong>
                                                        <?= safeOutput(
                                                            $pickupLocation !== ''
                                                                ? $pickupLocation
                                                                : $pickupLocationName
                                                        ) ?>
                                                    </strong>
                                                </div>
                                            </div>

                                            <div class="info-item">
                                                <span class="mini-icon">▦</span>
                                                <div>
                                                    <small>Date &amp; Time</small>
                                                    <strong>
                                                        <?php if (!empty($pickupDate)): ?>
                                                            <?= safeOutput(formatDate($pickupDate, 'd M Y')) ?>
                                                        <?php else: ?>
                                                            To be set by admin
                                                        <?php endif; ?>

                                                        <?php if (!empty($pickupTime)): ?>
                                                            · <?= safeOutput(formatDate($pickupTime, 'h:i A')) ?>
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            </div>

                                            <div class="info-item">
                                                <span class="mini-icon">☎</span>
                                                <div>
                                                    <small>Contact</small>
                                                    <strong>
                                                        <?= safeOutput(
                                                            $adminContact !== ''
                                                                ? $adminContact
                                                                : 'Admin contact details will appear here'
                                                        ) ?>
                                                    </strong>
                                                </div>
                                            </div>

                                            <div class="info-item">
                                                <span class="mini-icon">☷</span>
                                                <div>
                                                    <small>Notes</small>
                                                    <strong>
                                                        <?= safeOutput(
                                                            $pickupNotes !== ''
                                                                ? $pickupNotes
                                                                : 'Bring your student ID and a suitable cat carrier.'
                                                        ) ?>
                                                    </strong>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="approved-message">
                                            <span class="check">✓</span>
                                            <div>
                                                <strong>Application Approved</strong>
                                                <p>Your adoption application has been approved!</p>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- RIGHT: PICKUP LOCATION -->
                                    <section class="detail-card location-card">
                                        <h3 class="detail-title">
                                            <span class="detail-icon">📍</span>
                                            Pickup Location
                                        </h3>

                                        <div class="location-description">
                                            <span class="location-map-icon">◈</span>
                                            <div>
                                                <strong>
                                                    <?= safeOutput(
                                                        $pickupLocation !== ''
                                                            ? $pickupLocation
                                                            : $pickupLocationName
                                                    ) ?>
                                                </strong>
                                                <p>
                                                    Open Google Maps to view the designated pickup location.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="map-illustration" aria-hidden="true">
                                            <span class="map-symbol">🗺️</span>
                                            <span class="map-pin">📍</span>
                                        </div>

                                        <a
                                            class="location-button"
                                            href="<?= safeOutput($googleMapsUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            📍 View Location ↗
                                        </a>
                                    </section>

                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    © 2026 Purrfect Home. Made with
    <span style="color:var(--pink)">♥</span>
    for every cat looking for a loving home.
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-payload]').forEach(function (element) {
        if (typeof QRCode === 'undefined') {
            element.textContent = 'QR library failed to load.';
            return;
        }

        new QRCode(element, {
            text: element.dataset.payload,
            width: 180,
            height: 180,
            correctLevel: QRCode.CorrectLevel.H
        });
    });
});

function saveQr(id) {
    const element = document.getElementById('qr-' + id);

    if (!element) {
        alert('QR code not found.');
        return;
    }

    const canvas = element.querySelector('canvas');
    const image = element.querySelector('img');
    let url = '';

    if (canvas) {
        url = canvas.toDataURL('image/png');
    } else if (image) {
        url = image.src;
    } else {
        alert('Please wait for the QR code to load.');
        return;
    }

    const link = document.createElement('a');
    link.href = url;
    link.download = 'PurrfectHome_QR_' + id + '.png';
    document.body.appendChild(link);
    link.click();
    link.remove();
}
</script>

</body>
</html>
