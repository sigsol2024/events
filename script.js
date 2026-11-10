const nav = document.querySelector(".site-nav");

const onScroll = () => {
  if (!nav) return;
  nav.classList.toggle("is-scrolled", window.scrollY > 24);
};

onScroll();
window.addEventListener("scroll", onScroll, { passive: true });

const revealTargets = document.querySelectorAll(
  ".section-title, .section-lede, .fact, .include-item, .ticket-panel, .phone-link, .eyebrow"
);

revealTargets.forEach((el) => el.classList.add("reveal"));

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add("is-visible");
      observer.unobserve(entry.target);
    });
  },
  { threshold: 0.18, rootMargin: "0px 0px -8% 0px" }
);

revealTargets.forEach((el, index) => {
  el.style.transitionDelay = `${Math.min(index % 4, 3) * 80}ms`;
  observer.observe(el);
});

const shakeTargets = document.querySelectorAll(".float-prop, .float-drink");

const triggerShake = (el) => {
  el.classList.remove("is-shaking");
  // Force restart so repeated hovers always shake.
  void el.offsetWidth;
  el.classList.add("is-shaking");
};

shakeTargets.forEach((el) => {
  el.addEventListener("mouseenter", () => triggerShake(el));
  el.addEventListener("focus", () => triggerShake(el));
  el.addEventListener("click", () => triggerShake(el));
  el.addEventListener("animationend", (event) => {
    if (event.animationName === "shakeDrink") {
      el.classList.remove("is-shaking");
    }
  });
});
