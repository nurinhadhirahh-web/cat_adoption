<?php
/*
 * Letak fail ini di:
 * C:\xampp\htdocs\CAT_ADOPTION_SYSTEM\adminpage\verify_pickup.php
 */
session_start();
require_once __DIR__ . '/../db.php';

$pdo = $pdo ?? $conn ?? null;

if (!$pdo instanceof PDO) {
    exit('Database connection error. Please check db.php.');
}

/* Admin sahaja. Kalau belum login, hantar ke login dan simpan link ini */
if (($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login%20page/login.php'); // tukar kalau path login admin awak lain
    exit;
}

$token = trim($_GET['token'] ?? '');
$state = 'invalid';   // invalid | success | already
$info  = null;

if ($token !== '') {
    $stmt = $pdo->prepare(
        "SELECT aa.id, aa.student_id, aa.status, aa.pickup_verified, c.cat_name
         FROM adoption_applications aa
         LEFT JOIN cats c ON c.cat_id = aa.cat_id
         WHERE aa.pickup_token = ?"
    );
    $stmt->execute([$token]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($info && strtolower(trim((string) $info['status'])) === 'approved') {
        if ((int) $info['pickup_verified'] === 1) {
            $state = 'already';
        } else {
            /* Auto tandakan sebagai picked up */
            $upd = $pdo->prepare(
                "UPDATE adoption_applications
                 SET pickup_verified = 1
                 WHERE pickup_token = ? AND pickup_verified = 0"
            );
            $upd->execute([$token]);
            $state = $upd->rowCount() ? 'success' : 'already';
        }
    }
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$themes = [
    'success' => ['#159653', '#e8f9ef', '✓ Pickup Verified'],
    'already' => ['#a36b00', '#fff2cf', '⚠ Already Picked Up'],
    'invalid' => ['#c52c58', '#ffe4eb', '✕ Invalid QR Code'],
];
[$color, $bg, $title] = $themes[$state];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Pickup</title>
<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #fffafd;
    margin: 0;
    display: grid;
    place-items: center;
    min-height: 100vh;
    padding: 20px;
}
.card {
    max-width: 420px;
    width: 100%;
    background: #fff;
    border: 1px solid #f8cddd;
    border-radius: 18px;
    padding: 28px;
    text-align: center;
}
.badge {
    background: <?= $bg ?>;
    color: <?= $color ?>;
    padding: 14px;
    border-radius: 12px;
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 18px;
}
.row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f5e3eb;
    font-size: 14px;
}
a {
    display: inline-block;
    margin-top: 20px;
    color: #d94f85;
    font-weight: 700;
}
</style>
</head>
<body>
<div class="card">
    <div class="badge"><?= $title ?></div>

    <?php if ($info && $state !== 'invalid'): ?>
        <div class="row"><span>Application</span><strong>#<?= e($info['id']) ?></strong></div>
        <div class="row"><span>Cat</span><strong><?= e($info['cat_name']) ?></strong></div>
        <div class="row"><span>Student ID</span><strong><?= e($info['student_id']) ?></strong></div>
        <div class="row"><span>Time</span><strong><?= date('d M Y, h:i A') ?></strong></div>
    <?php else: ?>
        <p>QR tidak sah atau permohonan belum diluluskan.</p>
    <?php endif; ?>

    <a href="javascript:history.back()">← Kembali</a>
</div>
</body>
</html>