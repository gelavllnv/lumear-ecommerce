document.addEventListener('DOMContentLoaded', () => {
    const slider = document.getElementById('heroSlider');
    const track = document.getElementById('heroTrack');
    const slides = [...document.querySelectorAll('.lumear-home .hero-slide')];
    const dots = [...document.querySelectorAll('#heroPagination button')];
    const progress = document.getElementById('heroProgress');
    const counter = document.getElementById('heroSlideCounter');

    if (!slider || !track || slides.length < 2) return;

    let activeIndex = 0;
    let timer;
    const duration = 6000;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const show = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        track.style.transform = `translate3d(-${activeIndex * 100}%, 0, 0)`;
        slides.forEach((slide, slideIndex) => {
            const active = slideIndex === activeIndex;
            slide.classList.toggle('active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });
        dots.forEach((dot, dotIndex) => dot.classList.toggle('active', dotIndex === activeIndex));
        if (counter) counter.textContent = `${String(activeIndex + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
    };

    const stop = () => {
        window.clearTimeout(timer);
        progress?.classList.remove('running');
    };

    const play = () => {
        stop();
        if (reduceMotion || document.hidden) return;
        void progress?.offsetWidth;
        progress?.classList.add('running');
        timer = window.setTimeout(() => {
            show(activeIndex + 1);
            play();
        }, duration);
    };

    document.getElementById('heroPrev')?.addEventListener('click', () => { show(activeIndex - 1); play(); });
    document.getElementById('heroNext')?.addEventListener('click', () => { show(activeIndex + 1); play(); });
    dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); play(); }));
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : play());

    show(0);
    play();
});
