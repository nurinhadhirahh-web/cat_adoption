
<?php
session_start();

require_once __DIR__ . '/../db.php';

$pdo = $pdo ?? $conn ?? null;

if (!$pdo instanceof PDO) {
    die('Database connection error. Please check your db.php file.');
}

$error = '';
$cats = [];

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
 * Find a local image in the same folder as this file.
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

/* Fallback images for normal cats */
$catImages = [
    'oyen'  => 'https://radarmukomuko.disway.id/upload/8c522687768aa1dd95c0d06a21a77a35.jpg',
    'simba' => 'https://s3.envato.com/files/4637af80-c90f-4ec7-b4d2-6b23a666b155/inline_image_preview.jpg',
    'luna'  => 'https://radarkuningan.disway.id/upload/7f141ade2e60caf26962f7b9a8610a15.PNG',
    'tom'   => 'https://radarkuningan.disway.id/upload/f42f4d7a2770fb5b5f73ed9b99e8f8bb.jpeg'
];

$defaultCatImage =
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=900&q=85';

/*
|--------------------------------------------------------------------------
| LOAD CATS
|--------------------------------------------------------------------------
| 1. Load available cats from cats.
| 2. Load resolved found cats not yet copied into cats.
| 3. Prevent duplicate cat records.
|--------------------------------------------------------------------------
*/

try {
    $foundColumns = $pdo->query(
        "SHOW COLUMNS FROM found_cats"
    )->fetchAll(PDO::FETCH_COLUMN);

    $imageColumn = null;

    if (in_array('image_path', $foundColumns, true)) {
        $imageColumn = 'image_path';
    } elseif (in_array('photo_path', $foundColumns, true)) {
        $imageColumn = 'photo_path';
    }

    $imageSelect = $imageColumn ? "`$imageColumn`" : 'NULL';
    $joinImage   = $imageColumn ? "f.`$imageColumn`" : 'NULL';
    $ageSelect   = in_array('age', $foundColumns, true) ? 'age' : 'NULL';

    /* Load available cats */
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
         WHERE c.status = 'Available'
         ORDER BY c.cat_id DESC"
    );

    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
        $cats[] = [
            'cat_id'       => (int)$cat['cat_id'],
            'cat_name'     => $cat['cat_name'] ?? 'Lovely Cat',
            'age'          => $cat['age'] ?? 'Unknown',
            'gender'       => $cat['gender'] ?? 'Unknown',
            'description'  => $cat['description'] ?? '',
            'source'       => 'cats',
            'found_cat_id' => (int)($cat['found_cat_id'] ?? 0),
            'image_url'    => trim((string)($cat['found_image'] ?? '')),
            'sort_id'      => (int)$cat['cat_id']
        ];
    }

    /* Load resolved found cats not yet copied into cats */
    $stmt = $pdo->query(
        "SELECT
            id,
            cat_name,
            location,
            description,
            $imageSelect AS image_url,
            $ageSelect AS age,
            status,
            submitted_at
         FROM found_cats
         WHERE status = 'Resolved'
           AND NOT EXISTS (
               SELECT 1
               FROM cats c
               WHERE c.found_cat_id = found_cats.id
           )
         ORDER BY submitted_at DESC, id DESC"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $foundCat) {
        $location = trim((string)($foundCat['location'] ?? ''));
        $description = trim((string)($foundCat['description'] ?? ''));

        if ($location !== '' && $description !== '') {
            $description = $location . ' — ' . $description;
        } elseif ($location !== '') {
            $description = $location;
        }

        $cats[] = [
            'cat_id'       => (int)$foundCat['id'],
            'cat_name'     => trim((string)($foundCat['cat_name'] ?? '')) ?: 'Found Cat',
            'age'          => trim((string)($foundCat['age'] ?? '')) ?: 'Unknown',
            'gender'       => 'Unknown',
            'description'  => $description,
            'source'       => 'found_cats',
            'found_cat_id' => (int)$foundCat['id'],
            'image_url'    => trim((string)($foundCat['image_url'] ?? '')),
            'sort_id'      => (int)$foundCat['id']
        ];
    }

} catch (PDOException $exception) {
    error_log('Adopt cats error: ' . $exception->getMessage());
    $error = 'Unable to load cats. Please check your database table and column names.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Adopt a Cat | Purrfect Home</title>

<style>
:root {
    --pink: #d95785;
    --pink-dark: #b93667;
    --pink-soft: #fbe7ef;
    --pink-pale: #fff7fa;
    --pink-border: #f4d9e4;
    --text: #3f3b49;
    --muted: #7e8197;
    --white: #ffffff;
    --shadow: 0 8px 24px rgba(174,83,120,.10);
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background: linear-gradient(135deg,#fff9fb,#fff3f7);
}

a {
    color: inherit;
}

[hidden] {
    display: none !important;
}

/* HEADER */
.topbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    min-height: 82px;
    padding: 12px max(20px,calc((100% - 1200px)/2));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    background-color: #fffafd;
    background-image: radial-gradient(#f5dce7 .7px,transparent .7px);
    background-size: 10px 10px;
    border-bottom: 1px solid #f3dce6;
    box-shadow: 0 3px 14px rgba(180,85,120,.05);
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    text-decoration: none;
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    color: white;
    background: linear-gradient(135deg,#e76d99,#c43c70);
    border-radius: 15px;
    font-size: 24px;
}

.brand-name {
    display: block;
    color: #b63868;
    font-size: 17px;
    font-weight: 800;
}

.brand-tagline {
    display: block;
    margin-top: 3px;
    color: #d05c86;
    font-size: 10px;
}

.top-nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
    flex-wrap: wrap;
}

.top-nav a {
    padding: 10px 9px;
    color: #6f6879;
    text-decoration: none;
    font-size: 12px;
    font-weight: 650;
    border-radius: 10px;
    white-space: nowrap;
    transition: .2s ease;
}

.top-nav a:hover,
.top-nav a.active {
    color: var(--pink-dark);
    background: #fde8f0;
}

.top-nav a.logout {
    margin-left: 4px;
    padding: 10px 15px;
    color: white;
    background: linear-gradient(135deg,#e8759d,#d65382);
    border-radius: 24px;
}

.top-nav a.logout:hover {
    color: white;
    background: var(--pink-dark);
}

/* PAGE LAYOUT */
.page-layout {
    display: grid;
    grid-template-columns: 205px minmax(0,1fr);
    gap: 28px;
    max-width: 1600px;
    margin: 0 auto;
    padding: 24px 3%;
}

.decorative-sidebar {
    min-height: 820px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 25px 15px;
    background: linear-gradient(180deg,#fbe7ef,#fff0f5 68%,#f9dce8);
    border: 1px solid #f8e4ec;
    border-radius: 22px;
}

.sidebar-decoration {
    width: 100%;
    text-align: center;
    color: #c95683;
    padding-bottom: 12px;
}

.sidebar-heart {
    display: block;
    font-size: 70px;
    line-height: 1.2;
}

.sidebar-decoration p {
    font-size: 13px;
    line-height: 1.6;
}

.paw {
    position: absolute;
    color: #f0a9c1;
    font-size: 28px;
    opacity: .8;
}

.paw-one {
    top: 68%;
    left: 18%;
    transform: rotate(-18deg);
}

.paw-two {
    top: 76%;
    right: 15%;
    transform: rotate(17deg);
}

.paw-three {
    top: 84%;
    left: 20%;
    transform: rotate(15deg);
}

.content {
    min-width: 0;
}

/* HERO */
.hero {
    min-height: 250px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 36px;
    background:
    linear-gradient(115deg,
        rgba(255,240,245,.78) 0%,
        rgba(251,221,232,.62) 64%,
        rgba(245,184,206,.45) 100%),
    url('cat_image.jpg') center / cover no-repeat;
    border: 1px solid #f8e1ea;
    border-radius: 24px;
    box-shadow: var(--shadow);
}

.hero-copy {
    max-width: 100%;
    width: 100%;
    position: relative;
    z-index: 2;
}

.eyebrow {
    margin: 0 0 9px;
    color: var(--pink-dark);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 2.2px;
    text-transform: uppercase;
}

.hero h1 {
    margin: 0;
    color: #383744;
    font-family: Georgia,"Times New Roman",serif;
    font-size: clamp(36px,4vw,54px);
    line-height: 1.12;
    letter-spacing: -1.5px;
}

.hero h1 span {
    color: #cc4e7d;
}

.hero-subtitle {
    margin: 12px 0 8px;
    font-size: clamp(17px,1.7vw,23px);
    font-weight: 600;
}

.hero-description {
    max-width: 570px;
    margin: 10px 0 22px;
    color: #73758b;
    line-height: 1.7;
}

.hero-heart {
    position: absolute;
    right: 5%;
    top: 18%;
    color: #d95d8b;
    font-size: 44px;
    transform: rotate(12deg);
}

/* SEARCH */
.search-row {
    display: flex;
    gap: 10px;
    max-width: 650px;
}

.search-box {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 17px;
    background: white;
    border: 1px solid #f5e2ea;
    border-radius: 30px;
    box-shadow: 0 4px 12px rgba(181,89,126,.07);
}

.search-box span {
    color: #dc5c8b;
    font-size: 21px;
}

.search-box input {
    width: 100%;
    min-width: 0;
    padding: 14px 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text);
    font: inherit;
}

.filter-button {
    border: 0;
    border-radius: 30px;
    padding: 0 23px;
    color: white;
    background: linear-gradient(135deg,#e8759d,#ce4e7e);
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.filter-button:hover {
    background: var(--pink-dark);
}

/* MESSAGES */
.message {
    margin: 18px 0;
    padding: 14px 17px;
    border-radius: 12px;
    line-height: 1.5;
}

.error {
    color: #9c2444;
    background: #fde7eb;
    border: 1px solid #f5cbd6;
}

/* CAT SECTION */
.section-panel {
    margin-top: 22px;
    padding: 23px 20px 20px;
    background: rgba(255,255,255,.88);
    border: 1px solid #f7e5ec;
    border-radius: 23px;
    box-shadow: var(--shadow);
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin: 0 0 20px;
}

.section-title-wrap {
    display: flex;
    align-items: center;
    gap: 13px;
}

.section-paw {
    color: #dc5e8b;
    font-size: 34px;
}

.section-heading h2 {
    margin: 0;
    font-family: Georgia,"Times New Roman",serif;
    font-size: clamp(21px,2vw,27px);
}

.section-heading p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 14px;
}

.sort-control {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #696779;
    font-size: 14px;
}

.sort-control select {
    padding: 11px 14px;
    color: #756274;
    background: #fffafd;
    border: 1px solid #f3dce6;
    border-radius: 24px;
    font: inherit;
    outline-color: #e8759d;
}

.cat-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 20px;
}

.cat-card {
    min-width: 0;
    overflow: hidden;
    padding: 10px;
    background: white;
    border: 1px solid #f5dce6;
    border-radius: 18px;
    box-shadow: 0 4px 12px rgba(166,75,112,.07);
    transition: transform .2s ease,box-shadow .2s ease;
}

.cat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 22px rgba(166,75,112,.12);
}

/* CAT PHOTO - AUTOMATIC SIZE, NO CROPPING */

/* CAT PHOTO - SAME SIZE, NO CROPPING */
.cat-picture {
    width: 100%;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #fde7ef, #f8d1e0);
    border-radius: 13px;
}

.cat-picture .cat-photo {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 25%;
}

.no-cat-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #c95683;
    background: linear-gradient(135deg, #fde7ef, #f8d1e0);
}

.no-cat-image span {
    font-size: 42px;
}

.no-cat-image small {
    font-size: 12px;
}

/* Favorite icon */
.favorite {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    color: #dd5e8b;
    background: rgba(255,255,255,.96);
    border-radius: 50%;
    font-size: 22px;
    line-height: 1;
}

.cat-details {
    padding: 14px 8px 8px;
}

.cat-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 13px;
}

.cat-name-row h3 {
    margin: 0;
    color: #c43e70;
    font-size: 23px;
    overflow-wrap: anywhere;
}

.gender-pill {
    flex-shrink: 0;
    padding: 5px 10px;
    color: #4b8ecb;
    background: #eaf4ff;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.cat-meta {
    display: grid;
    gap: 9px;
    margin: 0 0 15px;
    color: #707991;
    font-size: 14px;
}

.cat-meta p {
    margin: 0;
    line-height: 1.5;
}

.cat-meta strong {
    color: #46475a;
}

.cat-description {
    min-height: 43px;
    margin: 0 0 17px;
    color: #7c8299;
    font-size: 14px;
    line-height: 1.6;
}

.cat-status {
    margin: 0 0 14px;
    color: #33815c;
    font-size: 13px;
    font-weight: 700;
}

.apply-button {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 13px 17px;
    color: white;
    background: linear-gradient(100deg,#e36a96,#d54f80);
    border: 0;
    border-radius: 14px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 750;
    transition: .2s ease;
}

.apply-button:hover {
    background: linear-gradient(100deg,#ca4e7b,#b93769);
}

.paw-button {
    font-size: 19px;
}

.empty-message {
    grid-column: 1/-1;
    padding: 45px 20px;
    text-align: center;
    background: #fff7fa;
    border: 1px dashed #e8b8cb;
    border-radius: 16px;
    color: var(--muted);
}

.empty-message h3 {
    color: var(--pink-dark);
}

.footer {
    padding: 24px 15px 30px;
    color: #9a8290;
    text-align: center;
    font-size: 13px;
}

/* RESPONSIVE */
@media(max-width:1100px) {
    .page-layout {
        grid-template-columns: 150px minmax(0,1fr);
        gap: 18px;
    }

    .cat-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

    .topbar {
        padding: 12px 20px;
    }
}

@media(max-width:700px) {
    .topbar {
        padding: 12px 14px;
    }

    .top-nav {
        justify-content: flex-start;
    }

    .top-nav a {
        padding: 8px 9px;
        font-size: 11px;
    }

    .page-layout {
        display: block;
        padding: 16px;
    }

    .decorative-sidebar {
        display: none;
    }

    .hero {
        min-height: 290px;
        padding: 25px 20px;
    }

    .hero-heart {
        right: 7%;
        top: 8%;
    }

    .hero h1 {
        font-size: 39px;
    }

    .search-row {
        flex-direction: column;
    }

    .search-box {
        min-height: 48px;
    }

    .filter-button {
        min-height: 45px;
    }

    .section-panel {
        padding: 18px 12px 14px;
    }

    .section-heading {
        align-items: flex-start;
    }

    .cat-grid {
        grid-template-columns: 1fr;
    }

    /* Mobile: image height adjusts automatically */

      .cat-picture {
    height: 220px;
}

 .cat-picture .cat-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 25%;
}

}
</style>
</head>

<body>
<header class="topbar">
    <a class="brand" href="homepage.html">
        <span class="brand-mark" aria-hidden="true">🐾</span>
        <span>
            <span class="brand-name">Purrfect Home</span>
            <span class="brand-tagline">Adopt ♥ Rescue ♥ Give Love</span>
        </span>
    </a>

    <nav class="top-nav" aria-label="Main navigation">
        <a href="homepage.html">Home</a>
        <a class="active" href="adopt_cat.php">Adopt a Cat</a>
        <a href="found_cat.php">Found Cats</a>
        <a href="about_us.php">About Us</a>
        <a href="my_application.php">My Applications</a>
        <a class="logout"
           href="../login%20page/logout.php"
           onclick="return confirm('Are you sure you want to log out?');">
            Log Out
        </a>
    </nav>
</header>

<div class="page-layout">
    <aside class="decorative-sidebar" aria-label="Decorative panel">
        <span class="paw paw-one" aria-hidden="true"></span>
        <span class="paw paw-two" aria-hidden="true"></span>
        <span class="paw paw-three" aria-hidden="true"></span>

        <div class="sidebar-decoration">
            <span class="sidebar-heart" aria-hidden="true">♡</span>
        </div>
    </aside>

    <main class="content">
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">A little love changes everything</p>

                <h1>Adopt a <span>Cat</span> ♡</h1>

                <p class="hero-subtitle">
                    Their second chance could be your greatest joy.
                </p>

                <p class="hero-description">
                    Find your new furry friend at Purrfect Home.
                    Give a cat a loving home and help change their life forever.
                </p>

                <form class="search-row" id="cat-search-form" role="search">
                    <label class="search-box">
                        <span aria-hidden="true">⌕</span>

                        <input
                            id="cat-search"
                            type="search"
                            placeholder="Search by name, age, gender or description..."
                            aria-label="Search available cats"
                        >
                    </label>

                    <button class="filter-button" type="submit">
                        ☷ &nbsp; Search
                    </button>
                </form>
            </div>

            <span class="hero-heart" aria-hidden="true">♡</span>
        </section>

        <?php if ($error !== ''): ?>
            <div class="message error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <section class="section-panel" id="available-cats">
            <div class="section-heading">
                <div class="section-title-wrap">
                    <span class="section-paw" aria-hidden="true">🐾</span>

                    <div>
                        <h2>Available for Adoption</h2>
                        <p>
                            Meet the lovely cats waiting for their forever homes.
                        </p>
                    </div>
                </div>

                <label class="sort-control" for="sort-cats">
                    Sort by:

                    <select id="sort-cats">
                        <option value="newest">Newest</option>
                        <option value="name">Name A–Z</option>
                        <option value="age">Age: Youngest first</option>
                    </select>
                </label>
            </div>

            <div class="cat-grid" id="cat-grid">

                <?php if (count($cats) > 0): ?>

                    <?php foreach ($cats as $cat): ?>

                        <?php
                        $isFoundCat = ($cat['source'] ?? '') === 'found_cats';

                        $fromFound = $isFoundCat ||
                            !empty($cat['found_cat_id']);

                        $imageUrl = trim(
                            (string)($cat['image_url'] ?? '')
                        );

                        if ($fromFound) {
                            /*
                             * Found cat photos are loaded from the
                             * homepage folder when available.
                             */
                            $imageUrl = localCatImage(
                                $imageUrl,
                                (string)$cat['cat_name']
                            );
                        } elseif ($imageUrl === '') {
                            $imageKey = strtolower(
                                trim((string)$cat['cat_name'])
                            );

                            $imageUrl = $catImages[$imageKey]
                                ?? $defaultCatImage;
                        }

                        if ($isFoundCat) {
                            $adoptionUrl = 'adoption_form.php?source=found&found_id='
                                . (int)$cat['cat_id'];
                        } else {
                            $adoptionUrl = 'adoption_form.php?cat_id='
                                . (int)$cat['cat_id'];
                        }
                        ?>

                        <article
                            class="cat-card"
                            data-name="<?= e(strtolower($cat['cat_name'])) ?>"
                            data-age="<?= is_numeric($cat['age']) ? (float)$cat['age'] : 9999 ?>"
                            data-gender="<?= e(strtolower($cat['gender'])) ?>"
                            data-description="<?= e(strtolower($cat['description'])) ?>"
                            data-id="<?= (int)$cat['sort_id'] ?>"
                            data-source="<?= e($cat['source']) ?>"
                        >

                            <div class="cat-picture">

                                <?php if ($imageUrl !== ''): ?>

                                    <img
                                        class="cat-photo"
                                        src="<?= e($imageUrl) ?>"
                                        alt="Photo of <?= e($cat['cat_name']) ?>"
                                        loading="lazy"
                                        onerror="this.style.display='none';this.nextElementSibling.hidden=false"
                                    >

                                    <div class="no-cat-image" hidden>
                                        <span>🐱</span>
                                        <small>Photo unavailable</small>
                                    </div>

                                <?php else: ?>

                                    <div class="no-cat-image">
                                        <span>🐱</span>
                                        <small>Photo unavailable</small>
                                    </div>

                                <?php endif; ?>

                                <span class="favorite" aria-hidden="true">♡</span>
                            </div>

                            <div class="cat-details">

                                <div class="cat-name-row">
                                    <h3><?= e($cat['cat_name']) ?></h3>

                                    <span class="gender-pill">
                                        <?= e($cat['gender']) ?>
                                    </span>
                                </div>

                                <div class="cat-meta">
                                    <p>
                                        <strong>Age:</strong>
                                        <?= e($cat['age']) ?>
                                        <?= is_numeric($cat['age']) ? ' year(s)' : '' ?>
                                    </p>

                                    <p>
                                        <strong>Gender:</strong>
                                        <?= e($cat['gender']) ?>
                                    </p>

                                    <p>
                                        <strong>Cat ID:</strong>
                                        #<?= $isFoundCat ? 'F' : '' ?><?= (int)$cat['cat_id'] ?>
                                    </p>
                                </div>

                                <p class="cat-description">
                                    <?= e(
                                        $cat['description']
                                        ?: 'A lovely cat waiting for a caring forever home.'
                                    ) ?>
                                </p>

                                <?php if ($fromFound): ?>
                                    <p class="cat-status">
                                        ♥ Resolved · Ready for adoption
                                    </p>
                                <?php else: ?>
                                    <p class="cat-status">
                                        ♥ Available for adoption
                                    </p>
                                <?php endif; ?>

                                <a
                                    class="apply-button"
                                    href="<?= e($adoptionUrl) ?>"
                                >
                                    <span>
                                        <span class="paw-button" aria-hidden="true">🐾</span>
                                        &nbsp; Apply to Adopt
                                    </span>

                                    <span aria-hidden="true">›</span>
                                </a>

                            </div>
                        </article>

                    <?php endforeach; ?>

                    <div class="empty-message" id="no-search-results" hidden>
                        <h3>No matching cats found</h3>
                        <p>Try another name, age, gender or description.</p>
                    </div>

                <?php else: ?>

                    <div class="empty-message">
                        <h3>No cats available right now</h3>
                        <p>
                            Please check back later for new furry friends.
                        </p>
                    </div>

                <?php endif; ?>

            </div>
        </section>
    </main>
</div>

<footer class="footer">
    Made with ♥ for every cat looking for a loving home · Purrfect Home
</footer>

<script>
const searchInput = document.getElementById('cat-search');
const searchForm = document.getElementById('cat-search-form');
const sortSelect = document.getElementById('sort-cats');
const catGrid = document.getElementById('cat-grid');
const noResults = document.getElementById('no-search-results');

function updateCats() {
    if (!catGrid) return;

    const query = (searchInput?.value || '').trim().toLowerCase();
    const cards = Array.from(
        catGrid.querySelectorAll('.cat-card')
    );

    let visibleCount = 0;

    cards.forEach(card => {
        const text = [
            card.dataset.name,
            card.dataset.age,
            card.dataset.gender,
            card.dataset.description,
            card.dataset.id,
            card.dataset.source
        ].join(' ').toLowerCase();

        const matches = text.includes(query);

        card.hidden = !matches;

        if (matches) {
            visibleCount++;
        }
    });

    const sortBy = sortSelect?.value || 'newest';

    cards.sort((a, b) => {
        if (sortBy === 'name') {
            return (a.dataset.name || '').localeCompare(
                b.dataset.name || ''
            );
        }

        if (sortBy === 'age') {
            return Number(a.dataset.age) - Number(b.dataset.age);
        }

        return Number(b.dataset.id) - Number(a.dataset.id);
    });

    cards.forEach(card => {
        catGrid.insertBefore(card, noResults || null);
    });

    if (noResults) {
        noResults.hidden = visibleCount !== 0;
        catGrid.appendChild(noResults);
    }
}

searchForm?.addEventListener('submit', event => {
    event.preventDefault();
    updateCats();
});

searchInput?.addEventListener('input', updateCats);
sortSelect?.addEventListener('change', updateCats);
</script>

</body>
</html>
