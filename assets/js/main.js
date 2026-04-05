const cur = document.getElementById('cur'), curo = document.getElementById('curo');
let mx = 0, my = 0, ox = 0, oy = 0;

if (cur && curo) {
  document.addEventListener('mousemove', e => {
    mx = e.clientX;
    my = e.clientY;
    cur.style.left = mx + 'px';
    cur.style.top = my + 'px';
  });

  (function loop() {
    ox += (mx - ox) * .1;
    oy += (my - oy) * .1;
    curo.style.left = ox + 'px';
    curo.style.top = oy + 'px';
    requestAnimationFrame(loop);
  })();
}

function addHover(sel) {
  document.querySelectorAll(sel).forEach(el => {
    el.addEventListener('mouseenter', () => {
      if (cur && curo) {
        cur.classList.add('big');
        curo.classList.add('big');
      }
    });
    el.addEventListener('mouseleave', () => {
      if (cur && curo) {
        cur.classList.remove('big');
        curo.classList.remove('big');
      }
    });
  });
}

addHover('a,button,[onclick],.sb-item,.dest-row,.list-item,.rev-item,.appr-item,.wx-cell,.pack-chip,.role-opt,.feat-cell,.step-cell,.role-card,.strip-item,.prov-card,.ptag');

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
