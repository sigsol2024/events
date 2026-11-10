const nav = document.querySelector(".site-nav");

const onScroll = () => {
  if (!nav) return;
  nav.classList.toggle("is-scrolled", window.scrollY > 24);
};

onScroll();
window.addEventListener("scroll", onScroll, { passive: true });

const observeReveals = (root = document) => {
  const revealTargets = root.querySelectorAll(
    ".section-title, .section-lede, .fact, .include-item, .ticket-panel, .phone-link, .eyebrow"
  );
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
    el.classList.add("reveal");
    el.style.transitionDelay = `${Math.min(index % 4, 3) * 80}ms`;
    observer.observe(el);
  });
};

observeReveals();

const bindShakes = (root = document) => {
  const shakeTargets = root.querySelectorAll(".float-prop, .float-drink");
  const triggerShake = (el) => {
    el.classList.remove("is-shaking");
    void el.offsetWidth;
    el.classList.add("is-shaking");
  };
  shakeTargets.forEach((el) => {
    if (el.dataset.shakeBound) return;
    el.dataset.shakeBound = "1";
    el.addEventListener("mouseenter", () => triggerShake(el));
    el.addEventListener("focus", () => triggerShake(el));
    el.addEventListener("click", () => triggerShake(el));
    el.addEventListener("animationend", (event) => {
      if (event.animationName === "shakeDrink") {
        el.classList.remove("is-shaking");
      }
    });
  });
};

bindShakes();

const drinkSvg = `
  <button class="float-drink" type="button" aria-label="Shake cocktail">
    <svg class="drink drink--mini" viewBox="0 0 90 120">
      <ellipse cx="45" cy="112" rx="18" ry="4" fill="rgba(0,0,0,0.15)"/>
      <path d="M28 30h34l-5 70H33L28 30Z" fill="#ffb703"/>
      <path d="M32 36h26l-3.5 52H35.5L32 36Z" fill="#fb8500"/>
      <rect x="40" y="16" width="10" height="16" rx="2" fill="#fff6d8"/>
      <path d="M36 18c6-10 18-10 24 2" stroke="#e91e8c" stroke-width="2.5" fill="none"/>
      <circle cx="60" cy="18" r="5" fill="#e91e8c"/>
    </svg>
  </button>`;

const escapeHtml = (value) =>
  String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

const renderTicketCard = (ticket, index) => {
  const featured = index > 0 ? " vip" : " regular";
  const featuredExtra = index === 2 ? " featured" : "";
  const btnClass = index === 0 ? "btn btn-primary" : "btn btn-magenta";
  const btnLabel = index === 0 ? "Reserve" : "Book Now";

  let pricesHtml = "";
  if (ticket.pricing_mode === "tiered" && ticket.prices.length > 1) {
    pricesHtml = `<div class="price-split">${ticket.prices
      .map(
        (p) => `<div>
        <span class="price-label">${escapeHtml(p.label || "Option")}</span>
        <span class="price">${escapeHtml(p.display)}</span>
      </div>`
      )
      .join("")}</div>`;
  } else {
    const display = ticket.prices[0]?.display || "";
    pricesHtml = `<span class="price">${escapeHtml(display)}</span>`;
  }

  return `<article class="ticket-panel${featured}${featuredExtra}">
    ${drinkSvg}
    <h3>${escapeHtml(ticket.title)}</h3>
    <p class="ticket-note">${escapeHtml(ticket.description || "")}</p>
    ${pricesHtml}
    <a class="${btnClass}" href="${escapeHtml(ticket.book_url)}">${btnLabel}</a>
  </article>`;
};

const loadTickets = async () => {
  const root = document.querySelector("[data-tickets-root]");
  if (!root) return;

  try {
    const res = await fetch("api/tickets.php", { headers: { Accept: "application/json" } });
    if (!res.ok) throw new Error("Failed to load tickets");
    const data = await res.json();
    const tickets = Array.isArray(data.tickets) ? data.tickets : [];
    if (!tickets.length) {
      root.innerHTML = `<p class="ticket-note" style="grid-column:1/-1;">Tickets will appear here once published.</p>`;
      return;
    }
    root.innerHTML = tickets.map(renderTicketCard).join("");
    observeReveals(root);
    bindShakes(root);
  } catch (err) {
    root.innerHTML = `<p class="ticket-note" style="grid-column:1/-1;">Unable to load live ticket prices. Please refresh or try again shortly.</p>`;
  }
};

loadTickets();
