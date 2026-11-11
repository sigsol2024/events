<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$ticketCardsHtml = render_landing_ticket_cards();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tee's Beach House Edition — Temi Presents</title>
  <meta name="description" content="Tee's Beach House Edition at Love Beach, Eleko Lagos. 11 December 2026, 8PM till dawn. Good vibes, great food, unforgettable moments." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header class="site-nav">
    <a class="nav-brand" href="#top">Tee's Beach House</a>
    <nav class="nav-links" aria-label="Primary">
      <a href="#details">Details</a>
      <a href="#includes">Includes</a>
      <a href="#tickets">Tickets</a>
      <a class="nav-cta" href="tel:08087791773">Reserve</a>
    </nav>
  </header>

  <main id="top">
    <section class="hero" aria-label="Event hero">
      <div class="hero-media" aria-hidden="true">
        <img
          src="assets/hero-beach-opt.jpg"
          alt=""
          class="hero-image"
        />
        <div class="hero-wash"></div>
      </div>

      <div class="float-prop float-prop--hero-right" aria-hidden="true">
        <svg class="drink drink--coco" viewBox="0 0 140 150" role="img">
          <ellipse cx="70" cy="138" rx="34" ry="7" fill="rgba(0,0,0,0.16)"/>
          <ellipse cx="70" cy="98" rx="40" ry="34" fill="#6b3f24"/>
          <ellipse cx="70" cy="92" rx="32" ry="26" fill="#f7efe0"/>
          <ellipse cx="70" cy="88" rx="28" ry="20" fill="#fff8ea"/>
          <path d="M58 70c4-18 20-22 28-8" stroke="#ff4fa3" stroke-width="3" fill="none"/>
          <circle cx="88" cy="60" r="8" fill="#ff4fa3"/>
          <rect x="66" y="40" width="4" height="36" rx="2" fill="#f0c43a"/>
          <path d="M48 78c-8-2-14 8-8 14" stroke="#2f9e44" stroke-width="3" fill="none"/>
          <ellipse cx="42" cy="76" rx="9" ry="4" fill="#3ecf5a"/>
          <circle cx="58" cy="96" r="3" fill="#ffb703"/>
          <circle cx="78" cy="100" r="2.5" fill="#e91e8c"/>
          <circle cx="68" cy="108" r="2" fill="#2a9d8f"/>
        </svg>
      </div>

      <div class="float-prop float-prop--hero-corner" aria-hidden="true">
        <svg class="drink drink--cola" viewBox="0 0 120 160" role="img">
          <ellipse cx="60" cy="148" rx="28" ry="6" fill="rgba(0,0,0,0.18)"/>
          <path d="M38 48h44l-6 92H44L38 48Z" fill="#f4c14a"/>
          <path d="M42 56h36l-4.5 70H46.5L42 56Z" fill="#ff7a3d"/>
          <path d="M46 64h28l-2 18H48L46 64Z" fill="rgba(255,255,255,0.25)"/>
          <rect x="52" y="28" width="16" height="22" rx="3" fill="#fff6d8"/>
          <path d="M48 30c8-14 24-14 32 2" stroke="#ff4fa3" stroke-width="3" fill="none"/>
          <circle cx="80" cy="30" r="7" fill="#ff4fa3"/>
          <path d="M34 70c10-4 18 6 12 14" stroke="#2f9e44" stroke-width="3" fill="none"/>
          <ellipse cx="34" cy="68" rx="8" ry="4" fill="#3ecf5a" transform="rotate(-20 34 68)"/>
        </svg>
      </div>

      <div class="hero-content">
        <p class="hero-presenter">Temi Presents</p>
        <h1 class="hero-brand">
          <span class="brand-script">Tee's</span>
          <span class="brand-title">Beach House</span>
          <span class="brand-edition">Edition</span>
        </h1>
        <p class="hero-support">Good vibes. Great food. Unforgettable moments on the sand at Love Beach.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#tickets">Get Your Spot</a>
          <a class="btn btn-ghost" href="tel:08087791773">Call to Enquire</a>
        </div>
      </div>
    </section>

    <section class="section details light" id="details">
      <div class="palm-deco palm-deco--left" aria-hidden="true"></div>
      <div class="palm-deco palm-deco--right" aria-hidden="true"></div>
      <div class="section-inner">
        <p class="eyebrow">The Night</p>
        <h2 class="section-title">Come for the beach.<br />Stay for the vibes.</h2>
        <p class="section-lede">One night under palms and tide at Love Beach — curated for people who know how to show up.</p>

        <ul class="fact-row">
          <li class="fact">
            <span class="fact-label">When</span>
            <span class="fact-value">11 December 2026</span>
            <span class="fact-meta">8PM till dawn</span>
          </li>
          <li class="fact">
            <span class="fact-label">Where</span>
            <span class="fact-value">Love Beach</span>
            <span class="fact-meta">Eleko, Lagos</span>
          </li>
          <li class="fact">
            <span class="fact-label">Hosted by</span>
            <span class="fact-value">Temi</span>
            <span class="fact-meta">Beach House Edition</span>
          </li>
        </ul>
      </div>
    </section>

    <section class="section includes photo-band" id="includes">
      <div class="photo-band-media" aria-hidden="true">
        <img
          src="assets/includes-beach-opt.jpg"
          alt=""
        />
        <div class="photo-band-wash"></div>
      </div>
      <div class="section-inner">
        <p class="eyebrow on-photo">Ticket Includes</p>
        <h2 class="section-title on-photo">Everything you need<br />for the night.</h2>

        <ul class="include-grid">
          <li class="include-item">
            <span class="include-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M4 10h16v2H4v-2Zm2 4h4v6H6v-6Zm8 0h4v6h-4v-6ZM8 4l2 4h4l2-4H8Z" fill="currentColor"/></svg>
            </span>
            <h3>Food &amp; Drinks</h3>
            <p>A full night of flavors and pours — no chasing extras on the sand.</p>
          </li>
          <li class="include-item">
            <span class="include-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M9 18V6l12-2v12" stroke="currentColor" stroke-width="1.6"/><circle cx="7" cy="18" r="2.5" stroke="currentColor" stroke-width="1.6"/><circle cx="19" cy="16" r="2.5" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <h3>Good Music</h3>
            <p>Sound that carries from the shoreline to the dancefloor till dawn.</p>
          </li>
          <li class="include-item">
            <span class="include-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M7 8h10v8H7V8Zm-3 2H2v4h2v-4Zm18 0h-2v4h2v-4Z" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <h3>Games</h3>
            <p>Playful moments between sets — keep the energy moving all night.</p>
          </li>
          <li class="include-item">
            <span class="include-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M12 5v14M5 12h14M7.5 7.5l9 9M16.5 7.5l-9 9" stroke="currentColor" stroke-width="1.2"/></svg>
            </span>
            <h3>Unmatched Vibes</h3>
            <p>The reason people stay after midnight — atmosphere you feel.</p>
          </li>
        </ul>
      </div>
    </section>

    <section class="section tickets sand" id="tickets">
      <div class="section-inner">
        <p class="eyebrow dark">Tickets &amp; Tables</p>
        <h2 class="section-title dark">Secure your spot early.</h2>
        <p class="section-lede dark">Limited spaces. Choose your entry or lock a VIP table with dome service.</p>

        <div class="ticket-layout" id="ticket-layout" data-tickets-root data-tickets-ready="1">
          <?= $ticketCardsHtml ?>
        </div>
      </div>
    </section>

    <section class="section finale photo-band" id="reserve">
      <div class="photo-band-media" aria-hidden="true">
        <img
          src="assets/shore-sunset-opt.jpg"
          alt=""
        />
        <div class="photo-band-wash photo-band-wash--warm"></div>
      </div>

      <div class="float-prop float-prop--finale" aria-hidden="true">
        <svg class="drink drink--cola" viewBox="0 0 120 160">
          <ellipse cx="60" cy="148" rx="28" ry="6" fill="rgba(0,0,0,0.18)"/>
          <path d="M38 48h44l-6 92H44L38 48Z" fill="#f4c14a"/>
          <path d="M42 56h36l-4.5 70H46.5L42 56Z" fill="#ff7a3d"/>
          <rect x="52" y="28" width="16" height="22" rx="3" fill="#fff6d8"/>
          <path d="M48 30c8-14 24-14 32 2" stroke="#ff4fa3" stroke-width="3" fill="none"/>
          <circle cx="80" cy="30" r="7" fill="#ff4fa3"/>
        </svg>
      </div>

      <div class="section-inner finale-inner">
        <p class="eyebrow on-photo">Enquiries &amp; Reservations</p>
        <h2 class="section-title on-photo">Ready for Love Beach?</h2>
        <p class="section-lede on-photo">Call or WhatsApp to lock tickets and VIP tables before spaces run out.</p>
        <a class="phone-link" href="tel:08087791773">0808 779 1773</a>
        <div class="hero-actions">
          <a class="btn btn-primary" href="tel:08087791773">Call Now</a>
          <a class="btn btn-ghost light-ghost" href="https://wa.me/2348087791773" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <p>Temi Presents · Tee's Beach House Edition · 11.12.2026</p>
    <p>Love Beach, Eleko Lagos</p>
  </footer>

  <script src="script.js"></script>
</body>
</html>
