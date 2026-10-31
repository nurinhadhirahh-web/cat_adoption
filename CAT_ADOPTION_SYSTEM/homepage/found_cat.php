<?php
session_start();

/* ============================================================
   STUDENT LOGIN SECURITY
============================================================ */
if (
    !isset($_SESSION['role'], $_SESSION['student_id']) ||
    $_SESSION['role'] !== 'student'
) {
    header('Location: ../login%20page/login.html');
    exit;
}

require_once __DIR__ . '/../db.php';

$studentId = (int) $_SESSION['student_id'];

/* ============================================================
   HELPER
============================================================ */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   CHECK IF found_cats HAS "age" AND "gender" COLUMNS
   (run these once in phpMyAdmin if they do not exist yet:
    ALTER TABLE found_cats ADD COLUMN age VARCHAR(50) NULL AFTER cat_name;
    ALTER TABLE found_cats ADD COLUMN gender VARCHAR(20) NULL AFTER age; )
============================================================ */
$hasAge = false;
$hasGender = false;

try {
    $columns = $pdo->query("SHOW COLUMNS FROM found_cats")->fetchAll(PDO::FETCH_COLUMN);
    $hasAge = in_array('age', $columns, true);
    $hasGender = in_array('gender', $columns, true);
} catch (PDOException $ex) {
    error_log('Purrfect Home found_cats column check error: ' . $ex->getMessage());
}

/* ============================================================
   FLASH MESSAGE
============================================================ */
$message = $_SESSION['found_flash']['text'] ?? '';
$messageType = $_SESSION['found_flash']['type'] ?? 'success';

unset($_SESSION['found_flash']);

/* ============================================================
   SUBMIT FOUND CAT REPORT
============================================================ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $catName = trim((string) ($_POST['cat_name'] ?? ''));
    $age = trim((string) ($_POST['age'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $confirmed = isset($_POST['confirm_details']);

    // Only accept the allowed gender values
    if (!in_array($gender, ['Male', 'Female'], true)) {
        $gender = '';
    }

    $error = '';
    $imagePath = null;
    $uploadedFile = null;

    if ($catName === '') {
        $catName = 'Unknown';
    }

    if ($location === '' || $description === '') {
        $error = 'Please fill in the location and description.';
    } elseif (!$confirmed) {
        $error = 'Please confirm that the information is accurate.';
    } elseif (
        mb_strlen($catName) > 100 ||
        mb_strlen($age) > 50 ||
        mb_strlen($location) > 255 ||
        mb_strlen($description) > 3000
    ) {
        $error = 'Some of the information is too long.';
    }

    /* -------------------------
       PHOTO UPLOAD (OPTIONAL)
    ------------------------- */
    if (
        $error === '' &&
        isset($_FILES['photo']) &&
        $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['photo'];

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'The photo could not be uploaded. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'The image must be 5 MB or smaller.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $error = 'Invalid uploaded image.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $imageInfo = @getimagesize($file['tmp_name']);

            if (!isset($allowed[$mime]) || $imageInfo === false) {
                $error = 'Please select a JPG, PNG or WEBP image.';
            } else {
                // Save image in the same folder as found_cat.php
                $uploadDir = __DIR__;

                try {
                    $newName = 'found_' .
                        bin2hex(random_bytes(12)) .
                        '.' . $allowed[$mime];

                    $destination = $uploadDir . DIRECTORY_SEPARATOR . $newName;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $imagePath = $newName;
                        $uploadedFile = $destination;
                    } else {
                        $error = 'The photo could not be saved.';
                    }
                } catch (Throwable $ex) {
                    error_log('Purrfect Home image upload error: ' . $ex->getMessage());
                    $error = 'The photo could not be saved. Please try again.';
                }
            }
        }
    }

    /* -------------------------
       SAVE REPORT TO DATABASE
    ------------------------- */
    if ($error === '') {
        try {
            $fields = ['student_id', 'cat_name'];
            $params = [$studentId, $catName];

            if ($hasAge) {
                $fields[] = 'age';
                $params[] = ($age !== '' ? $age : null);
            }

            if ($hasGender) {
                $fields[] = 'gender';
                $params[] = ($gender !== '' ? $gender : null);
            }

            $fields = array_merge($fields, ['location', 'description', 'image_path']);
            array_push($params, $location, $description, $imagePath);

            $placeholders = implode(', ', array_fill(0, count($fields), '?'));

            $stmt = $pdo->prepare(
                "INSERT INTO found_cats (" . implode(', ', $fields) . ", status, submitted_at)
                 VALUES ($placeholders, 'Pending', NOW())"
            );
            $stmt->execute($params);

            $_SESSION['found_flash'] = [
                'type' => 'success',
                'text' => 'Report submitted successfully. Thank you for helping a cat in need.'
            ];

        } catch (PDOException $ex) {
            error_log('Purrfect Home found_cat insert error: ' . $ex->getMessage());

            // Remove uploaded file if database insert failed
            if ($uploadedFile && is_file($uploadedFile)) {
                @unlink($uploadedFile);
            }

            $_SESSION['found_flash'] = [
                'type' => 'error',
                'text' => 'Unable to save your report. Please check the database configuration.'
            ];
        }
    } else {
        if ($uploadedFile && is_file($uploadedFile)) {
            @unlink($uploadedFile);
        }

        $_SESSION['found_flash'] = [
            'type' => 'error',
            'text' => $error
        ];
    }

    // Prevent duplicate submissions when refreshing
    header('Location: found_cat.php');
    exit;
}

/* ============================================================
   GET ALL FOUND CAT REPORTS
============================================================ */
$foundCats = [];
$dbError = false;

try {
    $ageSelect = $hasAge ? 'age' : 'NULL AS age';
    $genderSelect = $hasGender ? 'gender' : 'NULL AS gender';

    $stmt = $pdo->query(
        "SELECT
            id,
            cat_name,
            $ageSelect,
            $genderSelect,
            location,
            description,
            image_path,
            status,
            submitted_at
         FROM found_cats
         ORDER BY submitted_at DESC, id DESC"
    );

    $foundCats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('Purrfect Home found_cat list error: ' . $ex->getMessage());
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Found Cats | Purrfect Home</title>

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

        button, input, textarea, select {
            font: inherit;
        }

        /* HEADER */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            background: #fffafd;
            background-image: radial-gradient(#f8dce8 .65px, transparent .65px);
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

        /* PAGE */
        .page-shell {
            max-width: 1120px;
            margin: auto;
            padding: 54px 25px 75px;
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
            font-family: Georgia, serif;
            font-size: clamp(34px, 5vw, 49px);
            font-weight: 600;
            line-height: 1.2;
        }

        .page-intro h1 span {
            color: var(--pink);
        }

        .page-intro p {
            max-width: 650px;
            color: var(--muted);
            font-size: 14px;
        }

        /* TABS */
        .tabs {
            display: inline-flex;
            gap: 6px;
            padding: 5px;
            margin-bottom: 26px;
            background: white;
            border: 1px solid var(--pink-border);
            border-radius: 14px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .tab-btn.active {
            background: var(--pink);
            color: white;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(260px, .85fr);
            align-items: start;
            gap: 25px;
        }

        .form-card,
        .info-card {
            background: white;
            border: 1px solid var(--pink-border);
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(109, 58, 82, .045);
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
        }

        .card-heading p {
            color: var(--muted);
            font-size: 12px;
        }

        .form-section-title {
            margin: 25px 0 17px;
            color: var(--pink-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .form-group {
            min-width: 0;
            margin-bottom: 19px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        .optional {
            color: #a897a1;
            font-weight: 400;
        }

        .required {
            color: var(--pink);
        }

        .form-control {
            display: block;
            width: 100%;
            min-height: 45px;
            padding: 11px 13px;
            border: 1px solid #e9dce3;
            border-radius: 9px;
            outline: none;
            background: #fffdfe;
            color: var(--text);
            font-size: 12px;
        }

        .form-control:focus {
            border-color: #dc7da4;
            box-shadow: 0 0 0 3px rgba(207, 86, 136, .09);
        }

        textarea.form-control {
            min-height: 125px;
            resize: vertical;
        }

        input[type="file"].form-control {
            padding: 10px;
            background: var(--pink-light);
        }

        .field-hint {
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
        }

        .photo-preview {
            display: none;
            width: 100%;
            max-height: 250px;
            margin-top: 12px;
            border: 1px solid var(--pink-border);
            border-radius: 12px;
            object-fit: cover;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 7px 0 22px;
            color: var(--muted);
            font-size: 11px;
        }

        .checkbox-row input {
            margin-top: 4px;
            accent-color: var(--pink);
        }

        .submit-btn {
            display: flex;
            width: 100%;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            border-radius: 10px;
            background: var(--pink);
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: var(--pink-dark);
        }

        .message {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 9px;
            background: var(--pink-light);
            color: var(--pink-dark);
            font-size: 12px;
        }

        .message.success {
            background: #eaf6ee;
            color: #27663d;
        }

        .message.error {
            background: #fdeaea;
            color: #9b2c2c;
        }

        /* INFORMATION PANEL */
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
            font-family: Georgia, serif;
            font-size: 23px;
        }

        .info-card > p {
            color: #806d79;
            font-size: 12px;
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
        }

        .tip-number {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 25px;
            height: 25px;
            border-radius: 8px;
            background: white;
            color: var(--pink);
            font-weight: 800;
        }

        .notice {
            padding: 16px;
            margin-top: 25px;
            border: 1px solid #f4dce7;
            border-radius: 12px;
            background: white;
            color: #806d79;
            font-size: 11px;
        }

        .notice strong {
            display: block;
            margin-bottom: 4px;
            color: var(--pink-dark);
            font-size: 12px;
        }

        /* GALLERY */
        .gallery-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .gallery-toolbar .form-control {
            width: auto;
            flex: 1 1 220px;
        }

        .gallery-toolbar select.form-control {
            flex: 0 0 180px;
        }

        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 22px;
        }

        .cat-card {
            overflow: hidden;
            background: white;
            border: 1px solid var(--pink-border);
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(109, 58, 82, .045);
        }

        .cat-photo {
            display: block;
            width: 100%;
            height: 190px;
            object-fit: cover;
            background: var(--pink-light);
        }

        .cat-photo-empty {
            display: grid;
            place-items: center;
            height: 190px;
            background: var(--pink-light);
            font-size: 44px;
        }

        .cat-body {
            padding: 16px 18px 18px;
        }

        .cat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .cat-top h3 {
            overflow-wrap: anywhere;
            font-size: 16px;
        }

        .badge {
            flex-shrink: 0;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge.pending {
            background: #fdf1d9;
            color: #8a5a0b;
        }

        .badge.reviewed {
            background: #e3effb;
            color: #1d4f86;
        }

        .badge.resolved {
            background: #e5f4e9;
            color: #27663d;
        }

        .badge.rejected {
            background: #fdeaea;
            color: #9b2c2c;
        }

        .cat-meta {
            margin: 2px 0;
            color: var(--muted);
            font-size: 12px;
        }

        .cat-desc {
            margin-top: 9px;
            color: #6e5d67;
            font-size: 12px;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }

        .empty-state {
            grid-column: 1 / -1;
            padding: 40px 20px;
            border: 1px dashed var(--pink-border);
            border-radius: 18px;
            background: white;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        /* FOOTER */
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

        /* RESPONSIVE */
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
            .page-shell {
                padding: 35px 16px 50px;
            }

            .form-card,
            .info-card {
                padding: 21px;
            }

            .page-intro h1 {
                font-size: 36px;
            }

            .tabs {
                display: flex;
            }

            .tab-btn {
                flex: 1;
                padding: 10px 8px;
            }

            .gallery-toolbar select.form-control {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body>

<header class="site-header" id="home">
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
            <a class="nav-link" href="adopt_cat.php">Adopt a Cat</a>
            <a class="nav-link active" href="found_cat.php">Found Cats</a>
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

    <div class="breadcrumb">
        <a href="homepage.html">Home</a>
        <span>/</span>
        <span>Found Cats</span>
    </div>

    <section class="page-intro">
        <span class="eyebrow">HELP A CAT IN NEED</span>

        <h1 id="pageHeading">Report a <span>Found Cat.</span></h1>

        <p id="pageIntro">
            Have you spotted a cat that needs help?
            Share the details with our community so we can work
            towards a safer and more caring environment for every cat.
        </p>
    </section>

    <div class="tabs" role="tablist">
        <button
            class="tab-btn active"
            type="button"
            role="tab"
            data-tab="report"
            aria-selected="true">
            Report a Found Cat
        </button>

        <button
            class="tab-btn"
            type="button"
            role="tab"
            data-tab="gallery"
            aria-selected="false">
            Found Cats (<?= count($foundCats) ?>)
        </button>

        <button
            class="tab-btn"
            type="button"
            role="tab"
            data-tab="reports"
            aria-selected="false">
            My Reports
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= e($messageType) ?>" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($dbError): ?>
        <div class="message error" role="alert">
            Unable to load found cat reports.
            Please check the found_cats table, column names and PHP error log.
        </div>
    <?php endif; ?>

    <!-- TAB 1: REPORT -->
    <section class="tab-panel active" id="panel-report">
        <div class="layout">

            <section class="form-card">
                <div class="card-heading">
                    <h2>Found Cat Report</h2>
                    <p>Complete the form below with the information you know.</p>
                </div>

                <form
                    id="foundCatForm"
                    action="found_cat.php"
                    method="post"
                    enctype="multipart/form-data">

                    <h3 class="form-section-title">Cat Information</h3>

                    <div class="form-group">
                        <label for="cat_name">
                            Cat's Name <span class="optional">(if known)</span>
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            id="cat_name"
                            name="cat_name"
                            maxlength="100"
                            placeholder="e.g. Oyen">
                    </div>

                    <div class="form-group">
                        <label for="age">
                            Cat's Age <span class="optional">(estimate, if known)</span>
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            id="age"
                            name="age"
                            maxlength="50"
                            placeholder="e.g. 2 years, 3 months, kitten">

                        <p class="field-hint">
                            An estimate is fine. Leave blank if you are not sure.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="gender">
                            Cat's Gender <span class="optional">(if known)</span>
                        </label>

                        <select
                            class="form-control"
                            id="gender"
                            name="gender">
                            <option value="">Unknown</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="location">
                            Location Found <span class="required">*</span>
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            id="location"
                            name="location"
                            maxlength="255"
                            placeholder="e.g. Student cafeteria, Block A"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            Description <span class="required">*</span>
                        </label>

                        <textarea
                            class="form-control"
                            id="description"
                            name="description"
                            maxlength="3000"
                            placeholder="Describe the cat's colour, condition and other helpful details..."
                            required></textarea>

                        <p class="field-hint">
                            Include the cat's appearance and condition if possible.
                        </p>
                    </div>

                    <h3 class="form-section-title">Photo and Details</h3>

                    <div class="form-group">
                        <label for="photo">
                            Cat Photo <span class="optional">(optional)</span>
                        </label>

                        <input
                            class="form-control"
                            type="file"
                            id="photo"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <p class="field-hint">
                            JPG, PNG or WEBP. Maximum 5 MB.
                        </p>

                        <img
                            id="photoPreview"
                            class="photo-preview"
                            alt="Preview of selected cat photo">
                    </div>

                    <label class="checkbox-row">
                        <input
                            type="checkbox"
                            id="confirmDetails"
                            name="confirm_details"
                            value="1"
                            required>

                        <span>
                            I confirm that the information provided is accurate
                            to the best of my knowledge.
                        </span>
                    </label>

                    <button class="submit-btn" type="submit">
                        Submit Report <span aria-hidden="true">→</span>
                    </button>

                </form>
            </section>

            <aside class="info-card">
                <div class="info-icon" aria-hidden="true">♡</div>

                <h2>Every paw deserves a second chance.</h2>

                <p>
                    Your report can help others locate a cat in need
                    and provide the right assistance.
                </p>

                <ul class="tips-list">
                    <li>
                        <span class="tip-number">01</span>
                        <span>
                            <strong>Be specific.</strong><br>
                            Provide a clear location where you saw the cat.
                        </span>
                    </li>

                    <li>
                        <span class="tip-number">02</span>
                        <span>
                            <strong>Describe the cat.</strong><br>
                            Include its colour, size, age and visible condition.
                        </span>
                    </li>

                    <li>
                        <span class="tip-number">03</span>
                        <span>
                            <strong>Add a photo.</strong><br>
                            A clear picture can help identify the cat.
                        </span>
                    </li>

                    <li>
                        <span class="tip-number">04</span>
                        <span>
                            <strong>Stay safe.</strong><br>
                            Avoid approaching an injured or frightened animal
                            if doing so puts you at risk.
                        </span>
                    </li>
                </ul>

                <div class="notice">
                    <strong>Thank you for caring!</strong>
                    Every report is a small step towards helping a cat
                    find safety and care.
                </div>
            </aside>

        </div>
    </section>

    <!-- TAB 2: FOUND CATS GALLERY -->
    <section class="tab-panel" id="panel-gallery">

        <div class="gallery-toolbar">
            <input
                class="form-control"
                type="search"
                id="catSearch"
                placeholder="Search cat name or location...">

            <select
                class="form-control"
                id="statusFilter"
                aria-label="Filter by status">

                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="reviewed">Reviewed</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="cat-grid" id="catGrid">

            <?php if (!$dbError && empty($foundCats)): ?>
                <div class="empty-state" id="initialEmpty">
                    No found cat reports yet.
                </div>
            <?php endif; ?>

            <?php foreach ($foundCats as $cat): ?>

                <?php
                    $name = trim((string) ($cat['cat_name'] ?? ''));
                    if ($name === '') {
                        $name = 'Unknown';
                    }

                    $catAge = trim((string) ($cat['age'] ?? ''));
                    $catGender = trim((string) ($cat['gender'] ?? ''));
                    $location = (string) ($cat['location'] ?? '');
                    $description = (string) ($cat['description'] ?? '');

                    $status = trim((string) ($cat['status'] ?? 'Pending'));
                    if ($status === '') {
                        $status = 'Pending';
                    }

                    $statusClass = strtolower(
                        preg_replace('/[^a-z]/i', '', $status)
                    );

                    $imagePath = trim((string) ($cat['image_path'] ?? ''));

                    // Support older records with a cat-name-based image file
                    if ($imagePath === '') {
                        $slug = preg_replace(
                            '/[^a-z0-9_-]/',
                            '',
                            strtolower($name)
                        );

                        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                            if (
                                $slug !== '' &&
                                is_file(__DIR__ . DIRECTORY_SEPARATOR . $slug . '.' . $ext)
                            ) {
                                $imagePath = $slug . '.' . $ext;
                                break;
                            }
                        }
                    }

                    // Prevent unsafe paths from being used as image URLs
                    if (
                        $imagePath !== '' &&
                        (
                            basename($imagePath) !== $imagePath ||
                            !is_file(__DIR__ . DIRECTORY_SEPARATOR . $imagePath)
                        )
                    ) {
                        $imagePath = '';
                    }

                    $timestamp = strtotime((string) ($cat['submitted_at'] ?? ''));
                ?>

                <article
                    class="cat-card"
                    data-search="<?= e(function_exists('mb_strtolower')
                        ? mb_strtolower($name . ' ' . $location)
                        : strtolower($name . ' ' . $location)) ?>"
                    data-status="<?= e(strtolower($status)) ?>">

                    <?php if ($imagePath !== ''): ?>
                        <img
                            class="cat-photo"
                            src="<?= e($imagePath) ?>"
                            alt="Photo of <?= e($name) ?>"
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">

                        <div
                            class="cat-photo-empty"
                            style="display:none"
                            aria-hidden="true">🐱</div>
                    <?php else: ?>
                        <div class="cat-photo-empty" aria-hidden="true">🐱</div>
                    <?php endif; ?>

                    <div class="cat-body">
                        <div class="cat-top">
                            <h3><?= e($name) ?></h3>

                            <span class="badge <?= e($statusClass) ?>">
                                <?= e($status) ?>
                            </span>
                        </div>

                        <p class="cat-meta">
                            📍 <?= e($location) ?>
                        </p>

                        <?php if ($catAge !== ''): ?>
                            <p class="cat-meta">
                                🎂 Age: <?= e($catAge) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($catGender !== ''): ?>
                            <p class="cat-meta">
                                🐾 Gender: <?= e($catGender) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($timestamp !== false): ?>
                            <p class="cat-meta">
                                📅 <?= e(date('d M Y', $timestamp)) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (trim($description) !== ''): ?>
                            <p class="cat-desc"><?= e($description) ?></p>
                        <?php endif; ?>
                    </div>
                </article>

            <?php endforeach; ?>

            <div class="empty-state" id="noMatch" style="display:none;">
                No matching reports found.
            </div>

        </div>
    </section>

    <!-- TAB 3: MY REPORTS -->
    <section class="tab-panel" id="panel-reports">
        <?php include __DIR__ . '/my_report_panel.php'; ?>
    </section>

</main>

<footer class="site-footer">
    <div class="footer-inner">
        <span>© 2026 Purrfect Home. All rights reserved.</span>
        <span>Made with <span class="footer-heart">♥</span> for cats</span>
    </div>
</footer>

<script>
    /* MOBILE NAVIGATION */
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');

    menuToggle.addEventListener('click', function () {
        const isOpen = mainNav.classList.toggle('open');

        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.textContent = isOpen ? '✕ Close' : '☰ Menu';
    });

    /* TABS */
    const tabButtons = document.querySelectorAll('.tab-btn');
    const heading = document.getElementById('pageHeading');
    const intro = document.getElementById('pageIntro');

    const copy = {
        report: {
            title: 'Report a <span>Found Cat.</span>',
            text: 'Have you spotted a cat that needs help? Share the details ' +
                'with our community so we can work towards a safer and ' +
                'more caring environment for every cat.'
        },
        gallery: {
            title: 'Cats <span>Found Nearby.</span>',
            text: 'Browse the cats our community has reported. ' +
                'Recognise one? Let the admin know.'
        },
        reports: {
            title: 'My <span>Reports.</span>',
            text: 'Track your reports and claim your reward after 4 reviewed reports.'
        }
    };

    function showTab(name) {
        if (!copy[name]) {
            name = 'report';
        }

        tabButtons.forEach(function (button) {
            const active = button.dataset.tab === name;

            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', String(active));
        });

        document.querySelectorAll('.tab-panel').forEach(function (panel) {
            panel.classList.toggle(
                'active',
                panel.id === 'panel-' + name
            );
        });

        heading.innerHTML = copy[name].title;
        intro.textContent = copy[name].text;

        history.replaceState(null, '', '#' + name);

        if (name === 'gallery') {
            filterCats();
        }
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            showTab(button.dataset.tab);
        });
    });

    /* GALLERY SEARCH AND STATUS FILTER */
    const searchInput = document.getElementById('catSearch');
    const statusFilter = document.getElementById('statusFilter');
    const cards = document.querySelectorAll('.cat-card');
    const noMatch = document.getElementById('noMatch');

    function filterCats() {
        const query = searchInput.value.trim().toLowerCase();
        const status = statusFilter.value;
        let visible = 0;

        cards.forEach(function (card) {
            const matchesSearch = card.dataset.search.includes(query);
            const matchesStatus =
                !status || card.dataset.status === status;

            const show = matchesSearch && matchesStatus;

            card.style.display = show ? '' : 'none';

            if (show) {
                visible++;
            }
        });

        noMatch.style.display =
            cards.length > 0 && visible === 0 ? 'block' : 'none';
    }

    searchInput.addEventListener('input', filterCats);
    statusFilter.addEventListener('change', filterCats);

    /* PHOTO PREVIEW AND VALIDATION */
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');

    photoInput.addEventListener('change', function () {
        const file = this.files[0];

        photoPreview.style.display = 'none';
        photoPreview.removeAttribute('src');

        if (!file) {
            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type)) {
            alert('Please select a JPG, PNG or WEBP image.');
            this.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('The image must be 5 MB or smaller.');
            this.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            photoPreview.src = event.target.result;
            photoPreview.style.display = 'block';
        };

        reader.readAsDataURL(file);
    });

    /* CONFIRM BEFORE SUBMITTING */
    document.getElementById('foundCatForm').addEventListener(
        'submit',
        function (event) {
            const locationValue =
                document.getElementById('location').value.trim();

            const descriptionValue =
                document.getElementById('description').value.trim();

            const confirmation =
                document.getElementById('confirmDetails').checked;

            if (!locationValue || !descriptionValue || !confirmation) {
                event.preventDefault();
                return;
            }

            if (!confirm(
                'Are you sure you want to submit this found cat report?'
            )) {
                event.preventDefault();
            }
        }
    );

    /* OPEN TAB FROM URL HASH */
    showTab(location.hash.replace('#', '') || 'report');
</script>

</body>
</html>