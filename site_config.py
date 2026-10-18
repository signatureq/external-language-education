"""Public address shared by metadata and legal documents."""
SITE_ORIGIN = 'https://externlangschool.ru'
SITE_NAME = 'External Language Education'


def site_url(filename='index.html'):
    return SITE_ORIGIN + '/' + ('' if filename == 'index.html' else filename)
