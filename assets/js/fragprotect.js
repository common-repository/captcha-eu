document.addEventListener('DOMContentLoaded', () => {
    const captchaMailhideRoots = document.querySelectorAll(".captcha_mailhide_root");

    const handleClick = async (el) => {
        if (el.dataset.clicked === "true") return;
        el.dataset.clicked = "true";
        const cryptedPayload = el.dataset.payload;
        const captchaMailhideSlider = el.querySelector(".captcha_mailhide_slider");
        const inline_logo = el.querySelector(".inline_logo")

        // Activate slider
        captchaMailhideSlider.classList.remove('inactive');
        el.querySelector(".screen-reader-status").textContent = 'captcha.eu is running....';

        try {
            const sol = await KROT.getSolution();
            const payload = {
                cpt: JSON.stringify(sol),
                crypted: cryptedPayload
            };

            const response = await jQuery.ajax({
                url: cptFragAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpt_decrypt',
                    data: payload
                }
            });

            // Deactivate slider
            captchaMailhideSlider.classList.add('inactive');
            el.querySelector(".screen-reader-status").textContent = '';
            const jso = JSON.parse(response);
            if (jso.status !== "OK") {
                alert("Failed");
            } else {
                // Update HTML with result
                el.querySelector(".captcha_real_mail").innerHTML = jso.result;
                el.querySelector(".captcha_real_mail").removeAttribute('aria-hidden');
                el.querySelector(".captcha_real_mail").classList.remove("captcha_real_mail")
                
                inline_logo.style.display = "none";
            }
        } catch (error) {
            // Handle error
            captchaMailhideSlider.classList.add('inactive');
            alert('Failed');
        }
    };

    // Add event listener for both click and keypress events
    captchaMailhideRoots.forEach(el => {
        el.setAttribute("tabindex", "0");
        el.addEventListener("click", () => handleClick(el));
        el.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                handleClick(el);
            }
        });
    });

});
