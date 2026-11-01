(() => {
    const carousel = document.querySelector('[data-hero-carousel]');

    if (!carousel) {
        return;
    }

    const slides = Array.from(carousel.querySelectorAll('[data-hero-slide]'));
    const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));
    const previousButton = carousel.querySelector('[data-carousel-prev]');
    const nextButton = carousel.querySelector('[data-carousel-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeIndex = 0;
    let intervalId = null;

    const normaliseIndex = (index) => (index + slides.length) % slides.length;

    const playActiveVideo = () => {
        const activeVideo = slides[activeIndex]?.querySelector('video');

        if (activeVideo && !reducedMotion.matches) {
            activeVideo.play().catch(() => {
                // Some browsers require a visitor interaction before autoplay.
            });
        }
    };

    const showSlide = (index) => {
        activeIndex = normaliseIndex(index);

        slides.forEach((slide, slideIndex) => {
            const isActive = slideIndex === activeIndex;
            const video = slide.querySelector('video');

            slide.classList.toggle('is-active', isActive);
            slide.setAttribute('aria-hidden', String(!isActive));

            if (video && !isActive) {
                video.pause();
                video.currentTime = 0;
            }
        });

        dots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === activeIndex;
            dot.classList.toggle('is-active', isActive);
            dot.setAttribute('aria-selected', String(isActive));
        });

        playActiveVideo();
    };

    const stopRotation = () => {
        if (intervalId) {
            window.clearInterval(intervalId);
            intervalId = null;
        }
    };

    const startRotation = () => {
        stopRotation();

        if (!reducedMotion.matches && !document.hidden) {
            intervalId = window.setInterval(() => showSlide(activeIndex + 1), 7000);
        }
    };

    previousButton?.addEventListener('click', () => {
        showSlide(activeIndex - 1);
        startRotation();
    });

    nextButton?.addEventListener('click', () => {
        showSlide(activeIndex + 1);
        startRotation();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            showSlide(index);
            startRotation();
        });
    });

    carousel.addEventListener('mouseenter', stopRotation);
    carousel.addEventListener('mouseleave', startRotation);
    carousel.addEventListener('focusin', stopRotation);
    carousel.addEventListener('focusout', (event) => {
        if (!carousel.contains(event.relatedTarget)) {
            startRotation();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopRotation();
        } else {
            playActiveVideo();
            startRotation();
        }
    });

    reducedMotion.addEventListener?.('change', () => {
        if (reducedMotion.matches) {
            stopRotation();
            slides.forEach((slide) => slide.querySelector('video')?.pause());
        } else {
            playActiveVideo();
            startRotation();
        }
    });

    showSlide(0);
    startRotation();
})();
