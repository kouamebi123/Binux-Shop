/* Binux Shop — comportements de l'interface. Sans dépendance ; tout reste utilisable sans ce script. */
(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ----- Images : si un visuel ne se charge pas, l'initiale de l'article reste visible ----- */
    const markBroken = (img) => img.classList.add('is-broken');

    $$('img[data-img]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) {
            markBroken(img);
        }
    });

    document.addEventListener('error', (event) => {
        if (event.target instanceof HTMLImageElement && event.target.matches('[data-img]')) {
            markBroken(event.target);
        }
    }, true);

    /* ----- Messages : fermeture ----- */
    document.addEventListener('click', (event) => {
        const close = event.target.closest('[data-flash-close]');
        if (close) {
            close.closest('[data-flash]')?.remove();
        }
    });

    /* ----- Menus déroulants (<details>) : un seul ouvert, fermeture au clic extérieur et à Échap ----- */
    const menus = $$('[data-menu]');

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (menu.open && !menu.contains(event.target)) {
                menu.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        menus.forEach((menu) => {
            if (menu.open) {
                menu.open = false;
                $('summary', menu)?.focus();
            }
        });
    });

    /* ----- Menu sur petit écran ----- */
    const navToggle = $('[data-nav-toggle]');
    const nav = $('[data-nav]');

    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', String(open));
        });
    }

    /* ----- En-tête : habillé de nuit tant que la vitrine est dessous ----- */
    const header = $('[data-header]');
    const vitrine = $('[data-vitrine]');

    if (header && vitrine && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            header.classList.toggle('site-header--night', entry.isIntersecting);
        }, { rootMargin: `-${header.offsetHeight}px 0px 0px 0px` }).observe(vitrine);
    }

    /* ----- Vitrine : défilement par boutons et à la souris ----- */
    const rail = $('[data-rail]');

    if (rail) {
        const railNav = $('[data-rail-nav]');
        const prev = $('[data-rail-prev]');
        const next = $('[data-rail-next]');
        const step = () => Math.max(240, rail.clientWidth * 0.7);

        const syncButtons = () => {
            const max = rail.scrollWidth - rail.clientWidth;
            if (railNav) {
                railNav.hidden = max < 8;
            }
            if (prev && next) {
                prev.disabled = rail.scrollLeft < 8;
                next.disabled = rail.scrollLeft > max - 8;
            }
        };

        prev?.addEventListener('click', () => rail.scrollBy({ left: -step(), behavior: reducedMotion ? 'auto' : 'smooth' }));
        next?.addEventListener('click', () => rail.scrollBy({ left: step(), behavior: reducedMotion ? 'auto' : 'smooth' }));
        rail.addEventListener('scroll', syncButtons, { passive: true });
        window.addEventListener('resize', syncButtons);
        syncButtons();

        let startX = 0;
        let startScroll = 0;
        let dragging = false;
        let moved = false;

        rail.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }
            dragging = true;
            moved = false;
            startX = event.clientX;
            startScroll = rail.scrollLeft;
        });

        window.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }
            const delta = event.clientX - startX;
            if (!moved && Math.abs(delta) > 6) {
                moved = true;
                rail.classList.add('is-dragging');
            }
            if (moved) {
                rail.scrollLeft = startScroll - delta;
            }
        });

        const endDrag = () => {
            if (!dragging) {
                return;
            }
            dragging = false;
            // Laisse passer le clic en cours avant de rendre les liens à nouveau cliquables.
            setTimeout(() => rail.classList.remove('is-dragging'), 0);
        };

        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);
        rail.addEventListener('click', (event) => {
            if (moved) {
                event.preventDefault();
                moved = false;
            }
        }, true);
        rail.addEventListener('dragstart', (event) => event.preventDefault());
    }

    /* ----- Plan du magasin : l'image suit le rayon survolé ----- */
    const preview = $('[data-rayon-preview]');

    if (preview) {
        const images = $$('[data-rayon-image]', preview);
        const show = (index) => images.forEach((img) => img.classList.toggle('is-active', img.dataset.rayonImage === index));

        $$('[data-rayon]').forEach((link) => {
            link.addEventListener('pointerenter', () => show(link.dataset.rayon));
            link.addEventListener('focus', () => show(link.dataset.rayon));
        });
    }

    /* ----- Transition entre la grille et la fiche : seul le visuel cliqué se transforme ----- */
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-product-link]');
        if (!link) {
            return;
        }
        $$('.is-morphing').forEach((element) => element.classList.remove('is-morphing'));
        const scope = link.closest('.card-product, .display');
        const visual = scope && $('.thumb', scope);
        visual?.classList.add('is-morphing');
    });

    /* ----- Quantités : boutons − et + ----- */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-step]');
        if (!button) {
            return;
        }

        const input = $('input', button.closest('.stepper'));
        if (!input) {
            return;
        }

        const min = Number(input.min || 1);
        const max = Number(input.max || 99);
        const value = Math.min(max, Math.max(min, (Number(input.value) || min) + Number(button.dataset.step)));

        if (value !== Number(input.value)) {
            input.value = String(value);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // Dans le panier, changer une quantité enregistre aussitôt la ligne.
    document.addEventListener('change', (event) => {
        const form = event.target.closest('form[data-autosubmit]');
        if (form) {
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });

    /* ----- Confirmation avant une action définitive ----- */
    document.addEventListener('submit', (event) => {
        const message = event.target.dataset?.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    /* ----- Ajout au panier sans quitter la page ----- */
    const toast = $('[data-cart-toast]');
    let toastTimer;

    const fillThumb = (holder, item) => {
        holder.replaceChildren();
        const mono = document.createElement('span');
        mono.className = 'thumb__mono';
        mono.textContent = (item.name || '?').charAt(0).toUpperCase();
        holder.append(mono);

        if (item.image) {
            const img = document.createElement('img');
            img.alt = '';
            img.dataset.img = '';
            img.src = item.image;
            holder.append(img);
        }
    };

    const showToast = (data, failed = false) => {
        if (!toast) {
            return;
        }

        toast.classList.toggle('is-error', failed);
        $('[data-cart-toast-title]', toast).textContent = data.message;

        if (!failed) {
            $('[data-cart-toast-name]', toast).textContent = data.item.name;
            $('[data-cart-toast-price]', toast).textContent = data.item.price;
            $('[data-cart-toast-total]', toast).textContent = data.total;
            fillThumb($('[data-cart-toast-thumb]', toast), data.item);
        }

        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.hidden = true; }, 6000);
    };

    toast?.addEventListener('click', (event) => {
        if (event.target.closest('[data-cart-toast-close]')) {
            toast.hidden = true;
        }
    });

    toast?.addEventListener('pointerenter', () => clearTimeout(toastTimer));

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-cart-add]') || event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        const button = $('button[type="submit"]', form);
        button?.setAttribute('disabled', '');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'fetch', Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const isJson = (response.headers.get('content-type') || '').includes('application/json');
            if (!isJson) {
                // Session expirée ou réponse inattendue : on laisse le formulaire suivre son cours normal.
                form.removeAttribute('data-cart-add');
                form.submit();
                return;
            }

            const data = await response.json();
            showToast(data, !data.ok);

            if (data.ok) {
                $$('[data-cart-count]').forEach((badge) => {
                    badge.textContent = String(data.count);
                    badge.classList.remove('is-empty', 'is-bumped');
                    void badge.offsetWidth;
                    badge.classList.add('is-bumped');
                });

                if (button?.classList.contains('btn-round')) {
                    button.classList.add('is-done');
                    setTimeout(() => button.classList.remove('is-done'), 1400);
                }
            }
        } catch (error) {
            form.removeAttribute('data-cart-add');
            form.submit();
            return;
        } finally {
            button?.removeAttribute('disabled');
        }
    });

    /* ----- Recherche : suggestions pendant la saisie ----- */
    const search = $('[data-search]');

    if (search) {
        const input = $('input[type="search"]', search);
        const list = $('[data-search-results]', search);
        let controller;
        let timer;
        let active = -1;

        const close = () => {
            list.hidden = true;
            list.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        };

        const setActive = (index) => {
            const options = $$('[role="option"]', list);
            active = options.length ? (index + options.length) % options.length : -1;
            options.forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        };

        const render = (items) => {
            list.replaceChildren();

            if (!items.length) {
                const empty = document.createElement('li');
                empty.className = 'search__empty';
                empty.textContent = 'Aucun article ne correspond.';
                list.append(empty);
            }

            items.forEach((item) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');

                const link = document.createElement('a');
                link.href = item.url;

                const thumb = document.createElement('span');
                thumb.className = 'thumb thumb--sm';
                fillThumb(thumb, item);

                const text = document.createElement('span');
                const name = document.createElement('span');
                name.className = 'search__name';
                name.textContent = item.name;
                const meta = document.createElement('span');
                meta.className = 'search__meta';
                meta.textContent = `${item.category}, ${item.price}`;
                text.append(name, meta);

                link.append(thumb, text);
                li.append(link);
                list.append(li);
            });

            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            active = -1;
        };

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const query = input.value.trim();

            if (query.length < 2) {
                close();
                return;
            }

            timer = setTimeout(async () => {
                controller?.abort();
                controller = new AbortController();

                try {
                    const response = await fetch(`${search.dataset.suggestUrl}?q=${encodeURIComponent(query)}`, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    });
                    if (response.ok) {
                        render((await response.json()).items || []);
                    }
                } catch (error) {
                    /* saisie suivante ou réseau indisponible : la recherche classique reste possible */
                }
            }, 180);
        });

        input.addEventListener('keydown', (event) => {
            if (list.hidden) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault();
                $$('[role="option"] a', list)[active]?.click();
            } else if (event.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', (event) => {
            if (!search.contains(event.target)) {
                close();
            }
        });
    }

    /* ----- Filtres du catalogue : appliqués dès qu'on les change ----- */
    $$('[data-filters]').forEach((form) => {
        form.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
        $('[data-filters-submit]', form)?.setAttribute('hidden', '');
    });
})();
