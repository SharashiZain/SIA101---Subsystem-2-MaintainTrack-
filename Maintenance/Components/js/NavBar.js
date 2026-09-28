const menuItems = document.querySelectorAll(".menu-item");
const currentPage = window.location.pathname.split("/").pop();

menuItems.forEach(function (item) {
  const page = item.getAttribute("data-page");

  if (page && page === currentPage) {
    item.classList.add("active");
  }

  item.addEventListener("click", function () {
    if (!page) return;
    window.location.href = item.getAttribute("href");
  });
});

const profileTrigger = document.getElementById("profileTrigger");
const profileMenu = document.getElementById("profileMenu");

if (profileTrigger && profileMenu) {
  profileTrigger.addEventListener("click", function (event) {
    event.stopPropagation();
    profileMenu.classList.toggle("open");
  });

  document.addEventListener("click", function (event) {
    if (
      !profileMenu.contains(event.target) &&
      !profileTrigger.contains(event.target)
    ) {
      profileMenu.classList.remove("open");
    }
  });
}
