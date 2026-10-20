"""Search metadata and discovery files for the static production website."""
from html import escape
import json
from xml.etree.ElementTree import Element, SubElement, tostring

from site_config import SITE_NAME, SITE_ORIGIN, site_url

DESCRIPTIONS = {
    'index.html': 'Иностранные языки экстерном в Москве: 7 языков, занятия по 4 часа 4 раза в неделю, группы до 8 человек. Формат обучения, расписание и стоимость.',
    'admission.html': 'Как начать обучение в External Language Education: знакомство со школой, определение уровня, подбор группы, пробный урок и условия поступления.',
    'interview.html': 'Собеседование и индивидуальный онлайн-урок с преподавателем External Language Education. Познакомьтесь с форматом обучения перед принятием решения.',
    'format.html': 'Языковой интенсив 4 × 4 в Москве: группы до 8 человек, разговорная практика и личное внимание преподавателя. Отдельные группы для взрослых, подростков и детей.',
    'schedule.html': 'Расписание языкового интенсива: 4 часа в день, 4 раза в неделю. Утреннее обучение 9:00–13:30 или вечернее 17:30–22:00. Очные занятия в Москве.',
    'tuition.html': 'Стоимость языкового интенсива: 900 ₽ в час, 14 400 ₽ в неделю. Оплата за одну, две или четыре учебные недели. Все расчёты и условия оплаты.',
    'languages.html': 'Семь иностранных языков экстерном в Москве: английский, итальянский, испанский, французский, немецкий, китайский и корейский. Выберите свою программу.',
}


def description_for(filename, page, languages):
    if filename in DESCRIPTIONS:
        return DESCRIPTIONS[filename]
    language = next((item for item in languages if filename == item['slug'] + '.html'), None)
    if language:
        return f"{language['title']} в Москве: занятия по 4 часа 4 раза в неделю, группы до 8 человек. Начните с нуля или продолжайте с вашего уровня. 900 ₽ в час."
    return page['description']


def metadata(filename, page, languages):
    url = site_url(filename)
    title = f"{page['title']} — {SITE_NAME}"
    description = description_for(filename, page, languages)
    language = next((item for item in languages if filename == item['slug'] + '.html'), None)
    photo = language['photo'] if language else 'assets/photos/blue/world-panorama.jpg'
    image_url = SITE_ORIGIN + '/' + photo
    robots = 'noindex,follow' if page.get('legal') else 'index,follow,max-image-preview:large'
    graph = [
        {'@type': 'EducationalOrganization', '@id': SITE_ORIGIN + '/#school',
         'name': SITE_NAME, 'alternateName': 'External', 'url': site_url(),
         'description': 'Интенсивное обучение иностранным языкам экстерном в Москве',
         'logo': {'@type': 'ImageObject', 'url': SITE_ORIGIN + '/favicon-512.png', 'width': 512, 'height': 512},
         'areaServed': {'@type': 'City', 'name': 'Москва'}},
        {'@type': 'WebSite', '@id': SITE_ORIGIN + '/#website', 'url': site_url(),
         'name': SITE_NAME, 'alternateName': 'External', 'inLanguage': 'ru-RU',
         'publisher': {'@id': SITE_ORIGIN + '/#school'}},
        {'@type': 'WebPage', '@id': url + '#webpage', 'url': url, 'name': title,
         'description': description, 'inLanguage': 'ru-RU',
         'isPartOf': {'@id': SITE_ORIGIN + '/#website'},
         'about': {'@id': SITE_ORIGIN + '/#school'}},
    ]
    if filename != 'index.html':
        crumbs = [{'@type': 'ListItem', 'position': 1, 'name': 'Главная', 'item': site_url()}]
        if language:
            crumbs.append({'@type': 'ListItem', 'position': 2, 'name': 'Языки', 'item': site_url('languages.html')})
        crumbs.append({'@type': 'ListItem', 'position': len(crumbs) + 1, 'name': page['title'], 'item': url})
        graph.append({'@type': 'BreadcrumbList', '@id': url + '#breadcrumbs', 'itemListElement': crumbs})
        graph[2]['breadcrumb'] = {'@id': url + '#breadcrumbs'}
    data = json.dumps({'@context': 'https://schema.org', '@graph': graph}, ensure_ascii=False).replace('<', '\\u003c')
    e = lambda value: escape(value, quote=True)
    return f'''<title>{e(title)}</title><meta name="description" content="{e(description)}"><meta name="robots" content="{robots}"><link rel="canonical" href="{e(url)}"><link rel="icon" type="image/png" sizes="120x120" href="favicon.png"><link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png"><meta property="og:type" content="website"><meta property="og:site_name" content="{SITE_NAME}"><meta property="og:locale" content="ru_RU"><meta property="og:title" content="{e(title)}"><meta property="og:description" content="{e(description)}"><meta property="og:url" content="{e(url)}"><meta property="og:image" content="{image_url}"><meta property="og:image:alt" content="Иностранные языки — External Language Education"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{e(title)}"><meta name="twitter:description" content="{e(description)}"><meta name="twitter:image" content="{image_url}"><script type="application/ld+json">{data}</script>'''


def write_discovery(root, pages):
    namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9'
    sitemap = Element('urlset', xmlns=namespace)
    for filename, page in pages.items():
        if page.get('legal'):
            continue
        node = SubElement(sitemap, 'url')
        SubElement(node, 'loc').text = site_url(filename)
    (root / 'sitemap.xml').write_bytes(b'<?xml version="1.0" encoding="UTF-8"?>\n' + tostring(sitemap, encoding='utf-8') + b'\n')
    (root / 'robots.txt').write_text('User-agent: *\nAllow: /\nDisallow: /palettes.html\nDisallow: /cms-publishing/\nDisallow: /cms-cache/\nDisallow: /cms-site.php\n\nSitemap: ' + SITE_ORIGIN + '/sitemap.xml\n', encoding='utf-8')
