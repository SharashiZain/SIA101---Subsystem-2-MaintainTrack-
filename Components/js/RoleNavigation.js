const menuItems = document.querySelectorAll(".menu-item");
const currentPage = window.location.pathname.split("/").pop();
const displayOptions = {
  small: {
    baseFontSize: "14px",
    fontScale: "0.875",
    contentScale: "0.85",
    spacing: "16px",
    container: "20px",
    card: "16px",
    section: "16px",
    bottom: "32px",
  },
  medium: {
    baseFontSize: "16px",
    fontScale: "1",
    contentScale: "1",
    spacing: "24px",
    container: "30px",
    card: "24px",
    section: "24px",
    bottom: "60px",
  },
  large: {
    baseFontSize: "18px",
    fontScale: "1.125",
    contentScale: "1.15",
    spacing: "32px",
    container: "40px",
    card: "32px",
    section: "32px",
    bottom: "72px",
  },
};
const backgroundOptions = [
  "default",
  "building",
  "cloud",
  "classic",
  "classic2",
  "terrain",
];

function applyDisplayPreferences(fontSize, contentSize, background) {
  const root = document.documentElement;
  const font = displayOptions[fontSize] ? fontSize : "medium";
  const content = displayOptions[contentSize] ? contentSize : "medium";
  const pageBackground = backgroundOptions.includes(background)
    ? background
    : "default";
  const fontSettings = displayOptions[font];
  const contentSettings = displayOptions[content];

  root.dataset.fontSize = font;
  root.dataset.contentSize = content;
  root.dataset.background = pageBackground;
  root.style.setProperty("--base-font-size", fontSettings.baseFontSize);
  root.style.setProperty("--font-scale", fontSettings.fontScale);
  root.style.setProperty("--content-scale", contentSettings.contentScale);
  root.style.setProperty("--content-spacing", contentSettings.spacing);
  root.style.setProperty("--container-padding", contentSettings.container);
  root.style.setProperty("--card-padding", contentSettings.card);
  root.style.setProperty("--section-gap", contentSettings.section);
  root.style.setProperty("--page-bottom-padding", contentSettings.bottom);
}

function readPreferenceCookie(name, fallback = "medium") {
  const cookie = document.cookie
    .split(";")
    .map((item) => item.trim())
    .find((item) => item.startsWith(`${name}=`));
  return cookie ? decodeURIComponent(cookie.slice(name.length + 1)) : fallback;
}

const activeRole = document.documentElement.dataset.role || "admin";

applyDisplayPreferences(
  readPreferenceCookie(`${activeRole}_font_size`),
  readPreferenceCookie(`${activeRole}_content_size`),
  readPreferenceCookie(`${activeRole}_background`, "default"),
);

document.addEventListener("change", function (event) {
  if (!event.target.matches("[data-display-preference]")) return;

  const fontSize =
    document.querySelector('[name="font_size"]')?.value ||
    readPreferenceCookie(`${activeRole}_font_size`);
  const contentSize =
    document.querySelector('[name="content_size"]')?.value ||
    readPreferenceCookie(`${activeRole}_content_size`);
  const background =
    document.querySelector('[name="background"]')?.value ||
    readPreferenceCookie(`${activeRole}_background`, "default");
  applyDisplayPreferences(fontSize, contentSize, background);
});

menuItems.forEach(function (item) {
  const page = item.dataset.page;
  const href = item.getAttribute("href") || item.dataset.href || page;
  const pageName = page ? page.split("?")[0].split("/").pop() : "";

  if (pageName === currentPage) {
    item.classList.add("active");
  }

  item.addEventListener("click", function (event) {
    if (!href) return;
    event.preventDefault();
    window.location.href = href;
  });
});

const profileTrigger = document.getElementById("profileTrigger");
const profileMenu = document.getElementById("profileMenu");

if (profileTrigger && profileMenu) {
  profileTrigger.addEventListener("click", function (event) {
    event.stopPropagation();
    const isOpen = profileMenu.classList.toggle("open");
    profileTrigger.setAttribute("aria-expanded", String(isOpen));
  });

  document.addEventListener("click", function (event) {
    if (
      !profileMenu.contains(event.target) &&
      !profileTrigger.contains(event.target)
    ) {
      profileMenu.classList.remove("open");
      profileTrigger.setAttribute("aria-expanded", "false");
    }
  });
}

const sidebarUser = document.querySelector(".sidebar-user");
const sidebarUserMenu = document.querySelector(".sidebar-user-menu");

if (sidebarUser && sidebarUserMenu) {
  sidebarUser.addEventListener("click", function () {
    const isExpanded = sidebarUser.getAttribute("aria-expanded") === "true";
    sidebarUser.setAttribute("aria-expanded", String(!isExpanded));
    sidebarUserMenu.classList.toggle("open", !isExpanded);
  });

  document.addEventListener("click", function (event) {
    if (
      !sidebarUserMenu.contains(event.target) &&
      !sidebarUser.contains(event.target)
    ) {
      sidebarUser.setAttribute("aria-expanded", "false");
      sidebarUserMenu.classList.remove("open");
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && sidebarUserMenu.classList.contains("open")) {
      sidebarUser.setAttribute("aria-expanded", "false");
      sidebarUserMenu.classList.remove("open");
      sidebarUser.focus();
    }
  });
}

if (profileTrigger && profileMenu) {
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && profileMenu.classList.contains("open")) {
      profileMenu.classList.remove("open");
      profileTrigger.setAttribute("aria-expanded", "false");
      profileTrigger.focus();
    }
  });
}

document
  .querySelectorAll("[data-layout-toggle]")
  .forEach(function (layoutToggle) {
    layoutToggle.addEventListener("click", function (event) {
      event.preventDefault();
      const layoutRole = document.querySelector("[data-layout-role]");
      if (!layoutRole) return;

      const layout = layoutToggle.dataset.layoutToggle;
      document.cookie = `${layoutRole.dataset.layoutRole}_layout=${layout}; path=/; max-age=31536000; SameSite=Lax`;
      window.location.reload();
    });
  });

if (document.querySelector(".role-sidebar")) {
  document.body.classList.add("has-role-sidebar");
}
