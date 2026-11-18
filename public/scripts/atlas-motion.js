/*
  atlas-motion.js
  ---------------
  Révélations au scroll et micro-parallaxe, sans bibliothèque externe.
*/

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealItems = document.querySelectorAll('[data-reveal], .reveal');

if (reduceMotion) {
    revealItems.forEach((item) => item.classList.add('is-inview'));
} else if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-inview');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.08 }
    );

    revealItems.forEach((item) => observer.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-inview'));
}

// Parallaxe très légère sur les éléments décoratifs qui le demandent.
const parallaxItems = Array.from(document.querySelectorAll('[data-parallax]'));

if (!reduceMotion && parallaxItems.length > 0 && window.matchMedia('(pointer: fine)').matches) {
    let queued = false;

    const renderParallax = () => {
        queued = false;
        const viewport = window.innerHeight;

        parallaxItems.forEach((item) => {
            const rect = item.getBoundingClientRect();
            const depth = Number(item.getAttribute('data-parallax')) || 0.08;
            const offset = (rect.top + rect.height / 2 - viewport / 2) * depth;
            item.style.setProperty('--parallax-y', `${offset.toFixed(2)}px`);
        });
    };

    const onScroll = () => {
        if (!queued) {
            queued = true;
            window.requestAnimationFrame(renderParallax);
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    renderParallax();
}
