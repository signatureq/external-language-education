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
document.querySelectorAll('.cadence-controls').forEach(group => {
  const buttons = Array.from(group.querySelectorAll('.cadence-button'));
  buttons.forEach(button => button.addEventListener('click', () => {
    const value = priceValues[button.dataset.cadence];
    if (!value) return;
    buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    const calculator = group.closest('.calculator');
    calculator.querySelector('[data-price]').textContent = value.price;
    calculator.querySelector('[data-price-period]').textContent = value.period;
    calculator.querySelector('[data-price-description]').textContent = value.description;
    selection.payment = value.selection;
    renderSelection();
  }));
});

const scheduleValues = {
  morning: {title:'Утро · 9:00–13:30',description:'Первая половина дня для языка. 4 часа занятий и 30 минут перерыва.',selection:'Утро, 9:00–13:30'},
  evening: {title:'Вечер · 17:30–22:00',description:'Вечернее погружение в практику. 4 часа занятий и 30 минут перерыва.',selection:'Вечер, 17:30–22:00'}
};
function updateSchedule(key, updateUrl = true) {
  const value = Object.hasOwn(scheduleValues,key) ? scheduleValues[key] : null;
  const heading = document.querySelector('[data-schedule-title]');
  if (!value || !heading) return;
  document.querySelectorAll('[data-schedule]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.schedule === key)));
  heading.textContent = value.title;
  document.querySelector('[data-schedule-description]').textContent = value.description;
  document.querySelector('[data-select-time]').dataset.selectTime = key;
  const image = document.querySelector('[data-schedule-image]');
  if (image) { image.src = key === 'morning' ? 'assets/photos/blue/group-practice.jpg' : 'assets/photos/blue/study-detail.jpg'; image.alt = key === 'morning' ? 'Иллюстрация группового занятия' : 'Иллюстрация работы с учебными материалами'; }
  if (updateUrl) {
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

function fieldError(field, message) {
  const error = form.querySelector('#' + field.id + '-error');
  if (!error) return;
  error.textContent = message;
  error.hidden = !message;
  if (message) field.setAttribute('aria-invalid','true');
  else field.removeAttribute('aria-invalid');
}
function validateField(field) {
  let message = '';
  if (field.id === 'name' && !field.value.trim()) message = 'Укажите, как к вам обращаться.';
  if (field.id === 'phone') {
    const digits = field.value.replace(/\D/g,'');
    if (digits.length < 10 || digits.length > 15 || !/^[+\d\s().-]+$/.test(field.value)) message = 'Укажите телефон с кодом страны — от 10 до 15 цифр.';
  }
  if (field.id === 'language' && !field.value) message = 'Выберите язык или пункт «Помогите выбрать».';
  fieldError(field,message);
  return !message;
}
const requiredFields = form ? ['name','phone','language'].map(id => form.querySelector('#'+id)) : [];
requiredFields.forEach(field => {
  field.addEventListener('input', () => fieldError(field,''));
  field.addEventListener('change', () => fieldError(field,''));
});
let submitting = false;
form?.addEventListener('submit', event => {
  event.preventDefault();
  if (submitting) return;
  const invalid = requiredFields.filter(field => !validateField(field));
  if (invalid.length) { invalid[0].focus(); return; }
  submitting = true;
  const button = form.querySelector('[type=submit]');
  button.disabled = true;
  button.querySelector('span').textContent = 'Проверяем данные…';
  form.setAttribute('aria-busy','true');
  // Local demonstration only. No network requests or personal-data storage.
  setTimeout(() => {
    const status = form.querySelector('.form-status');
    form.querySelector('.form-fields').hidden = true;
    status.hidden = false;
    form.removeAttribute('aria-busy');
    submitting = false;
    button.disabled = false;
    button.querySelector('span').textContent = 'Оставить заявку';
    status.focus({preventScroll:true});
  },400);
});
form?.querySelector('.form-reset')?.addEventListener('click', () => {
  form.reset();
  requiredFields.forEach(field => fieldError(field,''));
  Object.keys(selection).forEach(key => delete selection[key]);
  renderSelection();
  form.querySelector('.form-fields').hidden = false;
  form.querySelector('.form-status').hidden = true;
  form.querySelector('#name').focus({preventScroll:true});
});

// The language tabs filter programs; they do not assess the visitor.
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-pressed',String(item===button)));
  document.querySelectorAll('[data-region]').forEach(card => { card.hidden = button.dataset.filter !== 'all' && card.dataset.region !== button.dataset.filter; });
}));
const pageLinks = Array.from(document.querySelectorAll('.page-nav a'));
if (pageLinks.length && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver(entries => {
    const visible = entries.filter(entry => entry.isIntersecting).sort((a,b)=>a.boundingClientRect.top-b.boundingClientRect.top)[0];
    if (visible) pageLinks.forEach(link => link.classList.toggle('is-active',link.hash === '#'+visible.target.id));
  },{rootMargin:'-150px 0px -55% 0px',threshold:0});
  pageLinks.forEach(link => { const section=document.getElementById(link.hash.slice(1)); if(section)observer.observe(section); });
}
