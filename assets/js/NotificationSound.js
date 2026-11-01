/*
 * NotificationSound.js
 * Shared by all five roles (admin, secretary, maintenance, regular, treasurer).
 * Plays assets/notif.mp3 when a NEW unread notification arrives for the logged-in user.
 *
 * Load it once per page (see layout.php hook). It starts itself; no per-page code needed.
 * It also keeps the red notification dot in sync: the dot (any element with
 * data-notif-dot) is shown only while the role's notification page has unread items.
 *   MTNotifSound.markRead(["id1", "id2"]);  // mark items read and update the dot now
 *   MTNotifSound.isRead("id1");             // was this item already read?
 *   MTNotifSound.refreshDots();             // re-check the unread list right now
 *   MTNotifSound.refreshApprovals();        // secretary only: re-check the Approvals dot now
 * Optional control from any page:
 *   MTNotifSound.check();   // check right now (e.g. after your own fetch)
 *   MTNotifSound.play();    // play the sound manually
 *   MTNotifSound.mute();    // MTNotifSound.unmute(); MTNotifSound.isMuted();
 */
(function (global) {
  "use strict";

  var ROLES = ["admin", "secretary", "maintenance", "regular", "treasurer"];
  var POLL_VISIBLE_MS = 10000; // how often to check while the tab is open
  var POLL_HIDDEN_MS = 30000; // slower while the tab is in the background

  // Work out the site root (e.g. /Sia/) from this script's own URL.
  var scriptEl = document.currentScript;
  var scriptSrc = scriptEl ? scriptEl.src : "";
  var siteRoot = scriptSrc.replace(/assets\/js\/NotificationSound\.js.*$/, "");

  function detectRole() {
    var m = location.pathname.match(
      /\/pages\/(admin|secretary|maintenance|regular|treasurer)\//,
    );
    if (m) return m[1];
    var q = new URLSearchParams(location.search).get("role"); // shared pages like settings.php
    return ROLES.indexOf(q) !== -1 ? q : null;
  }

  var role = detectRole();
  var endpoint =
    siteRoot +
    "includes/notification_poll.php?role=" +
    encodeURIComponent(role || "");
  var soundUrl = siteRoot + "assets/notif.mp3";
  var lastKey = "mt_notif_last_" + role;
  var muteKey = "mt_notif_muted_" + role;

  // ---- Red dot: follows the SAME unread list the role's notification page shows ----
  // Each <role>_notification.php answers ?feed=1 with the ids of its unread items.
  // Items the user already read (click / "Mark all as read" / opening the page) are
  // remembered in localStorage per role + user, so the dot only shows what is still unread.
  var DOT_POLL_VISIBLE_MS = 20000;
  var DOT_POLL_HIDDEN_MS = 60000;
  var MAX_READ_IDS = 500;
  var userKey = String(global.MT_NOTIF_USER || "");
  var readStoreKey = "mt_notif_read_" + role + "_" + userKey;
  var unreadCacheKey = "mt_notif_unread_" + role + "_" + userKey;
  var feedUrl = role
    ? siteRoot + "pages/" + role + "/" + role + "_notification.php?feed=1"
    : "";
  var lastUnreadIds = null; // null = feed not loaded yet, dot stays hidden
  var dotTimer = null;
  var dotBusy = false;

  // ---- Approvals dot (secretary only): shown only while requests await a decision ----
  // Any element with data-approvals-dot is shown when the pending count is above 0.
  var APPROVAL_POLL_VISIBLE_MS = 20000;
  var APPROVAL_POLL_HIDDEN_MS = 60000;
  var approvalsEndpoint =
    role === "secretary"
      ? siteRoot +
        "includes/notification_poll.php?role=secretary&scope=approvals"
      : "";
  var approvalsCacheKey = "mt_approvals_pending_" + userKey;
  var approvalsTimer = null;
  var approvalsBusy = false;

  var audio = null;
  var pendingPlay = false; // browser blocked autoplay; play on the next click/key
  var timer = null;
  var busy = false;

  function store(key, val) {
    try {
      localStorage.setItem(key, val);
    } catch (e) {}
  }
  function load(key) {
    try {
      return localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function getAudio() {
    if (!audio) {
      audio = new Audio(soundUrl);
      audio.preload = "auto";
    }
    return audio;
  }

  function play() {
    if (load(muteKey) === "1") return;
    var a = getAudio();
    try {
      a.currentTime = 0;
    } catch (e) {}
    var p = a.play();
    if (p && typeof p.catch === "function") {
      p.catch(function () {
        pendingPlay = true;
      }); // autoplay blocked until the user interacts
    }
  }

  // Browsers only allow sound after a click/tap/key; unlock it on the first one.
  function unlock() {
    var a = getAudio();
    if (pendingPlay) {
      pendingPlay = false;
      play();
    } else {
      // Silent prime so later plays are allowed.
      a.muted = true;
      var p = a.play();
      var done = function () {
        a.pause();
        a.currentTime = 0;
        a.muted = false;
      };
      if (p && typeof p.then === "function")
        p.then(done).catch(function () {
          a.muted = false;
        });
      else done();
    }
    ["click", "keydown", "touchstart"].forEach(function (ev) {
      document.removeEventListener(ev, unlock, true);
    });
  }

  // Show the red dot on any element marked data-notif-dot only while there are unread notifications.
  function updateDots(unread) {
    var show = Number(unread) > 0;
    var dots = document.querySelectorAll("[data-notif-dot]");
    for (var i = 0; i < dots.length; i++) {
      if (show) dots[i].removeAttribute("hidden");
      else dots[i].setAttribute("hidden", "");
    }
  }

  function updateApprovalDots(pending) {
    var show = Number(pending) > 0;
    var dots = document.querySelectorAll("[data-approvals-dot]");
    for (var i = 0; i < dots.length; i++) {
      if (show) dots[i].removeAttribute("hidden");
      else dots[i].setAttribute("hidden", "");
    }
  }

  function refreshApprovals() {
    if (!approvalsEndpoint || approvalsBusy) return;
    approvalsBusy = true;
    fetch(approvalsEndpoint, { credentials: "same-origin", cache: "no-store" })
      .then(function (r) {
        return r.ok ? r.json() : null;
      })
      .then(function (data) {
        if (!data || data.error || typeof data.pending !== "number") return;
        store(approvalsCacheKey, String(data.pending));
        updateApprovalDots(data.pending);
      })
      .catch(function () {
        /* network blip: keep whatever the dot shows now */
      })
      .then(function () {
        approvalsBusy = false;
      });
  }

  // ---- read state (kept in the browser, per role + user) ----
  function readIds() {
    try {
      var list = JSON.parse(load(readStoreKey) || "[]");
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function isRead(id) {
    return readIds().indexOf(String(id)) !== -1;
  }

  // Show or hide the dots from the cached/fetched unread ids minus the ones already read.
  function renderDots() {
    if (lastUnreadIds === null) return;
    var seen = readIds();
    var remaining = 0;
    for (var i = 0; i < lastUnreadIds.length; i++) {
      if (seen.indexOf(String(lastUnreadIds[i])) === -1) remaining++;
    }
    updateDots(remaining);
    global.dispatchEvent(
      new CustomEvent("mt:notifications", { detail: { unread: remaining } }),
    );
  }

  // Remember that these notification ids were read, then update the dot right away.
  function markRead(ids) {
    if (!ids || !ids.length) return;
    var list = readIds();
    for (var i = 0; i < ids.length; i++) {
      var id = String(ids[i]);
      if (id && list.indexOf(id) === -1) list.push(id);
    }
    store(readStoreKey, JSON.stringify(list.slice(-MAX_READ_IDS)));
    renderDots();
  }

  function refreshDots() {
    if (!role || !feedUrl || dotBusy) return;
    dotBusy = true;
    fetch(feedUrl, { credentials: "same-origin", cache: "no-store" })
      .then(function (r) {
        return r.ok ? r.json() : null;
      })
      .then(function (data) {
        if (!data || !Array.isArray(data.unread_ids)) return;
        lastUnreadIds = data.unread_ids;
        store(unreadCacheKey, JSON.stringify(lastUnreadIds));
        renderDots();
      })
      .catch(function () {
        /* network blip or not logged in: keep whatever the dot shows now */
      })
      .then(function () {
        dotBusy = false;
      });
  }

  function check() {
    if (!role || busy) return;
    busy = true;
    fetch(endpoint, { credentials: "same-origin", cache: "no-store" })
      .then(function (r) {
        return r.ok ? r.json() : null;
      })
      .then(function (data) {
        if (!data || data.error) return;
        var latest = data.latest_created_at
          ? Date.parse(data.latest_created_at)
          : 0;
        var seen = load(lastKey);

        if (seen === null) {
          // first visit: set a baseline, don't play for old notifications
          store(lastKey, String(latest || 0));
          return;
        }
        if (latest > Number(seen)) {
          // something newer than we have already announced
          store(lastKey, String(latest)); // update first so other open tabs don't also play it
          play();
        }
      })
      .catch(function () {
        /* network blip: try again next tick */
      })
      .then(function () {
        busy = false;
      });
  }

  function schedule() {
    clearInterval(timer);
    timer = setInterval(
      check,
      document.hidden ? POLL_HIDDEN_MS : POLL_VISIBLE_MS,
    );
    clearInterval(dotTimer);
    dotTimer = setInterval(
      refreshDots,
      document.hidden ? DOT_POLL_HIDDEN_MS : DOT_POLL_VISIBLE_MS,
    );
    clearInterval(approvalsTimer);
    if (approvalsEndpoint) {
      approvalsTimer = setInterval(
        refreshApprovals,
        document.hidden ? APPROVAL_POLL_HIDDEN_MS : APPROVAL_POLL_VISIBLE_MS,
      );
    }
  }

  function start() {
    if (!role || !siteRoot) return;
    ["click", "keydown", "touchstart"].forEach(function (ev) {
      document.addEventListener(ev, unlock, true);
    });
    document.addEventListener("visibilitychange", function () {
      schedule();
      if (!document.hidden) {
        check();
        refreshDots();
        refreshApprovals();
      }
    });
    // Another tab marked something read: update this tab's dot too.
    global.addEventListener("storage", function (e) {
      if (e.key === readStoreKey) renderDots();
    });
    // Paint the last known state at once so the dot doesn't blink between pages.
    try {
      var cached = JSON.parse(load(unreadCacheKey) || "null");
      if (Array.isArray(cached)) {
        lastUnreadIds = cached;
        renderDots();
      }
    } catch (e) {}
    // Paint the last known approvals state at once so the dot doesn't blink between pages.
    if (approvalsEndpoint) {
      var cachedPending = load(approvalsCacheKey);
      if (cachedPending !== null) updateApprovalDots(Number(cachedPending));
    }
    schedule();
    check();
    refreshDots();
    refreshApprovals();
  }

  global.MTNotifSound = {
    check: check,
    refreshDots: refreshDots,
    refreshApprovals: refreshApprovals,
    markRead: markRead,
    isRead: isRead,
    play: play,
    mute: function () {
      store(muteKey, "1");
    },
    unmute: function () {
      store(muteKey, "0");
    },
    isMuted: function () {
      return load(muteKey) === "1";
    },
  };

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", start);
  else start();
})(window);