
<?php
// Purrfect Home - About Us Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Purrfect Home</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink: #d45187;
            --pink-dark: #bd3e73;
            --pink-light: #fbeaf1;
            --pink-border: #f3dce6;
            --dark: #30232b;
            --muted: #806d79;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            color: var(--dark);
            background: var(--white);
            line-height: 1.7;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* HEADER */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            background-color: #fffafd;
            background-image: radial-gradient(
                #f8dce8 0.65px,
                transparent 0.65px
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
            min-width: 44px;
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
            font-weight: 700;
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
            transition: color 0.2s ease;
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

        .main-nav .logout-link {
            min-height: 38px;
            padding: 9px 15px;
            border: 1px solid #d94382;
            border-radius: 20px;
            background: #d94382;
            color: white;
            font-weight: 700;
        }

        .main-nav .logout-link:hover {
            background: #bd2868;
            border-color: #bd2868;
            color: white;
        }

        .menu-toggle {
            display: none;
            padding: 8px 12px;
            border: 1px solid var(--pink-border);
            border-radius: 8px;
            background: white;
            color: var(--pink-dark);
            font: inherit;
            cursor: pointer;
        }

        /* PAGE LAYOUT */
        .page-shell {
            max-width: 1120px;
            margin: auto;
            padding: 44px 28px 65px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 35px;
            color: #a08f99;
            font-size: 12px;
        }

        .breadcrumb a:hover {
            color: var(--pink);
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 15px;
            color: var(--pink);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        /* HERO SECTION */
        .hero {
            display: grid;
            grid-template-columns: 1fr 0.9fr;
            align-items: center;
            gap: 65px;
            padding: 15px 0 65px;
        }

        .hero-copy h1 {
            margin-bottom: 23px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(39px, 4.5vw, 59px);
            line-height: 1.14;
            letter-spacing: -1.5px;
        }

        .hero-copy h1 span {
            display: block;
            color: var(--pink);
        }

        .hero-copy > p {
            max-width: 490px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.95;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 22px;
            margin-top: 27px;
        }

        /* BUTTONS */
        .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 19px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(110deg, #e36a96, #cb4779);
            color: white;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 5px 14px rgba(203, 71, 121, 0.16);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(203, 71, 121, 0.22);
        }

        .text-btn {
            color: var(--pink-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .text-btn:hover {
            text-decoration: underline;
        }

        .hero-note {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 30px;
            color: #a08f99;
            font-size: 11px;
        }

        .hero-note span {
            color: var(--pink);
            font-size: 17px;
        }

        /* HERO IMAGE */
        .hero-visual {
            position: relative;
            padding: 0 5px 20px 0;
        }

        .hero-image {
            display: block;
            width: 100%;
            height: 400px;
            object-fit: cover;
            object-position: center;
            border-radius: 48% 48% 17px 17px;
        }

        .image-decoration {
            position: absolute;
            top: 14px;
            right: -8px;
            color: #e9a4c0;
            font-size: 34px;
        }

        .image-card {
            position: absolute;
            bottom: 0;
            left: -30px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 17px;
            border: 1px solid #f6e8ee;
            border-radius: 13px;
            background: white;
            box-shadow: 0 10px 35px rgba(79, 43, 59, 0.09);
        }

        .image-card-icon {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            border-radius: 12px;
            background: var(--pink-light);
            color: var(--pink);
            font-size: 23px;
        }

        .image-card strong,
        .image-card small {
            display: block;
        }

        .image-card strong {
            font-size: 12px;
        }

        .image-card small {
            margin-top: 3px;
            color: var(--muted);
            font-size: 10px;
        }

        /* SECTION HEADINGS */
        .section-heading {
            max-width: 600px;
            margin: 0 auto 32px;
            text-align: center;
        }

        .section-heading h2 {
            margin-bottom: 12px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 3vw, 38px);
            line-height: 1.3;
        }

        .section-heading h2 span {
            color: var(--pink);
        }

        .section-heading p {
            color: var(--muted);
            font-size: 13px;
        }

        /* OUR MISSION */
        .mission-section {
            margin-bottom: 65px;
            padding: 48px 35px;
            border: 1px solid #f8e8ef;
            border-radius: 20px;
            background: #fff8fb;
        }

        .mission-content {
            max-width: 760px;
            margin: auto;
            text-align: center;
        }

        .mission-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            margin: 0 auto 17px;
            border: 1px solid var(--pink-border);
            border-radius: 17px;
            background: white;
            color: var(--pink);
            font-size: 27px;
        }

        .mission-content h2 {
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
            font-size: 32px;
        }

        .mission-content h2 span {
            color: var(--pink);
        }

        .mission-content p {
            max-width: 680px;
            margin: auto;
            color: var(--muted);
            font-size: 13px;
            line-height: 2;
        }

        /* WHAT WE OFFER */
        .offer-section {
            padding-bottom: 65px;
        }

        .offer-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 20px;
        }

        .offer-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 27px 23px;
            border: 1px solid #f1e3e9;
            border-radius: 15px;
            background: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .offer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(81, 42, 61, 0.07);
        }

        .offer-icon {
            display: grid;
            place-items: center;
            width: 47px;
            height: 47px;
            margin-bottom: 19px;
            border-radius: 14px;
            background: var(--pink-light);
            color: var(--pink);
            font-size: 24px;
        }

        .offer-card h3 {
            margin-bottom: 10px;
            font-family: 'Playfair Display', serif;
            font-size: 19px;
        }

        .offer-card p {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.9;
        }

        /* BUTTONS INSIDE OFFER CARDS */
        .offer-card .primary-btn {
            width: 100%;
            margin-top: auto;
            padding: 11px 12px;
            text-align: center;
        }

        /* CLOSING MESSAGE */
        .closing-section {
            position: relative;
            overflow: hidden;
            padding: 42px 25px;
            border-radius: 18px;
            background: var(--pink-light);
            text-align: center;
        }

        .closing-heart {
            margin-bottom: 7px;
            color: var(--pink);
            font-size: 28px;
        }

        .closing-section h2 {
            margin-bottom: 10px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(25px, 3vw, 35px);
            line-height: 1.4;
        }

        .closing-section h2 span {
            color: var(--pink);
        }

        .closing-section p {
            max-width: 560px;
            margin: 0 auto 22px;
            color: var(--muted);
            font-size: 13px;
        }

        /* FOOTER */
        .site-footer {
            padding: 23px 20px;
            border-top: 1px solid var(--pink-border);
            color: #a08f99;
            font-size: 11px;
            text-align: center;
        }

        .site-footer span {
            color: var(--pink);
        }

        /* TABLET */
        @media (max-width: 900px) {
            .hero {
                gap: 35px;
            }

            .hero-image {
                height: 340px;
            }

            .hero-copy h1 {
                font-size: 43px;
            }
        }

        /* MOBILE NAVIGATION */
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
                flex: 0 0 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 8px 16px 14px;
                border: 1px solid #f0dce6;
                background: #fffafd;
                box-shadow: 0 12px 18px rgba(80, 40, 55, 0.06);
            }

            .main-nav.open {
                display: flex;
            }

            .nav-link {
                justify-content: flex-start;
                min-height: 40px;
                padding: 10px 4px;
                border-bottom: 1px solid #f8edf2;
                white-space: normal;
            }

            .profile-link {
                padding: 10px 4px;
                background: transparent;
            }

            .main-nav .logout-link {
                justify-content: center;
                align-self: flex-start;
                margin-top: 8px;
                padding: 9px 15px;
            }
        }

        /* SMALL SCREENS */
        @media (max-width: 700px) {
            .page-shell {
                padding: 30px 19px 45px;
            }

            .breadcrumb {
                margin-bottom: 23px;
            }

            .hero {
                grid-template-columns: 1fr;
                gap: 36px;
                padding: 10px 0 48px;
            }

            .hero-copy h1 {
                font-size: 43px;
            }

            .hero-image {
                height: 330px;
                max-width: 420px;
                margin: auto;
            }

            .hero-visual {
                width: 100%;
                max-width: 420px;
                margin: auto;
            }

            .image-card {
                left: -5px;
            }

            .mission-section {
                margin-bottom: 48px;
                padding: 35px 21px;
            }

            .mission-content h2 {
                font-size: 28px;
            }

            .offer-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .offer-card {
                padding: 23px;
            }

            .offer-section {
                padding-bottom: 45px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<header class="site-header" id="home">
    <div class="nav-wrap">

        <a class="brand" href="homepage.html"
           aria-label="Purrfect Home homepage">

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

            <a class="nav-link" href="found_cat.php">Found Cats</a>

            <a class="nav-link active" href="about_us.php">About Us</a>

            <a class="nav-link profile-link" href="my_application.php">
                <span>My Applications</span>
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
        <span>About Us</span>
    </div>

    <!-- HERO SECTION -->
    <section class="hero">

        <div class="hero-copy">

            <span class="eyebrow">♡ &nbsp; GET TO KNOW US</span>

            <h1>
                A little love.
                <span>A forever home.</span>
            </h1>

            <p>
                Welcome to Purrfect Home, a cat adoption and rescue
                platform created to help cats in need find safe,
                caring and loving homes.
            </p>

            <p style="margin-top: 12px;">
                We believe every cat deserves a second chance,
                a caring family and a place to call home.
                Together, we can make a difference, one paw at a time.
            </p>

            <div class="hero-actions">

                <a class="primary-btn" href="adopt_cat.php">
                    Meet Our Cats &nbsp; →
                </a>

                <a class="text-btn" href="#our-mission">
                    Discover Our Mission &nbsp; ↗
                </a>

            </div>

            <div class="hero-note">
                <span>♡</span>
                Every paw deserves a second chance.
            </div>

        </div>

        <div class="hero-visual">

            <span class="image-decoration" aria-hidden="true">✳</span>

            <img
                class="hero-image"
                src="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEje8BgmdxhcQPpotZqjyXe-ns9rNdcWAs_RGRxg7q9in3dj6-zZaXCO0z7ZCSWQ6PiMXhO8KvO2hAQt-MaysbKHGcDIGwmMNEk9EAyZe2b5Zt1OZhWe5KhiOon4rUiG2_-ZmHtzHf4weQM/w1200-h630-p-k-no-nu/Penting%21+Tips+Memilih+Makanan+Kucing+Paling+Berkualitas.jpg"
                alt="Cat eating cat food">

            <div class="image-card">

                <span class="image-card-icon">♡</span>

                <span>
                    <strong>Love starts here</strong>
                    <small>Find your new best friend</small>
                </span>

            </div>

        </div>

    </section>

    <!-- OUR MISSION -->
    <section class="mission-section" id="our-mission">

        <div class="mission-content">

            <div class="mission-icon" aria-hidden="true">♡</div>

            <span class="eyebrow">WHY WE EXIST</span>

            <h2>Our <span>Mission</span></h2>

            <p>
                Our mission is to connect cats with loving families,
                encourage responsible pet adoption and raise awareness
                about cats in need. Through Purrfect Home, we aim to
                make information about cat adoption and found cats
                easier to access, helping more cats find the care
                and attention they deserve.
            </p>

        </div>

    </section>

    <!-- WHAT WE OFFER -->
    <section class="offer-section">

        <div class="section-heading">

            <span class="eyebrow">HOW WE MAKE A DIFFERENCE</span>

            <h2>What We <span>Offer</span></h2>

            <p>
                Simple ways to help cats find safety, care and
                their forever homes.
            </p>

        </div>

        <div class="offer-grid">

            <!-- CAT ADOPTION CARD -->
            <article class="offer-card">

                <div class="offer-icon" aria-hidden="true">♡</div>

                <h3>Cat Adoption</h3>

                <p>
                    Explore cats available for adoption and discover
                    a potential companion to welcome into your home.
                    Every adoption can be the beginning of a beautiful
                    friendship.
                </p>

                <!-- BUTTON: GO TO ADOPT CAT PAGE -->
                <a class="primary-btn" href="adopt_cat.php">
                    Explore Cats &nbsp; →
                </a>

            </article>

            <!-- FOUND CATS CARD -->
            <article class="offer-card">

                <div class="offer-icon" aria-hidden="true">⌕</div>

                <h3>Found Cats</h3>

                <p>
                    View information about cats that have been found.
                    Sharing accurate details can help their owners
                    identify them and support their safe return home.
                </p>

                <!-- BUTTON: GO TO FOUND CATS PAGE -->
                <a class="primary-btn" href="found_cat.php">
                    View Found Cats &nbsp; →
                </a>

            </article>

            <!-- FOREVER HOMES CARD -->
            <article class="offer-card">

                <div class="offer-icon" aria-hidden="true">⌂</div>

                <h3>Forever Homes</h3>

                <p>
                    Encourage kindness, responsible pet ownership
                    and compassion towards cats by helping them
                    find a safe and caring environment.
                </p>

            </article>

        </div>

    </section>

    <!-- CLOSING MESSAGE -->
    <section class="closing-section">

        <div class="closing-heart" aria-hidden="true">♡</div>

        <h2>
            Every Cat Deserves
            <span>a Loving Home.</span>
        </h2>

        <p>
            A small act of kindness can change a cat's life forever.
            Thank you for being part of the Purrfect Home community.
        </p>

        <a class="primary-btn" href="adopt_cat.php">
            Find Your Furry Friend &nbsp; →
        </a>

    </section>

</main>

<!-- FOOTER -->
<footer class="site-footer">

    <p>
        © <?php echo date('Y'); ?> Purrfect Home.
        Made with <span>♡</span> for every cat.
    </p>

</footer>

<!-- MOBILE MENU SCRIPT -->
<script>
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');

    menuToggle.addEventListener('click', function () {
        const isOpen = mainNav.classList.toggle('open');

        menuToggle.setAttribute(
            'aria-expanded',
            String(isOpen)
        );
    });

    mainNav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            mainNav.classList.remove('open');

            menuToggle.setAttribute(
                'aria-expanded',
                'false'
            );
        });
    });
</script>

</body>
</html>
