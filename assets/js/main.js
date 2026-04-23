
window.addEventListener('scroll', () => {
  const nav = document.getElementById('nav');
  if (nav) nav.classList.toggle('sc', window.scrollY > 30);
});

function countUp(el) {
  if (el.dataset.done === '1') return;
  const t = parseInt(el.dataset.c, 10);
  if (Number.isNaN(t)) return;
  const dur = 1600;
  const s = Date.now();
  const f = () => {
    const p = Math.min((Date.now() - s) / dur, 1);
    const e = 1 - Math.pow(1 - p, 3);
    el.textContent = t >= 1000 ? Math.floor(e * t).toLocaleString() : Math.floor(e * t);
    if (p < 1) {
      requestAnimationFrame(f);
    } else {
      el.textContent = t >= 1000 ? t.toLocaleString() : t;
      el.dataset.done = '1';
    }
  };
  f();
}

const sro = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('in');
      e.target.querySelectorAll('[data-c]').forEach(n => countUp(n));
    }
  });
}, { threshold: .1 });

document.querySelectorAll('.sr').forEach(el => sro.observe(el));

const fco = new IntersectionObserver(entries => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) setTimeout(() => e.target.classList.add('in'), i * 55);
  });
}, { threshold: .07 });

document.querySelectorAll('.feat-cell,.step-cell,.role-card').forEach(el => fco.observe(el));

document.querySelectorAll('.bar-f').forEach(bar => {
  const w = bar.style.width || bar.dataset.w;
  if (!w) return;
  bar.dataset.w = w;
  bar.style.width = '0';
  sro.observe(bar.closest('.sr') || bar);
});

function fetchJSON(url, options = {}) {
  return fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  }).then(res => res.json());
}

function formatCurrency(amount) {
  return 'PHP ' + Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
}

// ── Province Carousel ────────────────────────────────────────────
(function () {
  var carousel = document.getElementById('provCarousel');
  if (!carousel) return;

  var slides = carousel.querySelectorAll('.prov-c-slide');
  var dots   = carousel.querySelectorAll('.prov-c-dot');
  var prev   = carousel.querySelector('.prov-c-prev');
  var next   = carousel.querySelector('.prov-c-next');
  var total  = slides.length;
  if (total < 2) return;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var current = 0;
  var autoTimer = null;

  // Per-province fallback gradients (matched by order — same as PHP $provGrad array)
  var gradients = [
    'linear-gradient(150deg,#7c2d12,#c2410c)',
    'linear-gradient(150deg,#14532d,#16a34a)',
    'linear-gradient(150deg,#1e3a5f,#2563eb)',
    'linear-gradient(150deg,#292524,#57534e)',
    'linear-gradient(150deg,#0c4a6e,#0ea5e9)',
  ];
  slides.forEach(function (s, i) {
    var fb = s.querySelector('.prov-c-fallback');
    if (fb && !fb.style.background && gradients[i]) {
      fb.style.background = gradients[i];
    }
  });

  function setSlide(el, opacity, translateX, duration) {
    if (reduced || duration === 0) {
      el.style.transition = 'none';
    } else {
      el.style.transition = 'opacity ' + duration + 'ms cubic-bezier(.4,0,.2,1), transform ' + duration + 'ms cubic-bezier(.4,0,.2,1)';
    }
    el.style.opacity = opacity;
    el.style.transform = 'translateX(' + translateX + '%) scale(' + (opacity == 1 ? '1' : '0.98') + ')';
    el.style.pointerEvents = opacity == 1 ? 'all' : 'none';
  }

  function go(idx, dir) {
    if (idx === current) return;
    var outEl = slides[current];
    var inEl  = slides[idx];

    // Snap incoming slide off-screen instantly
    inEl.style.transition = 'none';
    inEl.style.opacity = '0';
    inEl.style.transform = 'translateX(' + (dir > 0 ? '8' : '-8') + '%) scale(0.98)';
    inEl.style.pointerEvents = 'none';

    // Force reflow so the snap takes effect before we start animating
    inEl.getBoundingClientRect();

    // Animate in
    setSlide(inEl, 1, '0', 520);

    // Animate out
    setSlide(outEl, 0, dir > 0 ? '-6%' : '6%', 420);

    // Update dots
    dots[current].classList.remove('active');
    dots[idx].classList.add('active');

    current = idx;

    // Clean up outgoing slide after transition
    setTimeout(function () {
      outEl.style.transform = 'translateX(0) scale(0.98)';
    }, 520);
  }

  function goNext() { go((current + 1) % total, 1); }
  function goPrev() { go((current - 1 + total) % total, -1); }

  function startAuto() {
    autoTimer = setInterval(goNext, 5000);
  }
  function resetAuto() {
    clearInterval(autoTimer);
    startAuto();
  }

  if (next) next.addEventListener('click', function () { goNext(); resetAuto(); });
  if (prev) prev.addEventListener('click', function () { goPrev(); resetAuto(); });

  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      var idx = parseInt(dot.dataset.index, 10);
      var dir = idx > current ? 1 : -1;
      go(idx, dir);
      resetAuto();
    });
  });

  // Pause auto-advance on hover
  carousel.addEventListener('mouseenter', function () { clearInterval(autoTimer); });
  carousel.addEventListener('mouseleave', startAuto);

  // Touch swipe
  var touchStartX = 0;
  carousel.addEventListener('touchstart', function (e) {
    touchStartX = e.changedTouches[0].clientX;
  }, { passive: true });
  carousel.addEventListener('touchend', function (e) {
    var dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 44) {
      dx < 0 ? goNext() : goPrev();
      resetAuto();
    }
  }, { passive: true });

  // Keyboard arrows (only when carousel is in viewport)
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    var rect = carousel.getBoundingClientRect();
    if (rect.bottom < 0 || rect.top > window.innerHeight) return;
    e.key === 'ArrowRight' ? goNext() : goPrev();
    resetAuto();
  });

  startAuto();
}());
