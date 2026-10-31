document.addEventListener("DOMContentLoaded", function () {
    // Login elements
    const studentTab = document.getElementById("studentTab");
    const adminTab = document.getElementById("adminTab");
    const loginForm = document.getElementById("loginForm");
    const roleInput = document.getElementById("role");

    const identityLabel = document.getElementById("identityLabel");
    const identityInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const togglePassword = document.getElementById("togglePassword");
    const submitButton = document.getElementById("submitButton");
    const errorMessage = document.getElementById("errorMessage");

    // Student / Admin selection
    function setRole(role) {
        const isStudent = role === "student";

        roleInput.value = role;

        studentTab.classList.toggle("active", isStudent);
        adminTab.classList.toggle("active", !isStudent);

        identityLabel.textContent = isStudent
            ? "Email Address"
            : "Username";

        identityInput.type = isStudent ? "email" : "text";
        identityInput.name = isStudent ? "email" : "username";
        identityInput.placeholder = isStudent
            ? "Enter your email address"
            : "Enter your admin username";

        identityInput.autocomplete = "username";

        submitButton.textContent = isStudent
            ? "Login as Student"
            : "Login as Admin";

        identityInput.value = "";
        passwordInput.value = "";
        errorMessage.textContent = "";

        // Reset password visibility
        passwordInput.type = "password";
        togglePassword.textContent = "Show";

        submitButton.disabled = false;
    }

    studentTab.addEventListener("click", function () {
        setRole("student");
    });

    adminTab.addEventListener("click", function () {
        setRole("admin");
    });

    document.getElementById("userPortal").addEventListener("click", function () {
        setRole("student");
    });

    document.getElementById("adminPortal").addEventListener("click", function () {
        setRole("admin");
    });

    // Show / hide password
    togglePassword.addEventListener("click", function () {
        const isHidden = passwordInput.type === "password";

        passwordInput.type = isHidden ? "text" : "password";
        togglePassword.textContent = isHidden ? "Hide" : "Show";
    });

    // Show errors returned by login.php
    const params = new URLSearchParams(window.location.search);
    const error = params.get("error");
    const requestedRole = params.get("type");

    setRole(requestedRole === "admin" ? "admin" : "student");

    if (error === "invalid") {
        errorMessage.textContent =
            "Incorrect username/email or password. Please try again.";
    } else if (error === "empty") {
        errorMessage.textContent =
            "Please enter your username/email and password.";
    } else if (error === "database") {
        errorMessage.textContent =
            "A database error occurred. Please check your database connection.";
    }

    // Prevent empty submissions
    loginForm.addEventListener("submit", function (event) {
        if (!identityInput.value.trim() || !passwordInput.value) {
            event.preventDefault();
            errorMessage.textContent =
                "Please enter your username/email and password.";
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = "Logging in...";
    });

    // Background music
    const music = document.getElementById("purrBackgroundMusic");
    const musicButton = document.getElementById("purrMusicButton");
    const musicStatus = document.getElementById("purrMusicStatus");

    musicButton.addEventListener("click", async function () {
        if (music.paused) {
            try {
                await music.play();
            } catch (error) {
                musicStatus.textContent =
                    "Please check the music file or try again.";
                musicStatus.style.display = "inline-block";
            }
        } else {
            music.pause();
        }
    });

    music.addEventListener("play", function () {
        musicButton.textContent = "Ⅱ";
        musicButton.title = "Pause music";
        musicButton.setAttribute("aria-label", "Pause music");
        musicStatus.style.display = "none";
    });

    music.addEventListener("pause", function () {
        musicButton.textContent = "♫";
        musicButton.title = "Play music";
        musicButton.setAttribute("aria-label", "Play music");
        musicStatus.textContent = "Music is paused ♡";
    });

    music.addEventListener("error", function () {
        musicStatus.textContent = "Music file not found";
        musicStatus.style.display = "inline-block";
    });
});