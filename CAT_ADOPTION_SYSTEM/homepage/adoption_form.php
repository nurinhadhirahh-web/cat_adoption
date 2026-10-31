<?php
session_start();

require_once __DIR__ . '/../db.php';

$studentId = $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? null;

$error = '';
$success = '';
$cat = null;
$catImage = '';

$catId = filter_input(INPUT_GET, 'cat_id', FILTER_VALIDATE_INT);

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
 * Find a local image in the same folder as this file.
 * (Sama seperti logik dalam adopt_cat.php)
 */
function localCatImage(string $path, string $name): string
{
    $path = basename(trim($path));

    if ($path !== '' && is_file(__DIR__ . DIRECTORY_SEPARATOR . $path)) {
        return $path;
    }

    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($name));

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        if (
            $slug !== '' &&
            is_file(__DIR__ . DIRECTORY_SEPARATOR . $slug . '.' . $ext)
        ) {
            return $slug . '.' . $ext;
        }
    }

    return '';
}

/* Fallback images for normal cats (sama seperti adopt_cat.php) */
$catImages = [
    'oyen'  => 'https://radarmukomuko.disway.id/upload/8c522687768aa1dd95c0d06a21a77a35.jpg',
    'simba' => 'https://s3.envato.com/files/4637af80-c90f-4ec7-b4d2-6b23a666b155/inline_image_preview.jpg',
    'luna'  => 'https://radarkuningan.disway.id/upload/7f141ade2e60caf26962f7b9a8610a15.PNG',
    'tom'   => 'https://radarkuningan.disway.id/upload/f42f4d7a2770fb5b5f73ed9b99e8f8bb.jpeg'
];

$defaultCatImage =
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=900&q=85';

/* ============================================================
   LINK DARI KUCING "FOUND": adoption_form.php?source=found&found_id=3
   Cari kucing dalam jadual cats yang berasal dari laporan itu.
============================================================ */
$source  = (string)($_GET['source'] ?? '');
$foundId = filter_input(INPUT_GET, 'found_id', FILTER_VALIDATE_INT);

if (!$catId && $source === 'found' && $foundId) {
    try {
        $lookup = $pdo->prepare(
            "SELECT cat_id
             FROM cats
             WHERE found_cat_id = :found_id
             AND status = 'Available'
             LIMIT 1"
        );

        $lookup->execute(['found_id' => $foundId]);
        $mappedCatId = $lookup->fetchColumn();

        if ($mappedCatId) {
            $catId = (int)$mappedCatId;
        }
    } catch (PDOException $exception) {
        error_log('Adoption found cat lookup error: ' . $exception->getMessage());
    }
}

/* ============================================================
   SUBMIT ADOPTION APPLICATION
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catId = filter_input(INPUT_POST, 'cat_id', FILTER_VALIDATE_INT);
    $reason = trim($_POST['reason'] ?? '');

    if (!$studentId) {
        $error = 'Please log in before submitting an application.';
    } elseif (!$catId || $reason === '') {
        $error = 'Please select a cat and enter your reason.';
    } elseif (strlen($reason) > 2000) {
        $error = 'Your reason must not exceed 2000 characters.';
    } else {
        try {
            $check = $pdo->prepare(
                "SELECT cat_id, cat_name
                 FROM cats
                 WHERE cat_id = :cat_id
                 AND status = 'Available'"
            );

            $check->execute(['cat_id' => $catId]);
            $availableCat = $check->fetch(PDO::FETCH_ASSOC);

            if (!$availableCat) {
                $error = 'Sorry, this cat is no longer available.';
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO adoption_applications
                    (student_id, cat_id, reason, status, applied_at)
                    VALUES (:student_id, :cat_id, :reason, 'Pending', NOW())"
                );

                $insert->execute([
                    'student_id' => $studentId,
                    'cat_id' => $catId,
                    'reason' => $reason
                ]);

                $success = 'Your adoption application has been submitted successfully!';
            }
        } catch (PDOException $exception) {
            error_log('Adoption application error: ' . $exception->getMessage());
            $error = 'Unable to submit your application. Please check your database settings.';
        }
    }
}

/* ============================================================
   GET SELECTED CAT DETAILS (+ PHOTO)
============================================================ */
if ($catId && !$success) {
    try {
        // Cari kolum gambar dalam found_cats (jika ada).
        $imageColumn = null;

        try {
            $foundColumns = $pdo->query(
                "SHOW COLUMNS FROM found_cats"
            )->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('image_path', $foundColumns, true)) {
                $imageColumn = 'image_path';
            } elseif (in_array('photo_path', $foundColumns, true)) {
                $imageColumn = 'photo_path';
            }
        } catch (PDOException $exception) {
            $imageColumn = null;
        }

        $joinImage = $imageColumn ? "f.`$imageColumn`" : 'NULL';

        $stmt = $pdo->prepare(
            "SELECT
                c.cat_id,
                c.cat_name,
                c.age,
                c.gender,
                c.description,
                c.found_cat_id,
                $joinImage AS found_image
             FROM cats c
             LEFT JOIN found_cats f ON f.id = c.found_cat_id
             WHERE c.cat_id = :cat_id
             AND c.status = 'Available'"
        );

        $stmt->execute(['cat_id' => $catId]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cat) {
            $fromFound = !empty($cat['found_cat_id']);

            if ($fromFound) {
                // Gambar kucing found: fail dalam folder yang sama.
                $catImage = localCatImage(
                    (string)($cat['found_image'] ?? ''),
                    (string)$cat['cat_name']
                );
            } else {
                $imageKey = strtolower(trim((string)$cat['cat_name']));
                $catImage = $catImages[$imageKey] ?? $defaultCatImage;
            }
        }
    } catch (PDOException $exception) {
        error_log('Adoption cat details error: ' . $exception->getMessage());
        $error = 'Unable to load cat details. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Adoption Application | Purrfect Home</title>

<style>
:root {
    --pink: #cf5688;
    --pink-dark: #b63d70;
    --pink-light: #fff0f6;
    --pink-border: #f1d9e4;
    --text: #45333d;
    --muted: #8e7b86;
    --white: #ffffff;
    --background: #fff9fc;
    --shadow: 0 10px 35px rgba(109, 58, 82, .045);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background: var(--background);
    line-height: 1.6;
}

a {
    color: inherit;
    text-decoration: none;
}

button,
input,
textarea,
select {
    font: inherit;
}

[hidden] {
    display: none !important;
}

/* ============================================================
   HEADER - MATCH FOUND_CAT.PHP
============================================================ */
.site-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    width: 100%;
    background-color: #fffafd;
    background-image: radial-gradient(
        #f8dce8 .65px,
        transparent .65px
    );
    background-size: 5px 5px;
    border-bottom: 1px solid #f0dce6;
}

.nav-wrap {
    width: min(1200px, calc(100% - 40px));
    min-height: 82px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: linear-gradient(135deg, #f58ab6, #d94382);
    color: white;
    font-size: 24px;
}

.brand-copy {
    display: grid;
    line-height: 1.25;
}

.brand-copy strong {
    color: #bd2868;
    font-size: 17px;
}

.brand-copy small {
    margin-top: 4px;
    color: #c56a91;
    font-size: 10px;
}

.main-nav {
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: flex-end;
    gap: clamp(12px, 1.6vw, 22px);
}

.nav-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 0;
    color: #5d5055;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.nav-link:hover,
.nav-link.active {
    color: #c95683;
}

.profile-link {
    gap: 6px;
    padding: 9px 13px;
    border-radius: 16px;
    background: #fbe4ed;
    color: #c93678;
}

.logout-link {
    min-height: 38px;
    padding: 9px 15px;
    border: 1px solid #d94382;
    border-radius: 20px;
    background: #d94382;
    color: white;
}

.logout-link:hover {
    background: #bd2868;
    color: white;
}

.menu-toggle {
    display: none;
    padding: 8px 12px;
    border: 1px solid var(--pink-border);
    border-radius: 8px;
    background: white;
    color: var(--pink-dark);
    cursor: pointer;
}

/* ============================================================
   PAGE LAYOUT
============================================================ */
.page-shell {
    max-width: 1120px;
    margin: auto;
    padding: 48px 25px 75px;
}

.breadcrumb {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-bottom: 22px;
    color: var(--muted);
    font-size: 12px;
}

.breadcrumb a:hover {
    color: var(--pink);
}

/* ============================================================
   PAGE INTRO
============================================================ */
.page-intro {
    margin-bottom: 26px;
}

.eyebrow {
    display: inline-block;
    margin-bottom: 10px;
    color: var(--pink);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.page-intro h1 {
    margin-bottom: 13px;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(36px, 5vw, 49px);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -1px;
}

.page-intro h1 span {
    color: var(--pink);
}

.page-intro p {
    max-width: 650px;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.8;
}

/* ============================================================
   TWO-COLUMN CONTENT
============================================================ */
.layout {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(260px, .85fr);
    align-items: start;
    gap: 25px;
}

.form-card,
.info-card {
    min-width: 0;
    background: white;
    border: 1px solid var(--pink-border);
    border-radius: 18px;
    box-shadow: var(--shadow);
}

.form-card {
    padding: 30px;
}

.card-heading {
    padding-bottom: 21px;
    margin-bottom: 24px;
    border-bottom: 1px solid #f4e8ee;
}

.card-heading h2 {
    margin-bottom: 5px;
    font-size: 19px;
    font-weight: 750;
}

.card-heading h2 span {
    color: var(--pink);
}

.card-heading p {
    color: var(--muted);
    font-size: 12px;
}

/* ============================================================
   CAT INFORMATION (WITH PHOTO)
============================================================ */
.form-section-title {
    margin: 25px 0 17px;
    color: var(--pink-dark);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.cat-info {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding: 17px;
    margin-bottom: 25px;
    border: 1px solid #f8e4ec;
    border-radius: 12px;
    background: #fff4f8;
}

.cat-photo-box {
    position: relative;
    flex-shrink: 0;
    width: 140px;
    height: 140px;
    overflow: hidden;
    border-radius: 16px;
    background: linear-gradient(135deg, #fde7ef, #f8d1e0);
}

.cat-photo-box img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 25%;
}

.cat-photo-empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    color: #c95683;
    background: linear-gradient(135deg, #fde7ef, #f8d1e0);
}

.cat-photo-empty span {
    font-size: 38px;
    line-height: 1;
}

.cat-photo-empty small {
    font-size: 11px;
}

.cat-info-details {
    flex: 1;
    min-width: 0;
}

.cat-info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 7px;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.cat-info-row:last-child {
    margin-bottom: 0;
}

.cat-info-row strong {
    color: #493640;
}

.cat-info-row span {
    color: #776570;
}

/* ============================================================
   FORM
============================================================ */
.form-group {
    min-width: 0;
    margin-bottom: 21px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 700;
}

.required {
    color: var(--pink);
}

.form-control {
    display: block;
    width: 100%;
    min-height: 45px;
    padding: 12px 13px;
    border: 1px solid #e9dce3;
    border-radius: 9px;
    outline: none;
    background: #fffdfe;
    color: var(--text);
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.form-control::placeholder {
    color: #a897a1;
}

.form-control:focus {
    border-color: #dc7da4;
    box-shadow: 0 0 0 3px rgba(207, 86, 136, .09);
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
    line-height: 1.7;
}

.field-hint {
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
}

/* ============================================================
   BUTTONS
============================================================ */
.button-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.submit-btn {
    display: inline-flex;
    min-height: 47px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 23px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(100deg, #e36a96, #d54f80);
    color: white;
    font-size: 13px;
    font-weight: 750;
    cursor: pointer;
    transition: .2s ease;
}

.submit-btn:hover {
    background: linear-gradient(100deg, #ca4e7b, #b93769);
    transform: translateY(-1px);
}

.back-button {
    display: inline-flex;
    min-height: 45px;
    align-items: center;
    justify-content: center;
    padding: 11px 17px;
    border: 1px solid var(--pink-border);
    border-radius: 10px;
    background: white;
    color: var(--pink-dark);
    font-size: 12px;
    font-weight: 700;
}

.back-button:hover {
    background: var(--pink-light);
}

/* ============================================================
   MESSAGES
============================================================ */
.message {
    padding: 14px 16px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 12px;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.message.success {
    border: 1px solid #cce9d3;
    background: #eaf6ee;
    color: #27663d;
}

.message.error {
    border: 1px solid #f5cbd6;
    background: #fde7eb;
    color: #9c2444;
}

/* ============================================================
   RIGHT INFORMATION PANEL
============================================================ */
.info-card {
    padding: 25px;
    background: linear-gradient(145deg, #fff0f6, #fff8fb);
}

.info-icon {
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    margin-bottom: 18px;
    border-radius: 16px;
    background: white;
    color: var(--pink);
    font-size: 27px;
}

.info-card h2 {
    margin-bottom: 9px;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 23px;
    line-height: 1.45;
}

.info-card > p {
    color: #806d79;
    font-size: 12px;
    line-height: 1.8;
}

.tips-list {
    display: grid;
    gap: 17px;
    margin-top: 24px;
    list-style: none;
}

.tips-list li {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    color: #786673;
    font-size: 12px;
    line-height: 1.7;
}

.tip-number {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 27px;
    height: 27px;
    border-radius: 8px;
    background: white;
    color: var(--pink);
    font-size: 11px;
    font-weight: 800;
}

.tips-list strong {
    display: block;
    margin-bottom: 2px;
    color: #584451;
}

.notice {
    padding: 16px;
    margin-top: 25px;
    border: 1px solid #f4dce7;
    border-radius: 12px;
    background: white;
    color: #806d79;
    font-size: 11px;
    line-height: 1.8;
}

.notice strong {
    display: block;
    margin-bottom: 4px;
    color: var(--pink-dark);
    font-size: 12px;
}

/* ============================================================
   FOOTER
============================================================ */
.site-footer {
    padding: 25px;
    border-top: 1px solid var(--pink-border);
    background: white;
}

.footer-inner {
    display: flex;
    max-width: 1120px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: auto;
    color: var(--muted);
    font-size: 11px;
}

.footer-heart {
    color: var(--pink);
}

/* ============================================================
   RESPONSIVE DESIGN
============================================================ */
@media (max-width: 900px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .info-card {
        order: 2;
    }
}

@media (max-width: 850px) {
    .nav-wrap {
        position: relative;
        flex-wrap: wrap;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    .menu-toggle {
        display: block;
        margin-left: auto;
    }

    .main-nav {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1001;
        display: none;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 8px 16px 14px;
        border: 1px solid #f0dce6;
        background: #fffafd;
        box-shadow: 0 12px 18px rgba(80, 40, 55, .06);
    }

    .main-nav.open {
        display: flex;
    }

    .nav-link {
        justify-content: flex-start;
        padding: 10px 4px;
        white-space: normal;
        border-bottom: 1px solid #f8edf2;
    }

    .profile-link {
        padding: 10px 4px;
        background: transparent;
    }

    .logout-link {
        align-self: flex-start;
        margin-top: 8px;
        padding: 9px 15px;
    }
}

@media (max-width: 560px) {
    .nav-wrap {
        width: calc(100% - 28px);
    }

    .page-shell {
        padding: 32px 16px 50px;
    }

    .page-intro h1 {
        font-size: 36px;
    }

    .form-card,
    .info-card {
        padding: 21px;
    }

    .cat-info {
        flex-direction: column;
        padding: 14px;
    }

    .cat-photo-box {
        width: 100%;
        height: 220px;
    }

    .button-row {
        flex-direction: column;
    }

    .submit-btn,
    .back-button {
        width: 100%;
    }

    .footer-inner {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<!-- HEADER -->
<header class="site-header">
    <div class="nav-wrap">

        <a class="brand" href="homepage.html" aria-label="Purrfect Home homepage">
            <span class="brand-mark" aria-hidden="true">🐾</span>

            <span class="brand-copy">
                <strong>Purrfect Home</strong>
                <small>Adopt ♥ Rescue ♥ Give Love</small>
            </span>
        </a>

        <button
            class="menu-toggle"
            id="menuToggle"
            type="button"
            aria-expanded="false"
            aria-controls="mainNav">
            ☰ Menu
        </button>

        <nav class="main-nav" id="mainNav" aria-label="Main navigation">
            <a class="nav-link" href="homepage.html">Home</a>
            <a class="nav-link active" href="adopt_cat.php">Adopt a Cat</a>
            <a class="nav-link" href="found_cat.php">Found Cats</a>
            <a class="nav-link" href="about_us.php">About Us</a>

            <a class="nav-link profile-link" href="my_application.php">
                My Applications
            </a>

            <a
                class="nav-link logout-link"
                href="../login%20page/logout.php"
                onclick="return confirm('Are you sure you want to log out?');">
                Log Out
            </a>
        </nav>

    </div>
</header>

<main class="page-shell">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="homepage.html">Home</a>
        <span>/</span>
        <a href="adopt_cat.php">Adopt a Cat</a>
        <span>/</span>
        <span>Adoption Application</span>
    </div>

    <!-- PAGE TITLE -->
    <section class="page-intro">
        <span class="eyebrow">GIVE A CAT A FOREVER HOME</span>

        <h1>Apply to <span>Adopt.</span></h1>

        <p>
            Every cat deserves a loving home. Tell us why you would like
            to adopt your chosen cat and how you plan to provide a safe,
            happy and caring environment.
        </p>
    </section>

    <!-- FORM AND INFORMATION PANEL -->
    <section class="layout">

        <div class="form-card">

            <div class="card-heading">
                <h2><span>♡</span> Adoption Application</h2>
                <p>
                    Complete the form below with the information about
                    your adoption request.
                </p>
            </div>

            <?php if ($success !== ''): ?>

                <div class="message success" role="status">
                    <strong>Application Submitted!</strong><br>
                    <?= e($success) ?>
                </div>

                <div class="button-row">
                    <a class="submit-btn" href="my_application.php">
                        View My Applications &nbsp; →
                    </a>

                    <a class="back-button" href="adopt_cat.php">
                        Browse More Cats
                    </a>
                </div>

            <?php else: ?>

                <?php if ($error !== ''): ?>
                    <div class="message error" role="alert">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$studentId): ?>

                    <div class="message error" role="alert">
                        Please log in as a student before applying.
                    </div>

                <?php endif; ?>

                <?php if ($cat): ?>

                    <h3 class="form-section-title">Cat Information</h3>

                    <div class="cat-info">

                        <!-- CAT PHOTO -->
                        <div class="cat-photo-box">
                            <?php if ($catImage !== ''): ?>

                                <img
                                    src="<?= e($catImage) ?>"
                                    alt="Photo of <?= e($cat['cat_name']) ?>"
                                    onerror="this.style.display='none';this.nextElementSibling.hidden=false">

                                <div class="cat-photo-empty" hidden>
                                    <span>🐱</span>
                                    <small>Photo unavailable</small>
                                </div>

                            <?php else: ?>

                                <div class="cat-photo-empty">
                                    <span>🐱</span>
                                    <small>Photo unavailable</small>
                                </div>

                            <?php endif; ?>
                        </div>

                        <!-- CAT DETAILS -->
                        <div class="cat-info-details">

                            <div class="cat-info-row">
                                <strong>Cat's Name:</strong>
                                <span><?= e($cat['cat_name']) ?></span>
                            </div>

                            <div class="cat-info-row">
                                <strong>Age:</strong>
                                <span><?= e($cat['age'] ?: 'Unknown') ?></span>
                            </div>

                            <div class="cat-info-row">
                                <strong>Gender:</strong>
                                <span><?= e($cat['gender'] ?: 'Unknown') ?></span>
                            </div>

                            <div class="cat-info-row">
                                <strong>Description:</strong>
                                <span>
                                    <?= e(
                                        $cat['description']
                                        ?: 'A lovely cat waiting for a caring home.'
                                    ) ?>
                                </span>
                            </div>

                            <div class="cat-info-row">
                                <strong>Cat ID:</strong>
                                <span>#<?= (int)$cat['cat_id'] ?></span>
                            </div>

                        </div>

                    </div>

                    <h3 class="form-section-title">Your Application</h3>

                    <form method="POST" action="adoption_form.php">

                        <input
                            type="hidden"
                            name="cat_id"
                            value="<?= (int)$cat['cat_id'] ?>">

                        <div class="form-group">
                            <label for="reason">
                                Why do you want to adopt this cat?
                                <span class="required">*</span>
                            </label>

                            <textarea
                                class="form-control"
                                id="reason"
                                name="reason"
                                maxlength="2000"
                                required
                                placeholder="Tell us how you will care for this cat."><?= e($_POST['reason'] ?? '') ?></textarea>

                            <p class="field-hint">
                                Explain your reasons and how you will
                                provide a safe and loving home.
                                Maximum 2000 characters.
                            </p>
                        </div>

                        <div class="button-row">
                            <button
                                class="submit-btn"
                                type="submit"
                                <?= !$studentId ? 'disabled' : '' ?>>
                                Submit Application &nbsp; ♡
                            </button>

                            <a class="back-button" href="adopt_cat.php">
                                ← Back to Adopt a Cat
                            </a>
                        </div>

                    </form>

                <?php else: ?>

                    <div class="message error" role="alert">
                        No available cat was selected. Please return to
                        the adoption page and click Apply to Adopt on
                        an available cat.
                    </div>

                    <div class="button-row">
                        <a class="submit-btn" href="adopt_cat.php">
                            Choose a Cat &nbsp; →
                        </a>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <!-- RIGHT INFORMATION PANEL -->
        <aside class="info-card">

            <div class="info-icon" aria-hidden="true">♡</div>

            <h2>Every paw deserves a second chance.</h2>

            <p>
                Adopting a cat is a meaningful commitment.
                Your application helps us understand your intentions
                and your plans for caring for your new companion.
            </p>

            <ul class="tips-list">

                <li>
                    <span class="tip-number">01</span>
                    <div>
                        <strong>Be sincere.</strong>
                        Explain why you want to adopt this particular cat.
                    </div>
                </li>

                <li>
                    <span class="tip-number">02</span>
                    <div>
                        <strong>Provide a safe home.</strong>
                        Consider how you will keep the cat safe,
                        comfortable and healthy.
                    </div>
                </li>

                <li>
                    <span class="tip-number">03</span>
                    <div>
                        <strong>Be prepared.</strong>
                        Think about food, cleanliness, veterinary care
                        and the time needed to look after a cat.
                    </div>
                </li>

                <li>
                    <span class="tip-number">04</span>
                    <div>
                        <strong>Wait for a decision.</strong>
                        Your application will be submitted for review.
                    </div>
                </li>

            </ul>

            <div class="notice">
                <strong>♡ A little reminder</strong>
                Adopting a cat means providing care, patience and love
                throughout its life. Thank you for considering giving
                a cat a forever home.
            </div>

        </aside>

    </section>

</main>

<footer class="site-footer">
    <div class="footer-inner">
        <p>
            Made with <span class="footer-heart">♥</span> for every cat
            looking for a loving home · Purrfect Home
        </p>

        <p>Every paw deserves love.</p>
    </div>
</footer>

<script>
const menuToggle = document.getElementById('menuToggle');
const mainNav = document.getElementById('mainNav');

menuToggle?.addEventListener('click', function () {
    const isOpen = mainNav.classList.toggle('open');

    menuToggle.setAttribute('aria-expanded', String(isOpen));
});
</script>

</body>
</html>