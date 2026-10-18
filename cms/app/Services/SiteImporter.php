<?php

namespace App\Services;

use App\Models\ContentDocument;
use App\Models\Media;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\DB;
use Masterminds\HTML5;

class SiteImporter
{
    private HTML5 $html;

    public function __construct()
    {
        $this->html = new HTML5(['disable_html_ns' => true]);
    }

    public const PAGES = ['index.html' => 'Главная', 'languages.html' => 'Все языки', 'format.html' => 'Формат обучения', 'admission.html' => 'Поступление', 'interview.html' => 'Собеседование и пробный урок', 'tuition.html' => 'Стоимость и оплата', 'schedule.html' => 'Расписание', 'privacy.html' => 'Политика обработки данных', 'consent.html' => 'Согласие на обработку данных', 'italian.html' => 'Итальянский', 'english.html' => 'Английский', 'spanish.html' => 'Испанский', 'french.html' => 'Французский', 'chinese.html' => 'Китайский', 'korean.html' => 'Корейский', 'german.html' => 'Немецкий'];

    public function import(string $source): void
    {
        if (ContentDocument::exists()) {
            throw new \RuntimeException('Содержание уже импортировано. Повторный импорт перезаписал бы правки.');
        }
        foreach (array_keys(self::PAGES) as $file) {
            if (! is_file($source.'/'.$file)) {
                throw new \RuntimeException('Не найдена страница '.$file);
            }
        }
        DB::transaction(function () use ($source) {
            foreach (self::PAGES as $file => $name) {
                $dom = $this->html->loadHTML(file_get_contents($source.'/'.$file));
                $xpath = new \DOMXPath($dom);
                foreach (['header' => ['//header', 'Меню сайта'], 'footer' => ['//footer', 'Нижняя часть сайта'], 'contact' => ['//*[@id="application"]', 'Блок знакомства и форма']] as $key => [$query,$label]) {
                    $node = $xpath->query($query)->item(0);
                    if (! $node) {
                        continue;
                    }
                    if (! ContentDocument::where('key', 'shared.'.$key)->exists()) {
                        $this->create('shared.'.$key, 'shared', $label, $this->html->saveHTML($node));
                    }
                    $node->parentNode->replaceChild($dom->createComment('CMS_SHARED:'.$key), $node);
                }
                $title = $xpath->query('//title')->item(0)?->textContent ?? $name;
                $description = $xpath->query('//meta[@name="description"]')->item(0)?->getAttribute('content') ?? '';
                $kind = in_array($file, array_slice(array_keys(self::PAGES), 9)) ? 'language' : 'page';
                $this->create($file, $kind, $name, $this->html->saveHTML($dom), ['seo_title' => $title, 'seo_description' => $description]);
            }
            ContentDocument::create(['key' => 'settings', 'kind' => 'settings', 'name' => 'Стоимость и расписание', 'template' => '', 'schema' => [
                ['title' => 'Стоимость обучения', 'hint' => 'Укажите цену часа. Стоимость дня, недели и пакетов пересчитывается автоматически по формату 4 × 4. Изменения вступят в силу после публикации.', 'fields' => [['key' => 'rate', 'label' => 'Цена часа, ₽', 'type' => 'number', 'hint' => 'Сейчас 900 ₽. Все суммы на сайте и в калькуляторе обновятся вместе.']]],
                ['title' => 'Время занятий', 'hint' => 'Формат времени: 09:00–13:30. Расписание обновится на всех страницах.', 'fields' => [['key' => 'morning', 'label' => 'Утренние занятия', 'type' => 'time', 'hint' => 'Начало и окончание, включая перерыв.'], ['key' => 'evening', 'label' => 'Вечерние занятия', 'type' => 'time', 'hint' => 'Начало и окончание, включая перерыв.']]],
            ], 'draft' => ['rate' => 900, 'morning' => '9:00–13:30', 'evening' => '17:30–22:00'], 'published' => ['rate' => 900, 'morning' => '9:00–13:30', 'evening' => '17:30–22:00'], 'published_at' => now()]);
        });
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source.'/assets/photos', \FilesystemIterator::SKIP_DOTS)) as $entry) {
            $path = $entry->getPathname();
            $size = @getimagesize($path);
            if (! $size) {
                continue;
            }
            $relative = substr($path, strlen(rtrim($source, '/')) + 1);
            Media::firstOrCreate(['path' => $relative], ['name' => match (basename($path)) {
                'world-panorama.jpg' => 'Панорама мира','group-practice.jpg' => 'Групповое занятие','study-detail.jpg' => 'Учебные материалы','italy.jpg' => 'Италия — Манарола','london.jpg' => 'Англия — Лондон','paris.jpg' => 'Франция — Париж','spain.jpg' => 'Испания — Мадрид','china.jpg' => 'Китай — Великая стена','seoul.jpg' => 'Корея — Сеул', 'berlin.jpg' => 'Германия — Берлин', 'teacher-online.jpg' => 'Онлайн-знакомство с преподавателем', 'korea.jpg' => 'Корея — Сеул','germany.jpg' => 'Германия — Берлин',default => ucfirst(pathinfo($path, PATHINFO_FILENAME))
            }, 'alt' => '', 'width' => $size[0], 'height' => $size[1]]);
        }
    }

    private function create(string $key, string $kind, string $name, string $source, array $extra = []): void
    {
        $dom = $this->html->loadHTML($kind === 'shared' ? '<!doctype html><html><head></head><body>'.$source.'</body></html>' : $source);
        $xpath = new \DOMXPath($dom);
        $scope = $kind === 'shared' ? ($xpath->query('//body')->item(0)) : $xpath->query('//main')->item(0);
        $schema = [];
        $data = $extra;
        $index = 0;
        if ($scope) {
            $roots = [];
            foreach (iterator_to_array($scope->childNodes) as $child) {
                if ($child instanceof DOMElement) {
                    $roots[] = $child;
                }
            }
            foreach ($roots as $root) {
                $heading = $xpath->query('.//h1|.//h2|.//h3', $root)->item(0);
                $title = trim(preg_replace('/\s+/u', ' ', $heading?->textContent ?? ''));
                if ($title === '') {
                    $title = match ($root->tagName) {
                        'nav' => 'Навигация','header' => 'Меню сайта','footer' => 'Нижняя часть сайта',default => 'Дополнительные тексты'
                    };
                }
                $fields = [];
                $walk = function (DOMNode $node) use (&$walk, &$fields, &$data, &$index) {
                    if ($node instanceof DOMElement) {
                        if (in_array(strtolower($node->tagName), ['script', 'style', 'svg', 'noscript', 'option']) || str_contains($node->getAttribute('class'), 'sr-only') || str_contains($node->getAttribute('class'), 'breadcrumb')) {
                            return;
                        }
                        if ($node->tagName === 'img' && $node->hasAttribute('src')) {
                            foreach (['src' => 'Фотография', 'alt' => 'Описание фотографии'] as $attr => $label) {
                                $id = 'f'.++$index;
                                $data[$id] = $node->getAttribute($attr);
                                $node->setAttribute($attr, 'CMSVALUE_'.$id.'_END');
                                $fields[] = ['key' => $id, 'label' => $label, 'type' => $attr === 'src' ? 'image' : 'text', 'hint' => $attr === 'src' ? 'Выберите снимок из раздела «Фотографии». Сначала загрузите туда новый снимок, если нужно.' : 'Кратко опишите изображение для поиска и посетителей, которые его не видят.'];
                            }
                        }
                        if ($node->hasAttribute('placeholder')) {
                            $id = 'f'.++$index;
                            $data[$id] = $node->getAttribute('placeholder');
                            $node->setAttribute('placeholder', 'CMSVALUE_'.$id.'_END');
                            $fields[] = ['key' => $id, 'label' => 'Подсказка в поле', 'type' => 'text', 'hint' => 'Пример ответа внутри поля формы.'];
                        }
                    }
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $value = trim($node->textContent);
                        if ($value === '' || $value === '.' || preg_match('/^[\d\s.,×₽:–\-]+$/u', $value)) {
                            return;
                        }
                        $id = 'f'.++$index;
                        $data[$id] = $value;
                        $tag = $node->parentNode->nodeName;
                        $label = match ($tag) {
                            'h1' => 'Главный заголовок','h2' => 'Заголовок блока','h3','h4' => 'Подзаголовок','a','button' => 'Текст ссылки или кнопки','summary' => 'Вопрос','li' => 'Пункт списка','label' => 'Название поля','figcaption' => 'Подпись к фотографии',default => mb_strlen($value) > 80 ? 'Описание' : 'Текст'
                        };
                        $fields[] = ['key' => $id, 'label' => $label, 'type' => mb_strlen($value) > 110 ? 'textarea' : 'text', 'hint' => null];
                        $node->nodeValue = str_replace($value, 'CMSVALUE_'.$id.'_END', $node->nodeValue);
                    } else {
                        foreach (iterator_to_array($node->childNodes) as $child) {
                            $walk($child);
                        }
                    }
                };
                $walk($root);
                if ($fields) {
                    $schema[] = ['title' => $title, 'hint' => null, 'fields' => $fields];
                }
            }
        }
        // A shared fragment must not acquire a document wrapper.
        $template = $kind === 'shared' ? implode('', array_map(fn ($n) => $this->html->saveHTML($n), iterator_to_array($scope->childNodes))) : $this->html->saveHTML($dom);
        ContentDocument::create(['key' => $key, 'kind' => $kind, 'name' => $name, 'template' => $template, 'schema' => $schema, 'draft' => $data, 'published' => $data, 'published_at' => now()]);
    }
}
