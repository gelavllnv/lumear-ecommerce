/* =========================================================
   LUMEAR - BUYER AUTHENTICATION
   File: public/js/buyer/auth.js
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       PASSWORD VISIBILITY
       ===================================================== */

    const passwordInput = document.getElementById("password");
    const passwordToggle = document.getElementById("passwordToggle");

    const eyeOpen = document.getElementById("eyeOpen");
    const eyeClosed = document.getElementById("eyeClosed");

    if (passwordInput && passwordToggle) {

        passwordToggle.addEventListener("click", function () {

            const passwordIsHidden =
                passwordInput.getAttribute("type") === "password";

            if (passwordIsHidden) {

                passwordInput.setAttribute("type", "text");

                if (eyeOpen) {
                    eyeOpen.style.display = "none";
                }

                if (eyeClosed) {
                    eyeClosed.style.display = "block";
                }

                passwordToggle.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.setAttribute("type", "password");

                if (eyeOpen) {
                    eyeOpen.style.display = "block";
                }

                if (eyeClosed) {
                    eyeClosed.style.display = "none";
                }

                passwordToggle.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }
        });
    }


    /* =====================================================
       DEMO LOGIN

       Your project does not have real Laravel authentication
       yet. This preserves the behavior your old login page had.

       It saves the entered email/contact number and redirects
       the buyer to /home.
       ===================================================== */

    const loginForm = document.getElementById("loginForm");

    if (loginForm) {

        loginForm.addEventListener("submit", function (event) {

            event.preventDefault();

            const identityInput =
                document.getElementById("loginIdentity");

            const loginButton =
                document.getElementById("loginButton");

            if (!identityInput) {
                return;
            }

            const identity = identityInput.value.trim();

            if (identity === "") {
                identityInput.focus();
                return;
            }

            localStorage.setItem(
                "lumear_identity",
                identity
            );


            /* Small loading animation */

            if (loginButton) {

                loginButton.disabled = true;

                loginButton.innerHTML =
                    '<span>Signing in...</span>';
            }


            /* Redirect after short animation */

            setTimeout(function () {

                window.location.href =
                    loginForm.dataset.homeUrl;

            }, 550);
        });
    }


    /* =====================================================
       GOOGLE DEMO LOGIN
       ===================================================== */

    const googleButton =
        document.getElementById("googleLogin");

    if (googleButton) {

        googleButton.addEventListener("click", function () {

            localStorage.setItem(
                "lumear_identity",
                "Google Account"
            );

            googleButton.innerHTML =
                "<span>Connecting...</span>";

            setTimeout(function () {

                window.location.href =
                    googleButton.dataset.homeUrl;

            }, 500);
        });
    }


    /* =====================================================
       SUBTLE MOUSE MOVEMENT

       The product cards move slightly according to the mouse.
       Desktop only.
       ===================================================== */

    const showcase =
        document.getElementById("authShowcase");

    const floatingCards =
        document.querySelectorAll(".product-float");

    if (
        showcase &&
        floatingCards.length > 0 &&
        window.matchMedia("(min-width: 851px)").matches
    ) {

        showcase.addEventListener(
            "mousemove",
            function (event) {

                const rectangle =
                    showcase.getBoundingClientRect();

                const mouseX =
                    event.clientX - rectangle.left;

                const mouseY =
                    event.clientY - rectangle.top;

                const centerX =
                    rectangle.width / 2;

                const centerY =
                    rectangle.height / 2;

                const moveX =
                    (mouseX - centerX) / centerX;

                const moveY =
                    (mouseY - centerY) / centerY;


                floatingCards.forEach(
                    function (card, index) {

                        const strength =
                            (index + 1) * 3;

                        card.style.marginLeft =
                            moveX * strength + "px";

                        card.style.marginTop =
                            moveY * strength + "px";
                    }
                );
            }
        );


        showcase.addEventListener(
            "mouseleave",
            function () {

                floatingCards.forEach(
                    function (card) {

                        card.style.marginLeft = "0";
                        card.style.marginTop = "0";
                    }
                );
            }
        );
    }

});