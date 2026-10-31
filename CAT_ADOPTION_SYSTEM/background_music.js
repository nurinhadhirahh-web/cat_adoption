document.addEventListener("DOMContentLoaded", function () {
    const music = document.getElementById("backgroundMusic");
    const button = document.getElementById("musicButton");

    if (!music || !button) return;

    function showPlaying() {
        button.classList.add("is-playing");
        button.title = "Pause music";
        button.setAttribute("aria-label", "Pause background music");
    }

    function showPaused() {
        button.classList.remove("is-playing");
        button.title = "Play music";
        button.setAttribute("aria-label", "Play background music");
    }

    button.addEventListener("click", async function () {
        if (music.paused) {
            try {
                await music.play();
                showPlaying();
            } catch (error) {
                showPaused();
                console.error("Unable to play background music. Check the MP3 filename/path.", error);
                alert("Music tidak dapat dimainkan. Pastikan fail background_music(1).mp3 berada dalam folder yang sama dengan login HTML.");
            }
        } else {
            music.pause();
            showPaused();
        }
    });

    music.addEventListener("play", showPlaying);
    music.addEventListener("pause", showPaused);
    music.addEventListener("error", function () {
        console.error("Audio file could not be loaded. Check background_music(1).mp3.");
        showPaused();
    });
});
