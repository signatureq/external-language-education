// Animation is progressive enhancement: no hidden content if the APIs are absent.
const siteMotion = (() => {
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  const active = new Map();
  const pending = new Set();
  const ease = 'cubic-bezier(.22, 1, .36, 1)';
  let observer;

  function cancel(element) {
    const entry = active.get(element);
    if (!entry) return;
    active.delete(element);
    entry.animation.cancel();
  }

  function play(element, frames, options = {}, complete) {
    if (!element || preference.matches || !element.animate) return null;
    cancel(element);
    const animation = element.animate(frames, {duration: 560, easing: ease, fill: 'both', ...options});
    const entry = {animation, complete};
    active.set(element, entry);
    animation.finished.then(() => {
      if (active.get(element) !== entry) return;
      active.delete(element);
      complete?.();
      animation.cancel();
    }, () => { if (active.get(element) === entry) active.delete(element); });
    return animation;
  }

  function reveal(element, delay = 0, animate = true) {
    pending.delete(element);
    observer?.unobserve(element);
    element.classList.remove('motion-pending');
    if (animate) play(element, [{opacity: 0, transform: 'translateY(20px)'}, {opacity: 1, transform: 'none'}], {delay, duration: 680});
  }

  function refresh(element, delay = 0) {
    if (!element) return;
    pending.delete(element);
    observer?.unobserve(element);
    element.classList.remove('motion-pending');
    play(element, [{opacity: .25, transform: 'translateY(8px)'}, {opacity: 1, transform: 'none'}], {delay, duration: 360});
  }

  function filterPrograms() {
    document.querySelectorAll('.language-grid').forEach(grid => {
      let index = 0;
      grid.querySelectorAll(':scope > :not([hidden])').forEach(card => {
        const rect = card.getBoundingClientRect();
        if (rect.width && rect.bottom > 0 && rect.top < window.innerHeight) refresh(card, index++ * 55);
      });
    });
  }

  function initAccordion(details) {
    const summary = details.querySelector('summary');
    let targetOpen = details.open;
    details.addEventListener('toggle', () => { if (!active.has(details)) targetOpen = details.open; });
    summary?.addEventListener('click', event => {
      if (preference.matches || !details.animate) return;
      event.preventDefault();
      const start = details.getBoundingClientRect().height;
      cancel(details);
      targetOpen = !targetOpen;
      details.open = targetOpen;
      const end = details.getBoundingClientRect().height;
      details.open = true;
      details.style.overflow = 'hidden';
      play(details, [{height: `${start}px`}, {height: `${end}px`}], {duration: 320}, () => {
        details.open = targetOpen;
        details.style.removeProperty('overflow');
      });
    });
  }

  function init() {
    document.querySelectorAll('.faq-list details').forEach(initAccordion);
    if (preference.matches || !('IntersectionObserver' in window) || !Element.prototype.animate) return;
    observer = new IntersectionObserver(entries => {
      entries.forEach(entry => { if (entry.isIntersecting && pending.has(entry.target)) reveal(entry.target, Number(entry.target.dataset.motionDelay)); });
    }, {rootMargin: '0px 0px -24px 0px', threshold: 0});
    const targets = document.querySelectorAll([
      '.hero-content > *', '.hero-bottom > p', '.hero-facts > div',
      '.intro-grid > *', '.section-heading > *', '.feature-heading > *',
      '.language-filters', '.language-grid > *', '.practice-grid > *',
      '.rhythm-options > a', '.calculator > div', '.admission-steps > article',
      '.faq-layout > div', '.contact-grid > *', '.inner-hero-copy > *', '.inner-hero-photo',
      '.service-heading > *', '.reading-section > *', '.editorial-feature > *',
      '.program-overview > *', '.age-panels > *', '.levels-panel', '.time-grid > *',
      '.lesson-flow > *', '.essentials > *', '.related-cta > *',
      '.legal-hero > *', '.legal-sidebar', '.legal-document', '.footer-grid > *'
    ].join(','));
    targets.forEach(element => {
      // Avoid nesting entrances on both a column and its contents.
      if (element.parentElement.closest('[data-motion-reveal]')) return;
      element.dataset.motionReveal = '';
      const index = Array.from(element.parentElement.children).indexOf(element);
      const delay = Math.min(index % 4 * 65, 195);
      element.dataset.motionDelay = String(delay);
      const rect = element.getBoundingClientRect();
      if (!rect.width || rect.bottom <= 0) return;
      if (rect.top < window.innerHeight - 24) reveal(element, delay, !document.documentElement.dataset.pageNavigation);
      else {
        element.classList.add('motion-pending');
        pending.add(element);
        observer.observe(element);
      }
    });
    document.addEventListener('focusin', event => {
      const element = event.target.closest('[data-motion-reveal]');
      if (element && pending.has(element)) {
        pending.delete(element);
        observer.unobserve(element);
        element.classList.remove('motion-pending');
      }
    });
  }

  preference.addEventListener('change', event => {
    if (!event.matches) return;
    observer?.disconnect();
    pending.forEach(element => element.classList.remove('motion-pending'));
    pending.clear();
    active.forEach(({animation, complete}) => { animation.cancel(); complete?.(); });
    active.clear();
  });
  // The new page must be fully visible when the browser takes its snapshot.
  window.addEventListener('pagereveal', event => {
    if (!event.viewTransition) return;
    pending.forEach(element => {
      const rect = element.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) reveal(element, 0, false);
    });
    active.forEach((entry, element) => {
      if (!element.hasAttribute('data-motion-reveal')) return;
      const rect = element.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) cancel(element);
    });
  });
  return {init, refresh, filterPrograms};
})();

function initPageNavigation() {
  // Keep ordinary links/history intact where cross-document transitions are absent.
  if ('CSSViewTransitionRule' in window || !document.body.animate) return;
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  let entrance;
  document.documentElement.dataset.pageNavigation = 'fallback';
  const arrive = () => {
    entrance?.cancel();
    if (!preference.matches) {
      entrance = document.body.animate([{opacity: 0}, {opacity: 1}], {duration: 300, easing: 'ease-out'});
      entrance.finished.catch(() => {});
    }
  };
  arrive();
  window.addEventListener('pageshow', event => { if (event.persisted) arrive(); });
  preference.addEventListener('change', event => { if (event.matches) entrance?.cancel(); });
}

const menuButton = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('.mobile-nav');
function closeMenu(restoreFocus = false) {
  if (!menuButton || !mobileNav) return;
  const wasOpen = menuButton.getAttribute('aria-expanded') === 'true';
  menuButton.setAttribute('aria-expanded', 'false');
  mobileNav.hidden = true;
  document.body.classList.remove('menu-open');
  if (wasOpen && restoreFocus) menuButton.focus();
}
menuButton?.addEventListener('click', () => {
  const open = menuButton.getAttribute('aria-expanded') !== 'true';
  menuButton.setAttribute('aria-expanded', String(open));
  mobileNav.hidden = !open;
  document.body.classList.toggle('menu-open', open);
  if (open) {
    siteMotion.refresh(mobileNav);
    mobileNav.querySelectorAll('a').forEach((link, index) => siteMotion.refresh(link, index * 25));
  }
});
mobileNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => closeMenu()));
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(true); });
document.addEventListener('click', event => {
  if (!event.target.closest('.site-header')) closeMenu();
});
window.matchMedia('(min-width: 1021px)').addEventListener('change', event => { if (event.matches) closeMenu(); });

const form = document.querySelector('.application-form');
const languageSelect = form?.querySelector('#language');
const selection = {};
const selectionPanel = form?.querySelector('.form-selection');
function renderSelection() {
  if (!selectionPanel) return;
  const text = Object.values(selection).filter(Boolean).join(' · ');
  selectionPanel.hidden = !text;
  selectionPanel.querySelector('[data-selection-text]').textContent = text;
}
form?.querySelector('.clear-selection')?.addEventListener('click', () => {
  Object.keys(selection).forEach(key => delete selection[key]);
  renderSelection();
});
const query = new URLSearchParams(window.location.search);
const paletteFromUrl = query.get('palette');
const palette = ['black', 'sand', 'navy'].includes(paletteFromUrl) ? paletteFromUrl : 'sand';
if (['black', 'sand', 'navy'].includes(paletteFromUrl)) {
  document.querySelectorAll('a[href]').forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;
    const url = new URL(href, window.location.href);
    if (url.origin === window.location.origin && (url.pathname.endsWith('.html') || url.pathname.endsWith('/'))) {
      url.searchParams.set('palette', palette);
      link.href = url.pathname + url.search + url.hash;
    }
  });
}
const languageNames = {italian:'Итальянский',english:'Английский',french:'Французский',spanish:'Испанский',chinese:'Китайский',korean:'Корейский',german:'Немецкий'};
const requestedLanguage = query.get('language');
if (languageSelect && requestedLanguage) {
  const value = languageNames[requestedLanguage] || requestedLanguage;
  if (Array.from(languageSelect.options).some(option => option.value === value)) languageSelect.value = value;
}
document.querySelectorAll('[data-language]').forEach(link => link.addEventListener('click', () => {
  if (languageSelect) languageSelect.value = link.dataset.language;
}));

const priceValues = {
  week: {price:'14 400 ₽',period:'/ неделя',description:'16 часов · 4 учебных дня',selection:'Оплата раз в неделю'},
  fortnight: {price:'28 800 ₽',period:'/ две недели',description:'32 часа · 8 учебных дней',selection:'Оплата раз в две недели'},
  month: {price:'57 600 ₽',period:'/ четыре недели',description:'64 часа · 16 учебных дней',selection:'Оплата за четыре недели'}
};
if (window.schoolSettings) {
  const rate = Number(window.schoolSettings.rate);
  if (Number.isFinite(rate) && rate > 0) {
    Object.entries({week:16,fortnight:32,month:64}).forEach(([key,hours]) => {
      priceValues[key].price = (rate * hours).toLocaleString('ru-RU') + ' ₽';
    });
  }
}
document.querySelectorAll('.cadence-controls').forEach(group => {
  const buttons = Array.from(group.querySelectorAll('.cadence-button'));
  buttons.forEach(button => button.addEventListener('click', () => {
    const value = priceValues[button.dataset.cadence];
    if (!value) return;
    const changed = button.getAttribute('aria-pressed') !== 'true';
    buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    const calculator = group.closest('.calculator');
    calculator.querySelector('[data-price]').textContent = value.price;
    calculator.querySelector('[data-price-period]').textContent = value.period;
    calculator.querySelector('[data-price-description]').textContent = value.description;
    if (changed) siteMotion.refresh(calculator.querySelector('.price-live'));
    selection.payment = value.selection;
    renderSelection();
  }));
});

const scheduleValues = {
  morning: {title:'Утро · 9:00–13:30',description:'Первая половина дня для языка — 4 часа занятий и 30 минут перерыва',selection:'Утро, 9:00–13:30'},
  evening: {title:'Вечер · 17:30–22:00',description:'Вечернее погружение в практику — 4 часа занятий и 30 минут перерыва',selection:'Вечер, 17:30–22:00'}
};
if (window.schoolSettings) {
  ['morning','evening'].forEach(key => {
    const time = window.schoolSettings[key];
    if (!time) return;
    const label = key === 'morning' ? 'Утро' : 'Вечер';
    scheduleValues[key].title = label + ' · ' + time;
    scheduleValues[key].selection = label + ', ' + time;
  });
}
function updateSchedule(key, updateUrl = true) {
  const value = Object.hasOwn(scheduleValues,key) ? scheduleValues[key] : null;
  const heading = document.querySelector('[data-schedule-title]');
  if (!value || !heading) return;
  document.querySelectorAll('[data-schedule]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.schedule === key)));
  heading.textContent = value.title;
  document.querySelector('[data-schedule-description]').textContent = value.description;
  document.querySelector('[data-select-time]').dataset.selectTime = key;
  const image = document.querySelector('[data-schedule-image]');
  if (image) {
    image.src = key === 'morning' ? (window.schoolSettings?.morningPhoto || 'assets/photos/blue/group-practice.jpg') : 'assets/photos/blue/study-detail.jpg';
    image.alt = key === 'morning' ? 'Иллюстрация группового занятия' : 'Иллюстрация работы с учебными материалами';
  }
  if (updateUrl) {
    siteMotion.refresh(heading.closest('.selected-schedule')?.querySelector(':scope > div'));
    siteMotion.refresh(image?.closest('.photo'), 55);
    const url = new URL(window.location.href);
    url.searchParams.set('time',key);
    window.history.replaceState(null,'',url.pathname + url.search + url.hash);
  }
}
document.querySelectorAll('[data-schedule]').forEach(button => button.addEventListener('click', () => updateSchedule(button.dataset.schedule)));
if (document.querySelector('[data-schedule-title]')) updateSchedule(Object.hasOwn(scheduleValues,query.get('time')) ? query.get('time') : 'morning',false);
document.querySelector('[data-select-time]')?.addEventListener('click', event => {
  const value = scheduleValues[event.currentTarget.dataset.selectTime];
  if (value) { selection.time = value.selection; renderSelection(); }
});

// Applications are not collected until an actual delivery channel is configured.
form?.addEventListener('submit', event => {
  event.preventDefault();
  form.querySelector('.form-availability')?.focus({preventScroll:true});
});

const legalContents = document.querySelector('.legal-toc');
if (legalContents) {
  const wideDocument = window.matchMedia('(min-width: 961px)');
  const syncContents = () => { legalContents.open = wideDocument.matches; };
  syncContents();
  wideDocument.addEventListener('change', syncContents);
}

// The language tabs filter programs; they do not assess the visitor.
document.querySelectorAll('.filter-button[data-filter]').forEach(button => button.addEventListener('click', () => {
  const changed = button.getAttribute('aria-pressed') !== 'true';
  document.querySelectorAll('.filter-button[data-filter]').forEach(item => item.setAttribute('aria-pressed',String(item===button)));
  document.querySelectorAll('[data-region]').forEach(card => { card.hidden = button.dataset.filter !== 'all' && card.dataset.region !== button.dataset.filter; });
  document.querySelectorAll('.language-grid').forEach(grid => { grid.dataset.activeFilter = button.dataset.filter; });
  if (changed) siteMotion.filterPrograms();
}));
const pageLinks = Array.from(document.querySelectorAll('.page-nav a'));
if (pageLinks.length && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver(entries => {
    const visible = entries.filter(entry => entry.isIntersecting).sort((a,b)=>a.boundingClientRect.top-b.boundingClientRect.top)[0];
    if (visible) pageLinks.forEach(link => link.classList.toggle('is-active',link.hash === '#'+visible.target.id));
  },{rootMargin:'-150px 0px -55% 0px',threshold:0});
  pageLinks.forEach(link => { const section=document.getElementById(link.hash.slice(1)); if(section)observer.observe(section); });
}

initPageNavigation();
siteMotion.init();
