(function () {
  "use strict";

  var pendingKey = "sia.pendingActionToast";

  function getRegion() {
    var region = document.querySelector(".app-toast-region");
    if (!region) {
      region = document.createElement("div");
      region.className = "app-toast-region";
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-relevant", "additions");
      region.setAttribute("aria-atomic", "false");
      document.body.appendChild(region);
    }
    return region;
  }

  function showToast(message, type) {
    if (!message) return;

    var toast = document.createElement("div");
    toast.className = "app-toast" + (type === "error" ? " app-toast--error" : "");
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    var text = document.createElement("span");
    text.className = "app-toast__message";
    text.textContent = message;

    var close = document.createElement("button");
    close.className = "app-toast__close";
    close.type = "button";
    close.setAttribute("aria-label", "Dismiss notification");
    close.textContent = "\u00d7";
    close.addEventListener("click", function () {
      toast.remove();
    });

    toast.appendChild(text);
    toast.appendChild(close);
    getRegion().appendChild(toast);
    window.setTimeout(function () {
      toast.remove();
    }, 5000);
  }

  function queueToast(message) {
    try {
      sessionStorage.setItem(pendingKey, message);
    } catch (error) {
      showToast(message, "success");
    }
  }

  window.AppToast = {
    show: showToast,
    queue: queueToast,
  };

  try {
    var pendingMessage = sessionStorage.getItem(pendingKey);
    if (pendingMessage) {
      sessionStorage.removeItem(pendingKey);
      showToast(pendingMessage, "success");
    }
  } catch (error) {
    // Storage may be disabled; direct AJAX actions can still use showToast.
  }
})();
