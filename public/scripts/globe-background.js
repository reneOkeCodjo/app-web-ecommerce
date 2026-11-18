/**
 * Charge le globe uniquement quand il est utile :
 * thème sombre, navigateur capable, réseau et mémoire suffisants.
 *
 * Le moteur WebGL et les textures ne sont téléchargés qu'après
 * le premier temps libre du navigateur.
 */

(function () {
  "use strict";

  // Le navigateur peut-il afficher le globe sans risque ?
  const capable = (function () {
    if (typeof window.WebGLRenderingContext === "undefined") {
      return false;
    }

    const connection = navigator.connection;
    if (connection && connection.saveData) {
      return false;
    }

    if (
      typeof navigator.deviceMemory === "number" &&
      navigator.deviceMemory < 2
    ) {
      return false;
    }

    return true;
  })();

  if (!capable) {
    return;
  }

  let host = null;
  let canvas = null;
  let stopGlobe = null;
  let idleId = 0;
  let timerId = 0;
  let token = 0;
  let active = false;

  /** Le thème actuellement appliqué est-il sombre ? */
  function isDark() {
    return document.documentElement.getAttribute("data-theme") === "dark";
  }

  /** Annule l'attente du temps libre. */
  function cancelIdle() {
    if (idleId && typeof window.cancelIdleCallback === "function") {
      window.cancelIdleCallback(idleId);
    }
    idleId = 0;
    window.clearTimeout(timerId);
    timerId = 0;
  }

  /** Retire le globe et libère toutes ses ressources. */
  function teardown() {
    active = false;
    token += 1;
    cancelIdle();

    if (typeof stopGlobe === "function") {
      stopGlobe();
    }
    stopGlobe = null;

    if (host) {
      host.remove();
    }
    host = null;
    canvas = null;
  }

  /** Crée le canvas et lance le chargement différé. */
  function setup() {
    if (active) {
      return;
    }

    active = true;
    const currentToken = token + 1;
    token = currentToken;

    host = document.createElement("div");
    host.className = "globe-backdrop";
    host.setAttribute("aria-hidden", "true");

    canvas = document.createElement("canvas");
    canvas.className = "globe-canvas";
    canvas.setAttribute("aria-hidden", "true");

    host.appendChild(canvas);
    document.body.insertBefore(host, document.body.firstChild);

    const load = () => {
      idleId = 0;
      timerId = 0;

      if (!active || currentToken !== token) {
        return;
      }

      import("./globe/earthGlobe.js")
        .then(({ mountEarthGlobe }) => {
          if (!active || currentToken !== token || !canvas) {
            return null;
          }

          return mountEarthGlobe({
            canvas,
            dayUrl: "globe/earth-day.webp",
            nightUrl: "globe/earth-night.webp",
            animate: !window.matchMedia("(prefers-reduced-motion: reduce)").matches,
            onFirstFrame: () => {
              if (active && currentToken === token && canvas) {
                canvas.classList.add("is-visible");
              }
            },
          });
        })
        .then((stop) => {
          if (typeof stop !== "function") {
            return;
          }

          if (!active || currentToken !== token) {
            stop();
            return;
          }

          stopGlobe = stop;
        })
        .catch(() => {
          if (active && currentToken === token) {
            teardown();
          }
        });
    };

    if (typeof window.requestIdleCallback === "function") {
      idleId = window.requestIdleCallback(load, { timeout: 2500 });
    } else {
      timerId = window.setTimeout(load, 1200);
    }
  }

  /** Réagit au changement de thème et au chargement de la page. */
  function sync() {
    if (isDark()) {
      setup();
    } else {
      teardown();
    }
  }

  document.addEventListener("sv:themechange", sync);
  window.addEventListener("pagehide", teardown);
  window.addEventListener("pageshow", sync);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", sync, { once: true });
  } else {
    sync();
  }
})();
