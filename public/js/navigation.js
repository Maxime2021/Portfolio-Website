document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    const toggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.site-nav');

    if (!header || !toggle || !navigation) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = header.classList.toggle('is-open');

        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    navigation.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            header.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
});