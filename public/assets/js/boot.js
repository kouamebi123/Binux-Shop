/* Chargé avant l'affichage : signale que le script est disponible et révèle chaque visuel en fondu
   une fois qu'il est réellement chargé. Sans script, les images s'affichent normalement. */
(() => {
    'use strict';

    document.documentElement.classList.add('js');

    const settle = (img) => {
        if (!(img instanceof HTMLImageElement) || !img.matches('[data-img]')) {
            return;
        }
        if (img.naturalWidth > 0) {
            img.classList.add('is-loaded');
        } else {
            img.classList.add('is-broken');
        }
    };

    document.addEventListener('load', (event) => settle(event.target), true);
    document.addEventListener('error', (event) => settle(event.target), true);

    // Visuels déjà en cache, chargés avant que l'écouteur ne les voie.
    const sweep = () => document.querySelectorAll('img[data-img]').forEach((img) => {
        if (img.complete) {
            settle(img);
        }
    });

    document.addEventListener('DOMContentLoaded', sweep);
    window.addEventListener('load', sweep);
    window.addEventListener('pageshow', sweep);
})();
