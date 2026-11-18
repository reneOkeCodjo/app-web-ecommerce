<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}
?>
<!-- Voile d'entrée : affiché uniquement à la première visite de la session. -->
<div class="preloader" id="preloader" aria-hidden="true">
    <canvas class="preloader__canvas" data-preloader-canvas></canvas>
    <div class="preloader__veil"></div>

    <!-- Tracé orbital animé, dessiné en SVG pour rester net sur tous les écrans. -->
    <svg class="preloader__route" viewBox="0 0 1440 900" fill="none" aria-hidden="true">
        <path class="preloader__route-line" d="M-80 640C180 520 300 700 520 560S840 360 1060 470s300 30 500-90"/>
        <path class="preloader__route-line preloader__route-line--soft" d="M-60 300c240 130 380-40 600 90s330 230 540 110 280-20 420 40"/>
    </svg>

    <div class="preloader__content">
        <span class="preloader__mark" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 40 24 8l16 32"/>
                <path d="M15 29h18"/>
                <circle cx="24" cy="24" r="22"/>
            </svg>
        </span>
        <p class="preloader__brand">ATLAS</p>
        <p class="preloader__tagline">La vie étudiante sans frontières</p>
        <div class="preloader__bar" aria-hidden="true"><span data-preloader-bar></span></div>
        <p class="preloader__status">
            <span data-preloader-percent>00</span>
            <span>— préparation du terrain</span>
        </p>
    </div>
</div>
