# Персональный сайт-портфолио

Персональный сайт-портфолио, построенный на чистом HTML/CSS/JS с тёмным дизайном, WebGL-эффектами и PHP-админкой для управления проектами.

## Live Demo

**[https://sanchousmutant.github.io/Portfolios/](https://sanchousmutant.github.io/Portfolios/)**

## Технологии

| Категория | Технология |
|---|---|
| Разметка | HTML5 |
| Стили | CSS3 (переменные, flexbox, media queries) |
| Логика | Vanilla JavaScript (ES6+) |
| Плавная прокрутка | [Lenis](https://lenis.darkroom.engineering/) |
| Анимации скролла | [AOS](https://michalsnik.github.io/aos/) |
| Иконки | [Font Awesome 6.4.2](https://fontawesome.com/) |
| Glass-эффекты | [Liquid Glass JS](https://github.com/dashersw/liquid-glass-js) (WebGL) |
| DOM-снимки | [html2canvas](https://html2canvas.hertzen.com/) |
| Форма обратной связи | [Formspree](https://formspree.io/) |
| Карты | Google Maps Embed |
| Серверная часть | PHP (админ-панель) |
| Админ UI | [Tailwind CSS](https://tailwindcss.com/) (CDN) |

Все клиентские зависимости подключены через CDN — `package.json` не требуется.

## Разделы сайта

1. **Начало** — приветственная секция с фото и кратким представлением
2. **Обо мне** — навыки (прогресс-бары), опыт и образование
3. **Проекты** — карточки работ с ссылками на GitHub и демо-видео
4. **Контакты** — форма обратной связи (Formspree), карта Google Maps, соцсети

## Функциональности

- Адаптивный дизайн (десктоп, планшет, мобильные)
- Переключаемая тема **Liquid Glass** с WebGL frosted-glass эффектом
- Плавная прокрутка (Lenis) и анимации при скролле (AOS)
- Модальное окно для просмотра видео проектов
- Гамбургер-меню на мобильных (чистый CSS)
- Интерактивный SVG-кот: следит за курсором, гуляет по экрану, говорит фразы
- Форма обратной связи через Formspree
- Встроенная карта Google Maps

## Админ-панель (admin.php)

PHP-панель для управления проектами через веб-интерфейс:

- Авторизация с CSRF-защитой и bcrypt-хешированием
- Загрузка проектов: изображение, видео (опционально), ссылка на GitHub
- Валидация файлов по расширению и MIME-типу
- Автосинхронизация: загруженные проекты сохраняются в `data/projects.json` и автоматически инжектируются в `index.html`
- Удаление проектов (с удалением файлов с диска)

Подробнее — в файле `manual.md`.

## Особенности дизайна

- Тёмный интерфейс (`#0d1117`) с акцентным цветом `#00ff7f`
- CSS-переменные для единообразия стилей
- Кастомный скроллбар
- Полноэкранный фон с градиентным оверлеем
- Fluid-типографика через `calc()` с viewport-единицами

## Поддержка устройств

| Устройство | Разрешение |
|---|---|
| Десктоп | 1200px+ |
| Планшет | 768px – 1199px |
| Мобильные | 320px – 767px |

## Установка и запуск

1. Клонируйте репозиторий:
```bash
git clone https://github.com/sanchousmutant/Portfolios.git
```

2. Откройте `index.html` в браузере.

> **Для Liquid Glass эффектов** сайт необходимо запускать через локальный HTTP-сервер:
> - **Python:** `python -m http.server 5000`
> - **VS Code:** расширение "Live Server"

Подробнее о структуре проекта и зависимостях — в `Guides/`.

## Структура проекта

```
├── index.html              # Главная страница (SPA)
├── admin.php               # Админ-панель
├── style/
│   ├── style.css           # Основные стили
│   ├── cat.css             # Стили SVG-кота
│   ├── liquid-glass-override.css
│   └── liquid-glass-lib/   # Стили библиотеки glass
├── js/
│   ├── main.js             # Lenis, AOS, видео-модал
│   ├── cat.js              # Интерактивный SVG-кот
│   ├── liquid-glass-theme.js
│   └── liquid-glass-lib/   # WebGL-компоненты glass
├── img/                    # Изображения
├── video/                  # Видео демо проектов
├── data/
│   └── projects.json       # Хранилище проектов (из админки)
├── pdf/                    # Резюме
├── Guides/                 # Документация
├── manual.md               # Мануал по админ-панели
└── .htaccess               # Защита директорий
```

## Контакты

- Telegram: [@sanchomutant](https://t.me/sanchomutant)
- WhatsApp: [+7(999)450-29-24](https://wa.me/79994502924)
- GitHub: [sanchousmutant](https://github.com/sanchousmutant)

## Лицензия

MIT License — можно свободно использовать и модифицировать код.
