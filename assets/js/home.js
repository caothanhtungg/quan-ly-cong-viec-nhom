document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('.home-header');
    const menuToggle = document.querySelector('[data-home-menu-toggle]');
    const menu = document.querySelector('[data-home-menu]');
    const revealItems = document.querySelectorAll('[data-home-reveal]');
    const highlightItems = document.querySelectorAll('[data-home-highlight]');
    const scrollProgress = document.querySelector('[data-home-scroll-progress]');
    const typewriter = document.querySelector('[data-home-typewriter]');
    const marqueeTrack = document.querySelector('.home-marquee-track');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let scrollTicking = false;
    let marqueeResizeTimer;

    const updateScrollEffects = () => {
        if (header) {
            header.classList.toggle('is-scrolled', window.scrollY > 18);
        }

        if (scrollProgress) {
            const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = scrollableHeight > 0 ? Math.min(window.scrollY / scrollableHeight, 1) : 0;
            scrollProgress.style.width = `${progress * 100}%`;
        }

        scrollTicking = false;
    };

    const requestScrollUpdate = () => {
        if (!scrollTicking) {
            window.requestAnimationFrame(updateScrollEffects);
            scrollTicking = true;
        }
    };

    const closeMenu = () => {
        if (!menu || !menuToggle) {
            return;
        }

        menu.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('home-menu-open');
    };

    const applyStagger = (selector, delayStep) => {
        document.querySelectorAll(selector).forEach((item, index) => {
            item.style.setProperty('--home-reveal-delay', `${index * delayStep}ms`);
        });
    };

    const prepareMarquee = () => {
        if (!marqueeTrack || marqueeTrack.children.length < 2) {
            return;
        }

        const firstGroup = marqueeTrack.children[0];
        const secondGroup = marqueeTrack.children[1];
        const template = marqueeTrack.itemTemplate || firstGroup.innerHTML;
        marqueeTrack.itemTemplate = template;
        firstGroup.innerHTML = template;

        let copyCount = 0;
        while (firstGroup.scrollWidth < window.innerWidth + 160 && copyCount < 8) {
            firstGroup.insertAdjacentHTML('beforeend', template);
            copyCount += 1;
        }

        secondGroup.innerHTML = firstGroup.innerHTML;
        marqueeTrack.classList.remove('is-running');
        window.requestAnimationFrame(() => marqueeTrack.classList.add('is-running'));
    };

    const startTypewriter = () => {
        if (!typewriter || reduceMotion) {
            return;
        }

        let words = [];

        try {
            words = JSON.parse(typewriter.dataset.words || '[]');
        } catch (error) {
            return;
        }

        if (!Array.isArray(words) || words.length < 2) {
            return;
        }

        let wordIndex = 0;
        let letterIndex = Array.from(words[0]).length;
        let deleting = false;

        const typeNextLetter = () => {
            const letters = Array.from(words[wordIndex]);
            typewriter.textContent = letters.slice(0, letterIndex).join('');

            if (!deleting && letterIndex === letters.length) {
                deleting = true;
                window.setTimeout(typeNextLetter, 1500);
                return;
            }

            if (deleting && letterIndex === 0) {
                deleting = false;
                wordIndex = (wordIndex + 1) % words.length;
                window.setTimeout(typeNextLetter, 260);
                return;
            }

            letterIndex += deleting ? -1 : 1;
            window.setTimeout(typeNextLetter, deleting ? 38 : 72);
        };

        window.setTimeout(typeNextLetter, 1500);
    };

    if (menu && menuToggle) {
        menuToggle.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('is-open');
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.body.classList.toggle('home-menu-open', isOpen);
        });

        menu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                closeMenu();
            }

            requestScrollUpdate();
            window.clearTimeout(marqueeResizeTimer);
            marqueeResizeTimer = window.setTimeout(prepareMarquee, 160);
        });
    }

    applyStagger('.home-feature-grid [data-home-reveal]', 80);
    applyStagger('.home-role-grid [data-home-reveal]', 90);
    prepareMarquee();

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.14,
        });

        const highlightObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-highlighted');
                    highlightObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.3,
        });

        revealItems.forEach((item) => revealObserver.observe(item));
        highlightItems.forEach((item) => highlightObserver.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
        highlightItems.forEach((item) => item.classList.add('is-highlighted'));
    }

    updateScrollEffects();
    startTypewriter();
    window.addEventListener('scroll', requestScrollUpdate, { passive: true });
});
