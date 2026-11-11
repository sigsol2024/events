(function () {
  var shell = document.querySelector("[data-admin-shell]");
  if (!shell) return;

  var openBtn = shell.querySelector("[data-admin-open]");
  var closeBtns = shell.querySelectorAll("[data-admin-close]");
  var mq = window.matchMedia("(min-width: 1024px)");

  function setOpen(open) {
    shell.classList.toggle("is-nav-open", open);
    document.documentElement.style.overflow = open && !mq.matches ? "hidden" : "";
    if (openBtn) openBtn.setAttribute("aria-expanded", open ? "true" : "false");
  }

  if (openBtn) {
    openBtn.addEventListener("click", function () {
      setOpen(!shell.classList.contains("is-nav-open"));
    });
  }

  closeBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
      setOpen(false);
    });
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") setOpen(false);
  });

  function onMq() {
    if (mq.matches) setOpen(false);
  }

  if (mq.addEventListener) mq.addEventListener("change", onMq);
  else mq.addListener(onMq);

  shell.querySelectorAll(".admin-nav-link").forEach(function (link) {
    link.addEventListener("click", function () {
      if (!mq.matches) setOpen(false);
    });
  });
})();
