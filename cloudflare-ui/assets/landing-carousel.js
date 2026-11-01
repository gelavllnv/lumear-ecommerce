(() => {
    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) return;

    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const dots = [...carousel.querySelectorAll('[data-dot]')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let timer;

    const setSlide = (next) => {
        current = (next + slides.length) % slides.length;
        slides.forEach((slide, index) => {
            const active = index === current;
            slide.classList.toggle('active', active);
            slide.setAttribute('aria-hidden', String(!active));
            const video = slide.querySelector('video');
            if (!active) {
                video?.pause();
                if (video) video.currentTime = 0;
            }
        });
        dots.forEach((dot, index) => {
            const active = index === current;
            dot.classList.toggle('selected', active);
            dot.setAttribute('aria-selected', String(active));
        });
        if (!reduceMotion.matches) slides[current].querySelector('video')?.play().catch(() => {});
    };

    const stop = () => window.clearInterval(timer);
    const start = () => {
        stop();
        if (!reduceMotion.matches && !document.hidden) timer = window.setInterval(() => setSlide(current + 1), 7000);
    };

    carousel.querySelector('[data-prev]')?.addEventListener('click', () => { setSlide(current - 1); start(); });
    carousel.querySelector('[data-next]')?.addEventListener('click', () => { setSlide(current + 1); start(); });
    dots.forEach((dot, index) => dot.addEventListener('click', () => { setSlide(index); start(); }));
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', (event) => { if (!carousel.contains(event.relatedTarget)) start(); });
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    reduceMotion.addEventListener?.('change', () => reduceMotion.matches ? stop() : start());

    setSlide(0);
    start();
})();
