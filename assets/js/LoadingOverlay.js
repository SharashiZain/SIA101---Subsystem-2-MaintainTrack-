/*
 * LoadingOverlay.js
 * Shows assets/images/loading.svg only when a page change or form submit is
 * taking longer than usual. Normal, quick page changes never show the loader.
 *
 * How it works:
 *   - When the user submits a form or the page starts leaving, a timer starts.
 *   - If the next page arrives before the timer ends, the timer is cancelled
 *     and nothing is shown.
 *   - If the page is still here when the timer ends, the loader appears.
 *   - The loader is never shown while the device is offline, and it is removed
 *     when the connection drops, when the page is restored from cache, or after
 *     a safety timeout.
 *
 * Usage (on any page, before renderFoot()):
 *   <script src="<?= e(appAssetUrl('assets/js/LoadingOverlay.js')) ?>"></script>
 *
 * Optional attributes on the script tag:
 *   data-loading-src  path to the SVG (default: derived from this file's location)
 *   data-delay        milliseconds to wait before showing (default: 600)
 *
 * Pages that already have their own ".loading-overlay" element manage it
 * themselves, so this script does nothing on those pages.
 */
(function () {
  "use strict";

  if (document.querySelector(".loading-overlay"))
    return; /* page has its own overlay */

  var script = document.currentScript;
  var svgSrc = script && script.getAttribute("data-loading-src");
  if (!svgSrc && script && script.src) {
    /* No attribute given: find the SVG next to this script's own folder (assets/js -> assets/images). */
    svgSrc = script.src.replace(
      /\/js\/LoadingOverlay\.js(\?.*)?$/,
      "/images/loading.svg",
    );
  }
  if (!svgSrc) return;

  var delayMs = parseInt(script && script.getAttribute("data-delay"), 10);
  if (!(delayMs >= 0)) delayMs = 600;

  var SAFETY_MS = 20000;
  var overlay = null;
  var delayTimer = null;
  var safetyTimer = null;

  function isOnline() {
    return navigator.onLine !== false;
  }

  function injectStyle() {
    var style = document.createElement("style");
    style.textContent =
      ".app-loading-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;" +
      "background:rgba(15,23,42,.35);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);}" +
      ".app-loading-overlay[hidden]{display:none !important;}" +
      ".app-loading-overlay img{width:220px;height:220px;max-width:60vw;max-height:60vw;}";
    document.head.appendChild(style);
  }

  function build() {
    if (overlay) return overlay;
    injectStyle();
    overlay = document.createElement("div");
    overlay.className = "app-loading-overlay";
    overlay.id = "appLoadingOverlay";
    overlay.setAttribute("role", "status");
    overlay.setAttribute("aria-live", "polite");
    overlay.setAttribute("aria-label", "Loading");
    overlay.hidden = true;

    var img = document.createElement("img");
    img.src = svgSrc;
    img.alt = "Loading";
    overlay.appendChild(img);

    document.body.appendChild(overlay);
    return overlay;
  }

  function showNow() {
    delayTimer = null;
    if (!isOnline()) return; /* no wifi: do not show the loader */
    build();
    overlay.hidden = false;
    clearTimeout(safetyTimer);
    safetyTimer = setTimeout(hide, SAFETY_MS);
  }

  /* Start the wait. The loader only appears if the page is still here when it ends. */
  function scheduleShow() {
    if (!isOnline() || delayTimer || (overlay && !overlay.hidden)) return;
    delayTimer = setTimeout(showNow, delayMs);
  }

  function hide() {
    clearTimeout(delayTimer);
    clearTimeout(safetyTimer);
    delayTimer = null;
    if (overlay) overlay.hidden = true;
  }

  /* A form was submitted and not cancelled by another handler (e.g. validation). */
  window.addEventListener("submit", function (e) {
    if (!e.defaultPrevented) scheduleShow();
  });

  /* The page is leaving: link clicks, button handlers that set window.location, form posts. */
  window.addEventListener("beforeunload", scheduleShow);

  /* Connection lost: remove the loader and cancel any pending one. */
  window.addEventListener("offline", hide);

  /* Page restored from the back/forward cache, or the navigation ended: never leave it on. */
  window.addEventListener("pageshow", hide);
})();
