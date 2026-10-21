/* =========================================================
   ProfileEdit.js — Edit Profile, Save, Cancel and Change Password
   for the Admin, Regular and Maintenance profile pages.
   The page supplies its field IDs in <script id="profileConfig">.
   Saves go to the same page via fetch (see includes/profile_api.php).
========================================================= */
(function () {
  "use strict";

  var configEl = document.getElementById("profileConfig");
  if (!configEl) return;
  var cfg = JSON.parse(configEl.textContent || "{}");

  function byId(id) {
    return document.getElementById(id);
  }

  function getInitials(name) {
    return name
      .split(" ")
      .filter(function (n) {
        return n.length > 0;
      })
      .map(function (n) {
        return n[0];
      })
      .join("")
      .toUpperCase()
      .slice(0, 2);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var editBtn = byId("editProfileBtn");
    var saveBtn = byId("saveProfileBtn");
    var cancelBtn = byId("cancelProfileBtn");
    var formActions = byId("profileFormActions");
    var displayName = byId("profileDisplayName");
    var avatar = byId("avatarCircle");

    /* Only these inputs can be edited; role and contact stay read-only */
    var editable = (cfg.editable || []).map(byId).filter(Boolean);
    var originalValues = {};

    function lockInputs() {
      editable.forEach(function (input) {
        input.setAttribute("readonly", true);
        input.style.borderColor = "";
        input.style.background = "";
      });
    }

    function showViewMode() {
      formActions.style.display = "none";
      editBtn.style.display = "inline-flex";
      editBtn.textContent = "Edit Profile";
    }

    /* Sends one action to this page and returns {ok, error, warning} */
    async function postAction(action, payload) {
      var url = new URL(window.location.href);
      url.search = "";
      url.searchParams.set("action", action);

      try {
        var res = await fetch(url.toString(), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        var data = await res.json().catch(function () {
          return {};
        });
        if (!res.ok || !data.ok) {
          return {
            ok: false,
            error: data.error || "Something went wrong. Please try again.",
          };
        }
        return { ok: true, warning: data.warning || null };
      } catch (err) {
        return {
          ok: false,
          error: "Could not reach the server. Please try again.",
        };
      }
    }

    /* ---------- Edit ---------- */
    editBtn.addEventListener("click", function () {
      originalValues = {};
      editable.forEach(function (input) {
        originalValues[input.id] = input.value;
        input.removeAttribute("readonly");
        input.style.borderColor = "#4a86c8";
        input.style.background = "#ffffff";
      });

      formActions.style.display = "flex";
      editBtn.style.display = "none";
      editBtn.textContent = "Editing...";
    });

    /* ---------- Save ---------- */
    saveBtn.addEventListener("click", async function () {
      var fullName = byId(cfg.fullId).value.trim();
      var username = byId(cfg.usernameId).value.trim();
      var email = byId(cfg.emailId).value.trim();

      if (!fullName || !email) {
        alert("Full name and email are required.");
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert("Please enter a valid email address.");
        return;
      }

      saveBtn.disabled = true;
      var result = await postAction("update_profile", {
        fullName: fullName,
        username: username,
        email: email,
      });
      saveBtn.disabled = false;

      if (!result.ok) {
        alert(result.error);
        return;
      }

      displayName.textContent = fullName;
      avatar.textContent = getInitials(fullName);
      lockInputs();
      showViewMode();

      alert(
        result.warning
          ? "Profile saved.\n\n" + result.warning
          : "Profile updated successfully!",
      );
    });

    /* ---------- Cancel ---------- */
    cancelBtn.addEventListener("click", function () {
      editable.forEach(function (input) {
        if (originalValues[input.id] !== undefined) {
          input.value = originalValues[input.id];
        }
      });
      lockInputs();
      showViewMode();
    });

    /* ---------- Change password modal ---------- */
    var passwordModal = byId("passwordModal");
    var changePasswordBtn = byId("changePasswordBtn");
    var passwordModalClose = byId("passwordModalClose");
    var passwordModalCancel = byId("passwordModalCancel");
    var passwordModalSave = byId("passwordModalSave");

    function openPasswordModal() {
      passwordModal.style.display = "flex";
    }

    function closePasswordModal() {
      passwordModal.style.display = "none";
      byId("currentPassword").value = "";
      byId("newPassword").value = "";
      byId("confirmPassword").value = "";
    }

    changePasswordBtn.addEventListener("click", openPasswordModal);
    passwordModalClose.addEventListener("click", closePasswordModal);
    passwordModalCancel.addEventListener("click", closePasswordModal);

    passwordModalSave.addEventListener("click", async function () {
      var current = byId("currentPassword").value;
      var newPass = byId("newPassword").value;
      var confirm = byId("confirmPassword").value;

      if (!current || !newPass || !confirm) {
        alert("Please fill in all password fields.");
        return;
      }
      if (newPass.length < 6) {
        alert("New password must be at least 6 characters.");
        return;
      }
      if (newPass !== confirm) {
        alert("New passwords do not match.");
        return;
      }

      passwordModalSave.disabled = true;
      var result = await postAction("change_password", {
        current: current,
        newPass: newPass,
        confirm: confirm,
      });
      passwordModalSave.disabled = false;

      if (!result.ok) {
        alert(result.error);
        return;
      }
      closePasswordModal();
      alert(
        result.warning
          ? "Password saved.\n\n" + result.warning
          : "Password updated successfully!",
      );
    });

    passwordModal.addEventListener("click", function (e) {
      if (e.target === passwordModal) closePasswordModal();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && passwordModal.style.display === "flex")
        closePasswordModal();
    });
  });
})();
