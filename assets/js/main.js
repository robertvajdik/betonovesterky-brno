(function () {
  // Chráněné e-maily: data-m = base64 adresy pozpátku
  document.querySelectorAll('a[data-m]').forEach(function (a) {
    try {
      var m = atob(a.getAttribute('data-m')).split('').reverse().join('');
      a.href = 'mailto:' + m;
      (a.querySelector('[data-mail-text]') || a).textContent = m;
      a.removeAttribute('data-m');
    } catch (e) {}
  });

  // Mapa až po kliknutí (bez cookies Googlu předem)
  document.querySelectorAll('[data-map-src]').forEach(function (box) {
    var btn = box.querySelector('button');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.className = 'map';
      f.src = box.getAttribute('data-map-src');
      f.title = box.getAttribute('data-map-title') || '';
      f.loading = 'lazy';
      f.referrerPolicy = 'no-referrer-when-downgrade';
      box.replaceWith(f);
    });
  });

  // Obsah třetích stran (Facebook) až po kliknutí – bez cookies předem
  document.querySelectorAll('[data-embed-src]').forEach(function (box) {
    var btn = box.querySelector('button');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.className = box.getAttribute('data-embed-class') || '';
      f.src = box.getAttribute('data-embed-src');
      f.title = box.getAttribute('data-embed-title') || '';
      f.allow = 'encrypted-media';
      box.replaceWith(f);
    });
  });

  // Souhlas s cookies → Google Analytics až po „Povolit měření“ (volba v cookie bs_consent na 180 dní)
  var consent = document.querySelector('[data-consent]');
  if (consent) {
    var gaId = consent.getAttribute('data-ga');
    var readChoice = function () { var m = document.cookie.match(/(?:^|;\s*)bs_consent=(granted|denied)/); return m ? m[1] : ''; };
    var loadGa = function () {
      if (window.__gaLoaded) return;
      window.__gaLoaded = true;
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', gaId);
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
      document.head.appendChild(s);
    };
    var clearGaCookies = function () {
      document.cookie.split(';').forEach(function (c) {
        var name = c.split('=')[0].trim();
        if (!/^_ga/.test(name)) return;
        var host = location.hostname, parts = host.split('.');
        for (var i = 0; i < parts.length - 1; i++) {
          var d = parts.slice(i).join('.');
          document.cookie = name + '=; Max-Age=0; path=/; domain=.' + d;
        }
        document.cookie = name + '=; Max-Age=0; path=/';
      });
    };
    var choice = readChoice();
    if (choice === 'granted') loadGa(); else if (!choice) consent.hidden = false;
    consent.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-consent-choice]');
      if (!btn) return;
      var val = btn.getAttribute('data-consent-choice'), before = readChoice();
      document.cookie = 'bs_consent=' + val + '; Max-Age=' + (180 * 86400) + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
      consent.hidden = true;
      if (val === 'granted') loadGa();
      else if (before === 'granted' || window.__gaLoaded) { clearGaCookies(); location.reload(); }   // odvolání souhlasu
    });
    document.querySelectorAll('[data-consent-open]').forEach(function (b) {
      b.addEventListener('click', function () { consent.hidden = false; consent.querySelector('button').focus(); });
    });
  }

  // Mobilní menu
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav');
  function setNav(open) {
    nav.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('nav-open', open);
  }
  if (toggle && nav) {
    toggle.addEventListener('click', function () { setNav(!nav.classList.contains('is-open')); });
    window.addEventListener('resize', function () { if (window.innerWidth > 960) setNav(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && nav.classList.contains('is-open')) setNav(false); });
  }
  // Rozbalovací podmenu na mobilu
  document.querySelectorAll('.sub-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var li = btn.closest('.has-sub');
      var open = li.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Lightbox galerie (klávesnice + swipe)
  var box = document.querySelector('.lightbox');
  if (!box) return;
  var img = box.querySelector('img');
  var cap = box.querySelector('figcaption');
  var items = [];
  var idx = 0;
  var lastFocus = null;

  function show(i) {
    idx = (i + items.length) % items.length;
    img.src = items[idx].href;
    img.alt = cap.textContent = items[idx].dataset.caption || '';
  }
  function close() {
    box.hidden = true; img.src = '';
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-lightbox]');
    if (!a) return;
    ev.preventDefault();
    lastFocus = a;
    var gallery = a.closest('.gallery');
    items = Array.prototype.slice.call((gallery || document).querySelectorAll('[data-lightbox]'));
    box.hidden = false;
    document.body.style.overflow = 'hidden';
    show(items.indexOf(a));
    box.querySelector('.lightbox__close').focus();
  });
  box.querySelector('.lightbox__close').addEventListener('click', close);
  box.querySelector('.lightbox__prev').addEventListener('click', function () { show(idx - 1); });
  box.querySelector('.lightbox__next').addEventListener('click', function () { show(idx + 1); });
  box.addEventListener('click', function (ev) { if (ev.target === box) close(); });
  document.addEventListener('keydown', function (ev) {
    if (box.hidden) return;
    if (ev.key === 'Escape') close();
    if (ev.key === 'ArrowLeft') show(idx - 1);
    if (ev.key === 'ArrowRight') show(idx + 1);
  });
  var x0 = null;
  box.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (ev) {
    if (x0 === null) return;
    var dx = ev.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1));
    x0 = null;
  });
})();

// Preferovaný termín: „do“ nejde vybrat dřív než „od“
(function () {
  document.querySelectorAll('input[name="f_term_from"]').forEach(function (from) {
    var to = from.form && from.form.querySelector('input[name="f_term_to"]');
    if (!to) return;
    var base = to.min;
    function sync() {
      to.min = from.value || base;
      if (from.value && to.value && to.value < from.value) to.value = from.value;
    }
    from.addEventListener('change', sync);
    sync();
  });
})();
