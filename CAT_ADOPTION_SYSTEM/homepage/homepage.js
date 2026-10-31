document.addEventListener("DOMContentLoaded", function () {
    const menuButton = document.querySelector(".menu-toggle");
    const navMenu = document.querySelector(".main-nav");
    const toast = document.getElementById("toast");

    // Paparkan mesej
    function showMessage(message) {
        if (!toast) {
            alert(message);
            return;
        }

        toast.textContent = message;
        toast.classList.add("show");

        clearTimeout(window.purrfectToastTimer);
        window.purrfectToastTimer = setTimeout(function () {
            toast.classList.remove("show");
        }, 3000);
    }

    // Menu untuk telefon
    if (menuButton && navMenu) {
        menuButton.addEventListener("click", function () {
            const isOpen = navMenu.classList.toggle("open");
            menuButton.setAttribute("aria-expanded", String(isOpen));
        });
    }

    // Fungsi scroll ke bahagian homepage
    function goToSection(id) {
        const section = document.getElementById(id);

        if (section) {
            section.scrollIntoView({ behavior: "smooth" });
        } else {
            showMessage("Bahagian ini belum tersedia.");
        }
    }

    // Navigasi Home, Adopt a Cat, Found Cats, About Us, Profile
    document.querySelectorAll(".main-nav a, .footer-column a, .brand")
        .forEach(function (link) {
            link.addEventListener("click", function (event) {
                const href = link.getAttribute("href");

                if (href && href.startsWith("#")) {
                    const target = document.querySelector(href);

                    if (target) {
                        event.preventDefault();
                        target.scrollIntoView({ behavior: "smooth" });
                    }
                }

                if (navMenu && navMenu.classList.contains("open")) {
                    navMenu.classList.remove("open");
                    menuButton?.setAttribute("aria-expanded", "false");
                }
            });
        });

    // Butang dan pautan yang mempunyai data-action
    document.querySelectorAll("[data-action]").forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();

            const action = button.dataset.action;

            switch (action) {
                case "adopt":
                    window.location.href = "adopt_cat.php";
                    break;

                case "report":
                    showMessage(
                        "Borang laporan kucing akan disediakan pada halaman Report a Found Cat."
                    );
                    break;

                case "applications":
                    showMessage(
                        "Halaman My Applications akan disambungkan selepas halaman PHP dibuat."
                    );
                    break;

                case "cat-details":
                    showMessage(
                        "Maklumat " + button.dataset.cat +
                        " akan dipaparkan apabila halaman butiran kucing tersedia."
                    );
                    break;

                case "about":
                    goToSection("about");
                    break;

                default:
                    showMessage("Fungsi ini belum disediakan.");
            }
        });
    });

    // Butang Meet Our Cats
    document.querySelectorAll('a[href="#featured"]').forEach(function (link) {
        link.addEventListener("click", function (event) {
            event.preventDefault();
            goToSection("featured");
        });
    });

    // Butang Learn about us
    document.querySelectorAll('a[href="#about"]').forEach(function (link) {
        link.addEventListener("click", function (event) {
            event.preventDefault();
            goToSection("about");
        });
    });
        fetch("session_user.php")
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.logged_in) return;
            const navName = document.getElementById("navName");
            const heroName = document.getElementById("heroName");
            if (navName) navName.textContent = "Welcome, " + data.name;
            if (heroName) heroName.textContent = ", " + data.name.toUpperCase() + ",";
        })
        .catch(function () {});
});