"""Build the External Language Education website."""
from pathlib import Path
from html import escape
import json
from hashlib import sha256
from legal_documents import legal_pages, render_document

ROOT = Path(__file__).resolve().parent
def asset_version(name):
    return sha256((ROOT / name).read_bytes().replace(b'\r\n', b'\n')).hexdigest()[:10]

STYLE_VERSION = asset_version('styles.css')
COLOR_PALETTE_VERSION = asset_version('palette-colors.css')
REFINEMENT_VERSION = asset_version('refinements.css')
LEGAL_STYLE_VERSION = asset_version('legal.css')
SCRIPT_VERSION = asset_version('app.js')
PALETTE_INIT = """const paletteBackgrounds={black:"#111111",sand:"#f8f3ea",navy:"#14212a"};const requestedPalette=new URLSearchParams(location.search).get("palette");const activePalette=Object.hasOwn(paletteBackgrounds,requestedPalette)?requestedPalette:"sand";document.documentElement.dataset.palette=activePalette;document.querySelector('meta[name="theme-color"]').content=paletteBackgrounds[activePalette]"""
LANGUAGES = json.loads((ROOT / 'content.json').read_text(encoding='utf-8'))
LEGAL_SETTINGS = json.loads((ROOT / 'legal-settings.json').read_text(encoding='utf-8'))
PHOTO = 'assets/photos/blue/'
NAV = [('languages.html','Языки'),('format.html','Обучение'),('admission.html','Поступление'),('tuition.html','Стоимость'),('schedule.html','Расписание')]

def icon(name='arrow'):
    paths={'person':'<circle cx="12" cy="7" r="3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>','arrow':'<path d="M4 12h15m-6-6 6 6-6 6"/>','plus':'<path d="M12 5v14M5 12h14"/>','menu':'<path d="M4 8h16M4 16h16"/>','check':'<path d="m5 12 4 4L19 6"/>','pin':'<path d="M19 10c0 5-7 11-7 11s-7-6-7-11a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>','clock':'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>','globe':'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>','chevron':'<path d="m9 5 7 7-7 7"/>'}
    return f'<svg viewBox="0 0 24 24" aria-hidden="true">{paths[name]}</svg>'
ARROW=icon()
BRAND='<a class="brand" href="index.html" aria-label="External Language Education — главная"><span class="brand-word">external<span class="brand-dot">.</span></span><span class="brand-line">LANGUAGE EDUCATION</span></a>'

def button(label,href='#application',kind='',extra=''):
    return f'<a class="button {kind}" href="{href}" {extra}>{label}{ARROW}</a>'

def photo(src,alt,cls='',caption=None,eager=False):
    tone=' class="destination-photo"' if any(src==l['photo'] for l in LANGUAGES) else ''
    return f'<figure class="photo {cls}"><img{tone} src="{src}" alt="{alt}" width="1536" height="1024" {"fetchpriority=high" if eager else "loading=lazy"}>'+ (f'<figcaption>{caption}</figcaption>' if caption else '')+'</figure>'

def header(page):
    contact='index.html#application' if page in ('privacy.html','consent.html') else '#application'
    links=''.join(f'<a href="{href}"'+(' aria-current="page"' if href==page else '')+f'>{label}</a>' for href,label in NAV)
    return f'''<a class="skip-link" href="#main">Перейти к содержимому</a><header class="site-header"><div class="shell header-inner">{BRAND}<nav class="desktop-nav" aria-label="Основная навигация">{links}</nav><a class="header-contact" href="{contact}">Связаться {ARROW}</a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-navigation">Меню {icon('menu')}</button></div><nav class="mobile-nav" id="mobile-navigation" aria-label="Мобильная навигация" hidden><a href="index.html">Главная</a>{links}<a href="interview.html">Пробный урок</a><a href="{contact}">Связаться со школой</a></nav></header>'''

def footer(contact='#application'):
    return f'''<footer class="site-footer"><div class="shell footer-grid"><div>{BRAND}<p>Иностранные языки экстерном<br>Учимся в Москве<br>Говорим с целым миром</p></div><div><h3>Школа</h3><a href="format.html">Как мы учимся</a><a href="admission.html">Поступление</a><a href="interview.html">Собеседование и пробный урок</a><a href="schedule.html">Расписание</a><a href="tuition.html">Стоимость и оплата</a></div><div><h3>Языки</h3>{''.join(f'<a href="{l["slug"]}.html">{l["name"]}</a>' for l in LANGUAGES)}</div><div><h3>Начнём с разговора</h3><p>Узнайте об интенсиве<br>Выберите свою группу</p>{button('Оставить заявку',href=contact,kind='button-small')}<p class="footer-place">{icon('pin')} Москва</p></div></div><div class="shell footer-bottom"><span>© 2026 External Language Education</span><nav class="footer-legal" aria-label="Правовая информация"><a href="privacy.html">Политика обработки данных</a><a href="consent.html">Согласие на обработку данных</a></nav><a class="back-top" href="#main">Наверх</a></div></footer>'''

def application(language=None):
    opts='<option value="" disabled'+(' selected' if not language else '')+'>Выберите язык</option>'+''.join(f'<option'+(' selected' if language==l['name'] else '')+f'>{l["name"]}</option>' for l in LANGUAGES)+'<option>Помогите выбрать</option>'
    return f'''<section class="contact-section graphite-section" id="application" aria-labelledby="application-heading"><div class="shell contact-grid"><div class="contact-copy"><h2 id="application-heading">Давайте<br><span>познакомимся</span></h2><p>Расскажем об интенсиве, поможем выбрать язык и группу. Можно прийти с конкретной задачей или просто с желанием учиться.</p><div class="contact-location">{icon('pin')} Очное обучение в Москве</div><div class="contact-photo"><img src="{PHOTO}study-detail.jpg" alt="Иллюстрация совместной работы с языком" width="1536" height="1024" loading="lazy"><span>Иллюстрация учебного формата</span></div></div>
    <form class="application-form" aria-label="Заявка на обучение" novalidate><div class="form-fields"><p class="form-intro">Оставьте контакты — мы поможем выбрать язык и группу</p><div class="form-selection" hidden><span data-selection-text></span><button class="clear-selection" type="button">Сбросить</button></div>
    <fieldset class="application-inputs" disabled><legend class="sr-only">Данные для обращения в школу</legend>
    <div class="field"><label for="name">Как вас зовут?</label><input id="name" name="name" autocomplete="given-name" placeholder="Ваше имя" required maxlength="80"></div>
    <div class="field"><label for="phone">Телефон</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 ___ ___ __ __" required maxlength="25"></div>
    <div class="field"><label for="language">Какой язык хотите изучать?</label><select id="language" name="language" required>{opts}</select></div>
    <div class="field"><label for="comment">Ваш вопрос <span>необязательно</span></label><textarea id="comment" name="comment" maxlength="1000" placeholder="Например, хочу начать с нуля"></textarea></div>
    <div class="consent-field"><label class="consent-label" for="consent"><input id="consent" name="consent" type="checkbox" required value="yes"><span>Я даю <a href="consent.html" target="_blank" rel="noopener noreferrer">согласие на обработку персональных данных</a> для ответа на моё обращение</span></label></div>
    </fieldset>
    <p class="form-privacy">Подробнее о работе с данными — в <a href="privacy.html" target="_blank" rel="noopener noreferrer">политике обработки персональных данных</a></p>
    <button class="button" type="submit" disabled aria-describedby="application-availability"><span>Отправка пока недоступна</span>{ARROW}</button><p class="form-availability" id="application-availability" role="status" tabindex="-1">Приём заявок через сайт пока недоступен. Пожалуйста, зайдите позже.</p></div></form></div></section>'''


def faq(items,title='То, что хочется уточнить'):
    return f'<section class="shell section faq-layout"><div><h2>{title}</h2><p>Не нашли свой вопрос?<br>Обсудим его при знакомстве.</p><a class="text-link" href="#application">Задать вопрос {ARROW}</a></div><div class="faq-list">'+''.join(f'<details><summary>{q}{icon("plus")}</summary><div class="faq-answer"><p>{a}</p></div></details>'for q,a in items)+'</div></section>'

GENERAL_FAQ=[
    ('Можно ли учиться просто для себя?','Да. Конкретная цель не обязательна: можно учить язык для общения, путешествий, интереса к культуре или собственного удовольствия.'),
    ('Сколько времени займёт обучение?','Единого срока нет. Прогресс зависит от начального уровня, регулярности занятий и самостоятельной работы. На знакомстве обсудим ваши ожидания и готовность к интенсиву.'),
    ('Где проходят занятия?','Очные занятия планируются в Москве. Место проведения уточняется; подробности сообщим при знакомстве. Пробный урок проходит онлайн.'),
    ('Нужно ли оплачивать весь курс сразу?','Нет. Вы выбираете оплату раз в неделю, раз в две недели или за четыре учебные недели. Выбранный ритм фиксируется в договоре.'),
    ('Можно ли начать с нуля?','Да. Перед началом определим ваш уровень и подберём группу. Дети, подростки и взрослые занимаются отдельно.')
]

def section_title(title,copy='',link=None):
    return f'<div class="section-heading"><h2>{title}</h2><div>'+ (f'<p>{copy}</p>' if copy else '')+(f'<a class="text-link" href="{link[0]}">{link[1]} {ARROW}</a>' if link else '')+'</div></div>'

def language_card(l):
    region='asia' if l['slug'] in ['chinese','korean'] else 'europe'
    return f'''<a class="language-card" href="{l['slug']}.html" data-region="{region}"><div class="language-image"><img class="destination-photo" src="{l['photo']}" alt="{l['place']}" width="920" height="880" loading="lazy"><span>{l['country']}</span></div><div class="language-card-body"><h3>{l['name']}</h3><p>{l['lead']}</p><span class="card-link">О программе {ARROW}</span></div></a>'''

def languages_section(full=False):
    choice=f'''<a class="language-choice" href="#application" data-language="Помогите выбрать"><div class="language-image"><img src="{PHOTO}study-detail.jpg" alt="Иллюстрация совместной работы с учебными материалами" width="1536" height="1024" loading="lazy"><span>Иллюстрация учебного формата</span></div><div class="language-card-body"><h3>Поможем<br>выбрать язык</h3><p>Познакомьтесь с форматом и задайте вопросы специалисту школы</p><span class="card-link">Посоветоваться {ARROW}</span></div></a>'''
    content = '<section class="shell section" id="languages">'+section_title('Выберите<br>свой язык','С нуля или с вашего уровня. Для общения, путешествий и просто для себя.')+'<div class="language-filters" role="group" aria-label="Показать языки"><button class="filter-button" data-filter="all" aria-pressed="true">Все языки <span>7</span></button><button class="filter-button" data-filter="europe" aria-pressed="false">Европейские</button><button class="filter-button" data-filter="asia" aria-pressed="false">Азиатские</button></div><div class="language-grid" data-active-filter="all">'+''.join(language_card(l)for l in LANGUAGES)+choice+'</div></section>'
    return graphite_band(content) if full else content

def format_feature(include_times=False):
    times=compact_schedule() if include_times else ''
    return f'''<section class="blue-section round-section" id="practice"><div class="shell section"><div class="feature-heading"><h2>Экстерн:<br>больше времени на язык</h2><p>Интенсивное обучение для тех, кто хочет быстрее осваивать язык. Новую тему сразу применяем в заданиях и живой речи.</p></div><div class="practice-grid">{photo(PHOTO+'group-practice.jpg','Иллюстрация разговорной практики в небольшой группе','practice-photo','Иллюстрация учебного формата')}<div class="practice-content"><div class="large-equation">4 × 4<span>часа в день · дня в неделю</span></div><p>16 часов практики в неделю: разбираем, пробуем, говорим и закрепляем.</p><ul class="check-list"><li>{icon('check')} До 8 человек в группе</li><li>{icon('check')} Группы по возрасту и уровню</li><li>{icon('check')} Личная работа, диалоги и обсуждения</li></ul>{button('Подробнее о формате','format.html','button-white')}</div></div>{times}</div></section>'''

def schedule_cards():
    return f'''<section class="shell section" id="times">{section_title('Утро или вечер','Четыре часа занятий и 30 минут перерыва. Конкретные дни недели уточняются при выборе группы.')}<div class="time-grid"><a class="time-card" href="schedule.html?time=morning"><img src="{PHOTO}group-practice.jpg" alt="Иллюстрация группового занятия" width="1536" height="1024" loading="lazy"><div class="time-card-content"><h3>Утро</h3><strong>9:00–13:30</strong><p>Первая половина дня для языка</p><span class="time-link">Выбрать утро {ARROW}</span></div></a><a class="time-card" href="schedule.html?time=evening"><img src="{PHOTO}study-detail.jpg" alt="Иллюстрация работы с учебными материалами" width="1536" height="1024" loading="lazy"><div class="time-card-content"><h3>Вечер</h3><strong>17:30–22:00</strong><p>Вечернее погружение в практику</p><span class="time-link">Выбрать вечер {ARROW}</span></div></a></div><p class="section-note">Изображения иллюстрируют обучение, а не конкретное помещение школы.</p></section>'''

def calculator(title='Прозрачная цена<br>Гибкий ритм оплаты',heading='h2'):
    tuition_photo=photo(PHOTO+'study-detail.jpg','Иллюстрация работы с учебными материалами','tuition-photo','Иллюстрация учебного формата') if heading=='h1' else ''
    return f'''<div class="calculator"><div><{heading}>{title}</{heading}><p>900 ₽ за час. Без предоплаты за весь курс. Пакеты на одну, две или четыре учебные недели.</p><a class="text-link" href="tuition.html#price-table">Посмотреть все расчёты {ARROW}</a></div><div class="calculator-box"><div class="cadence-controls" role="group" aria-label="Период оплаты"><button class="cadence-button" data-cadence="week" aria-pressed="true">Неделя</button><button class="cadence-button" data-cadence="fortnight" aria-pressed="false">Две недели</button><button class="cadence-button" data-cadence="month" aria-pressed="false">Четыре недели</button></div><div class="price-live" aria-live="polite"><div class="calculator-price" data-price>14 400 ₽</div><div class="calculator-period" data-price-period>/ неделя</div><p data-price-description>16 часов · 4 учебных дня</p></div>{button('Обсудить обучение')}<p class="calculator-note">Цена часа одинакова для любого периода</p></div>{tuition_photo}</div>'''

def admissions_steps():
    steps=[('Знакомство','Расскажем об интенсиве и обсудим ваши ожидания'),('Уровень и группа','Определим знания онлайн и подберём подходящую группу'),('Пробный урок','Познакомитесь с преподавателем один на один, онлайн'),('Начало обучения','Вы примете решение, а мы закрепим условия в договоре')]
    return '<div class="admission-steps">'+''.join(f'<article><span class="step-number">0{i}</span><h3>{t}</h3><p>{p}</p></article>'for i,(t,p)in enumerate(steps,1))+'</div>'

def breadcrumb(title):
    return f'<div class="shell breadcrumb"><a href="index.html">Главная</a><span>/</span><span>{title.replace("<br>"," ")}</span></div>'

def page_nav(links):
    return '<nav class="page-nav" aria-label="Содержание страницы"><div class="shell">'+''.join(f'<a href="#{i}">{t}</a>' for i,t in links)+'</div></nav>'

def innerhero(title,lead,image,alt,links,caption=''):
    media=photo(image,alt,'inner-hero-photo',caption,True)
    return breadcrumb(title)+f'''<section class="shell inner-hero"><div class="inner-hero-copy"><h1>{title}</h1><p>{lead}</p>{button('Обсудить обучение')}</div>{media}</section>'''+page_nav(links)

def graphite_band(content):
    return '<div class="graphite-section">'+content+'</div>'

def feature(title,paras,image,alt,section_id='',reverse=False,caption='Иллюстрация учебного формата',extra=''):
    return f'<section class="shell section editorial-feature {"reverse" if reverse else ""}"'+(f' id="{section_id}"'if section_id else '')+f'><div class="feature-copy"><h2>{title}</h2>'+''.join(f'<p>{p}</p>'for p in paras)+extra+'</div>'+photo(image,alt,'feature-photo',caption)+'</section>'

def detail_block(title,paragraphs,section_id='',extra=''):
    return f'<section class="shell section reading-section"'+(f' id="{section_id}"'if section_id else '')+f'><h2>{title}</h2><div class="prose">'+''.join(f'<p>{p}</p>'for p in paragraphs)+extra+'</div></section>'

def price_table():
    rows=[('Один учебный день','4 часа + 30 минут перерыва','4 часа','3 600 ₽'),('Одна неделя','4 учебных дня','16 часов','14 400 ₽'),('Две недели','8 учебных дней','32 часа','28 800 ₽'),('Четыре недели','16 учебных дней','64 часа','57 600 ₽')]
    return '<div class="price-table-wrap"><table class="price-table"><caption class="sr-only">Стоимость обучения при цене 900 рублей за час</caption><thead><tr><th scope="col">Период</th><th scope="col">Практика</th><th scope="col">Стоимость</th></tr></thead><tbody>'+''.join(f'<tr><td><b>{a}</b><span>{b}</span></td><td>{c}</td><td>{d}</td></tr>'for a,b,c,d in rows)+'</tbody></table><p class="section-note">Месячный пакет в расчёте — ровно четыре учебные недели. Общая продолжительность программы определяется индивидуально.</p></div>'

def small_cta(title,copy,href,label):
    return f'<aside class="shell related-cta"><div><h2>{title}</h2><p>{copy}</p></div>{button(label,href)}</aside>'

def compact_schedule():
    return f'''<div class="rhythm-options"><a href="schedule.html?time=morning"><strong>Утро</strong><span>9:00–13:30</span>{ARROW}</a><a href="schedule.html?time=evening"><strong>Вечер</strong><span>17:30–22:00</span>{ARROW}</a><p>В каждом окне — 4 часа занятий и 30 минут перерыва</p></div>'''

def essentials(items):
    return '<dl class="essentials">'+''.join(f'<div><dt>{a}</dt><dd>{b}</dd></div>'for a,b in items)+'</dl>'

def level_strip():
    return '<div class="level-list"><span>0–A1</span><span>A1–B2</span><span>B2</span></div><p class="small">Уровни групп. Подбор после определения текущих знаний.</p>'

def lesson_flow():
    stages=[('Разобраться','Новая тема и понятное объяснение'),('Попробовать','Задания и применение материала'),('Заговорить','Диалоги, пары и обсуждения'),('Закрепить','Повторение и разбор ошибок')]
    return '<ol class="lesson-flow">'+''.join(f'<li><strong>{a}</strong><span>{b}</span></li>'for a,b in stages)+'</ol>'

def program_section(l):
    return '<section class="light-section round-section" id="program"><div class="shell section program-overview"><div><h2>Больше практики<br>Больше общения</h2><p class="section-note">'+l['intro']+'</p>'+lesson_flow()+'</div><div class="program-evidence"><ul class="check-list">'+''.join(f'<li>{icon("check")}{x}</li>'for x in l['focus'])+'</ul>'+essentials([('4 × 4','часа в день · дня в неделю'),('до 8','человек в группе')])+'<div class="pair-practice" aria-label="Восемь участников могут работать в четырёх парах"><div class="pair-diagram" aria-hidden="true">'+''.join('<span>'+icon('person')+icon('person')+'</span>'for _ in range(4))+'</div><p>В полной группе — 4 пары для диалогов или 2 команды по 4. Преподаватель работает с каждым участником и со всей группой.</p></div>'+button('Формат и расписание','format.html','button-outline')+'</div></div></section>'

home=f'''<section class="world-hero" aria-labelledby="home-heading"><img class="world-panorama" src="{PHOTO}world-panorama.jpg" alt="Художественная панорама городов мира" width="2048" height="1153" fetchpriority="high"><div class="world-sky"></div><div class="shell hero-content"><h1 id="home-heading">Иностранные языки<br>экстерном</h1><p>Интенсивное обучение в Москве<br>для тех, кто хочет больше практики и быстрее осваивать язык</p>{button('Познакомиться со школой')}</div><div class="shell hero-bottom"><p>Больше языка<br><span>Больше живого общения</span></p><dl class="hero-facts"><div><dt>4</dt><dd>часа в день</dd></div><div><dt>4</dt><dd>дня в неделю</dd></div><div><dt><small>до</small> 8</dt><dd>человек в группе</dd></div></dl></div></section>
<section class="intro-section light-section round-section"><div class="shell intro-grid"><div><h2>Экономьте время<br><span>Учите язык быстрее</span></h2><p>Интенсивное обучение для тех, кто готовится к поступлению или хочет быстрее освоить иностранный язык. Сосредоточьтесь на языке сейчас, чтобы скорее применять его в учёбе, работе и жизни.</p></div><div class="intro-note"><h3>Больше практики<br>каждую неделю</h3><p>4 часа в день, 4 дня в неделю. Разбираем новую тему, сразу пробуем её в речи и закрепляем на следующих занятиях.</p><a class="text-link" href="format.html">Как проходит обучение {ARROW}</a></div></div></section>'''
home+=languages_section()+format_feature(True)
home+='<section class="light-section round-section"><div class="shell section">'+calculator()+'</div></section>'
home+='<section class="graphite-section round-section" id="start"><div class="shell section">'+section_title('Сначала познакомимся','Собеседование и пробный урок помогут понять, подходит ли вам интенсив.',('interview.html','Как проходит знакомство'))+admissions_steps()+'</div></section>'
home+=faq([GENERAL_FAQ[4],GENERAL_FAQ[1],GENERAL_FAQ[2]],'Коротко о главном')

admission=innerhero('Поступление:<br>начнём со знакомства','Можно прийти с конкретной задачей или просто с желанием учить язык. Важно понять ваш уровень и готовность к интенсивному ритму.',PHOTO+'group-practice.jpg','Иллюстрация общения на языковом занятии',[('path','Как поступить'),('goals','Что обсудим'),('levels','Подбор группы')],'Иллюстрация учебного формата')
admission+=graphite_band('<section class="shell section" id="path">'+section_title('Четыре шага к занятиям')+admissions_steps()+'</section>')
admission+=detail_block('Разговор о вас',['На собеседовании расскажем о методике, расписании, правилах и месте проведения занятий. Обсудим ожидания и готовность заниматься четыре дня в неделю.','Можно учить язык для себя, работы, экзамена или переезда. Если есть личная цель и предпочтительные сроки, обсудим их вместе с текущими знаниями и возможностями заниматься.'], 'goals',button('О собеседовании и пробном уроке','interview.html','button-outline'))
admission+=feature('Группа по уровню<br>и возрасту',['Перед началом определим текущие знания онлайн. Дети, подростки и взрослые занимаются отдельно.','После знакомства предложим конкретный вариант: уровень, группу, расписание и преподавателя. На пробном уроке вы оцените формат до оплаты основного обучения. Решение остаётся за вами.'],PHOTO+'study-detail.jpg','Иллюстрация работы с учебными материалами','levels',reverse=True,extra=level_strip()+'<p class="section-note">В договоре фиксируются программа, стоимость, условия и выбранный ритм оплаты. Первый учебный день начинается со знакомства с группой.</p>')

interview=innerhero('Собеседование<br>и пробный урок','Познакомьтесь со школой и преподавателем до решения об обучении.',PHOTO+'teacher-online.jpg','Иллюстрация индивидуального онлайн-урока',[('conversation','Собеседование'),('trial','Пробный урок'),('decision','Что дальше')],'Иллюстрация онлайн-занятия')
interview+=graphite_band(detail_block('Внимательный разговор',['Расскажем о школе, методике и интенсивном ритме. Вы сможете задать вопросы и понять, подходит ли вам формат.','Обсудим ваш опыт и ожидания. Личная цель может помочь выбрать акценты, но учиться можно и просто для себя.'],'conversation'))
interview+=feature('Один на один<br>с преподавателем',['Пробный урок проходит онлайн, один на один. Преподаватель знакомится с вашей целью, темпом и особенностями, чтобы с первого группового занятия учитывать именно вас.','Вы оцениваете, понятно ли объяснение, комфортно ли говорить и хочется ли продолжить. Решение остаётся за вами.'],PHOTO+'study-detail.jpg','Иллюстрация учебных материалов','trial',extra='<div class="inline-facts"><span>Онлайн</span><span>Индивидуально</span><span>До решения об обучении</span></div>')
interview+=small_cta('Что дальше?','При продолжении согласуем группу, расписание и условия договора.','admission.html','Порядок поступления').replace('<aside class="shell related-cta">','<aside class="shell related-cta" id="decision">')

formatpage=innerhero('Иностранный язык<br>в интенсивном ритме','Экстерн — это больше практики в течение недели: новая тема, задания, диалог и закрепление.',PHOTO+'group-practice.jpg','Иллюстрация живого общения на занятии',[('practice','Ритм'),('groups','Группы'),('comfort','Комфорт')],'Иллюстрация учебного формата')
formatpage+=graphite_band('<section class="shell section" id="practice">'+section_title('Как устроен экстерн','4 часа в день, 4 дня в неделю. Достаточно времени, чтобы разобраться в теме и применить её в речи.')+essentials([('16 часов','практики в неделю'),('до 8','человек в группе'),('30 минут','перерыв у взрослых'),('7 языков','на выбор')])+compact_schedule()+'</section>')
formatpage+='<section class="pale-section round-section" id="groups"><div class="shell section">'+section_title('Каждому возрасту —<br>своя группа','Подбор по возрасту и текущим знаниям. Преподаватель знакомится с вами на индивидуальном пробном уроке.')+'''<div class="age-panels"><article class="graphite-card" id="adults"><h3>Взрослые</h3><p>Личная работа, диалоги и обсуждения. Преподаватель уделяет внимание каждому и ведёт группу как единое целое.</p><dl class="adult-facts"><div><dt>до 8</dt><dd>человек в группе</dd></div><div><dt>4 пары</dt><dd>или 2 команды по 4 в полной группе</dd></div></dl></article><article id="teenagers"><h3>Подростки</h3><p>Отдельные группы со своим темпом. Интерактивные задания и общение со сверстниками.</p></article><article id="children"><h3>Дети</h3><p>Языковая среда с учётом возраста. Для самых маленьких — формат мини-детского сада на иностранном языке.</p></article></div>'''+level_strip()+'</div></section>'
formatpage+=feature('Учиться<br>и чувствовать себя свободно',['У взрослых один 30-минутный перерыв. У детских групп паузы наступают чаще, чем у подростковых. Во время перерыва или занятий можно поесть, выпить чай, кофе или какао.','Если нужно встать, выйти или ответить на срочный звонок, это нормально. На занятиях есть место личной работе и общению с группой.'],PHOTO+'study-detail.jpg','Иллюстрация учебных материалов и чая','comfort',extra=button('Познакомиться с преподавателем','interview.html','button-outline'))

schedule=breadcrumb('Расписание')+'<section class="shell service-heading"><div class="service-copy"><h1>Расписание</h1><p>Четыре дня в неделю, утром или вечером. Конкретные дни согласуем при выборе группы.</p></div>'+photo(PHOTO+'study-detail.jpg','Иллюстрация учебного стола','schedule-intro-photo','Иллюстрация учебного формата',True)+'</section>'+page_nav([('choose-time','Утро или вечер'),('rhythm','Учебная неделя')])
schedule+=graphite_band(f'''<section class="shell section schedule-section" id="choose-time">{section_title('Когда вам удобнее?','В каждом окне — четыре часа занятий и 30 минут перерыва.')}<div class="schedule-switch" role="group" aria-label="Выбор времени обучения"><button data-schedule="morning" class="schedule-choice" aria-pressed="true">Утро · 9:00–13:30</button><button data-schedule="evening" class="schedule-choice" aria-pressed="false">Вечер · 17:30–22:00</button></div><div class="selected-schedule"><figure class="photo schedule-photo"><img data-schedule-image src="{PHOTO}group-practice.jpg" alt="Иллюстрация группового занятия" width="1536" height="1024" loading="lazy"><figcaption>Иллюстрация учебного формата</figcaption></figure><div aria-live="polite"><h3 data-schedule-title>Утро · 9:00–13:30</h3><p data-schedule-description>Первая половина дня для языка — 4 часа занятий и 30 минут перерыва</p>{button('Выбрать это время',extra='data-select-time="morning"')}<p class="small">Наличие мест и дни занятий уточняются при знакомстве.</p></div></div></section>''')
schedule+=detail_block('Практика и паузы',['За четыре часа можно разобрать тему, отработать её и закрепить в речи. Завершающую часть занятия посвящаем говорению, интерактивам и разбору ошибок. Между учебными днями остаются паузы для отдыха и самостоятельной работы.'],'rhythm',essentials([('4 дня','занятий в неделю'),('16 часов','практики в неделю'),('64 часа','за четыре недели')])+'<p class="section-note">Почему не пять дней подряд? Четыре учебных дня оставляют время на паузы и помогают встроить интенсив в жизнь. Важно регулярно возвращаться к языку и сохранять силы для практики.</p>')

payment_questions=[('Нужно ли оплачивать весь курс сразу?',GENERAL_FAQ[3][1]),('Почему месяц — это четыре недели?','Для расчёта пакета используем четыре учебные недели: 64 часа, 57 600 ₽. Это не календарный месяц.'),('Можно ли менять ритм оплаты?','Период оплаты выбирается один раз и фиксируется в договоре. Вопросы об условиях обсуждаются со школой.')]
tuition=breadcrumb('Стоимость и оплата')+'<section class="light-section tuition-hero" id="payment"><div class="shell section">'+calculator('Стоимость<br>и оплата','h1')+'</div></section>'+page_nav([('payment','Период оплаты'),('price-table','Все суммы'),('payment-questions','Условия')])
tuition+=graphite_band('<section class="shell section" id="price-table">'+section_title('Все суммы<br>в одном месте')+price_table()+'</section>')
tuition+='<div id="payment-questions">'+faq(payment_questions,'Как устроена оплата')+'</div>'

catalog=innerhero('Семь языков<br>Ваш выбор','Можно начать с нуля, продолжить обучение или выбрать новый язык просто из интереса.',PHOTO+'world-panorama.jpg','Художественная панорама городов мира',[('languages','Выбрать язык'),('common','Общий формат')],'Иллюстрация учебного формата')
catalog+=languages_section(True)
catalog+=detail_block('Общий формат',['Все языки изучаем интенсивно: четыре часа в день, четыре дня в неделю. Группы до восьми человек, по возрасту и уровню.'],'common',button('Подробнее об обучении','format.html','button-outline'))

pages={'index.html':dict(title='Иностранные языки экстерном в Москве',content=home),'admission.html':dict(title='Поступление',content=admission),'interview.html':dict(title='Собеседование и пробный урок',content=interview),'format.html':dict(title='Формат обучения',content=formatpage),'schedule.html':dict(title='Расписание',content=schedule),'tuition.html':dict(title='Стоимость и оплата',content=tuition),'languages.html':dict(title='Языки и программы',content=catalog)}
for l in LANGUAGES:
    body=innerhero(l['title'].replace(' язык ',' язык<br>'),l['lead'],l['photo'],l['place'],[('program','Как учимся'),('start-level','С чего начать')],l['place'])
    body+=program_section(l)
    body+=graphite_band(feature('Начать с вашего уровня',['Перед началом определим текущие знания и подберём группу. Познакомитесь с преподавателем на индивидуальном онлайн-уроке.','Учебный прогресс зависит от стартового уровня, регулярности и самостоятельной работы. Фиксированного срока для всех нет.'],PHOTO+'teacher-online.jpg','Иллюстрация знакомства с преподавателем онлайн','start-level',reverse=True,extra=level_strip()))
    body+=small_cta('900 ₽ за час','14 400 ₽ за неделю: 16 часов практики. Оплата пакетами.','tuition.html','Все варианты оплаты')
    body+='<section class="shell other-languages"><h2>Другие языки</h2><div>'+''.join(f'<a href="{a["slug"]}.html">{a["name"]} {ARROW}</a>'for a in LANGUAGES if a!=l)+'</div></section>'
    pages[l['slug']+'.html']=dict(title=l['title'],content=body,language=l['name'])

for filename, document in legal_pages(LEGAL_SETTINGS).items():
    pages[filename]=dict(title=document['title'],description=document['description'],content=render_document(document,LEGAL_SETTINGS),legal=True)

for filename,page in pages.items():
    title=escape(page['title'])
    description=escape(page.get('description',page['title']+'. Иностранные языки экстерном в Москве. 4 часа в день, 4 дня в неделю, группы до 8 человек.'),quote=True)
    legal_style=f'<link rel="stylesheet" href="legal.css?v={LEGAL_STYLE_VERSION}">' if page.get('legal') else ''
    application_html='' if page.get('legal') else application(page.get('language'))
    footer_html=footer('index.html#application' if page.get('legal') else '#application')
    html=f'''<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{title} — External Language Education</title><meta name="description" content="{description}"><meta name="theme-color" content="#111111"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=neutral"><link rel="preload" href="assets/fonts/body-normal-400.ttf" as="font" type="font/ttf" crossorigin><link rel="preload" href="assets/fonts/body-normal-600.ttf" as="font" type="font/ttf" crossorigin><script>{PALETTE_INIT}</script><link rel="stylesheet" href="styles.css?v={STYLE_VERSION}"><link rel="stylesheet" href="palette-colors.css?v={COLOR_PALETTE_VERSION}"><link rel="stylesheet" href="refinements.css?v={REFINEMENT_VERSION}">{legal_style}<script defer src="app.js?v={SCRIPT_VERSION}"></script></head><body class="page-{filename[:-5]}">{header(filename)}<main id="main">{page['content']}{application_html}</main>{footer_html}</body></html>'''
    (ROOT/filename).write_text(html.replace('<br>', ' <br>'),encoding='utf-8',newline='\n')
print(f'Built {len(pages)} pages.')
