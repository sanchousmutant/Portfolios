# Структура проекта Portfolios

> ⚠️ **ВАЖНО ДЛЯ АГЕНТОВ**: При любых изменениях структуры проекта ОБЯЗАТЕЛЬНО обновляйте этот файл!

## Обновлено: 2026-01-18 (v2 - добавлен Liquid Glass)

## Дерево проекта

```
Portfolios-main/
├── index.html              # Главная страница сайта
├── README.md               # Описание проекта
│
├── Guides/                 # Документация проекта
│   ├── PROJECT_STRUCTURE.md    # Этот файл (структура проекта)
│   └── LIQUID_GLASS_GUIDE.md   # Гайд по интеграции Liquid Glass
│
├── style/                  # Стили
│   ├── style.css                   # Основные стили сайта
│   └── liquid-glass-override.css   # [NEW] Стили для glass-режима
│
├── js/                     # JavaScript файлы
│   ├── main.js                     # Основная логика (Lenis, AOS, модалы)
│   └── liquid-glass-theme.js       # [NEW] Переключатель Liquid Glass темы
│
├── img/                    # Изображения
│   ├── bg.jpg              # Фоновое изображение
│   ├── ico.png             # Favicon
│   ├── photo_1.png         # Фото профиля
│   └── p1-p4.png           # Скриншоты проектов
│
├── pdf/                    # PDF файлы
│   └── Aleksandr_Anatolevich_Nikitin.pdf  # Резюме
│
├── video/                  # Видео демонстрации
│   ├── виджет.mp4
│   ├── openmedia.mp4
│   └── openwrt.mp4
│
└── liquid-glass-js-main/   # Библиотека Liquid Glass (WebGL)
    ├── container.js        # Класс контейнера с glass эффектом
    ├── button.js           # Класс кнопки с glass эффектом
    ├── glass.css           # CSS стили для glass элементов
    ├── controls.js         # Панель управления эффектами
    ├── controls.css        # Стили панели управления
    ├── styles.css          # Базовые стили демо
    ├── demo.js             # Демо-скрипт
    ├── demo.css            # Стили демо
    └── index.html          # Демо-страница
```

## Требования

> ⚠️ **ВАЖНО:** Для работы Liquid Glass эффектов сайт должен запускаться через HTTP сервер, а не через `file://`!

**Запуск локального сервера:**
```bash
# Вариант 1: Python
python -m http.server 5000

# Вариант 2: npx
npx serve -p 5000

# Вариант 3: VS Code Live Server
# Правый клик на index.html → "Open with Live Server"
```

## Зависимости (CDN)

- **Font Awesome 6.4.2** - иконки
- **AOS** - анимации при скролле
- **Lenis** - плавная прокрутка
- **html2canvas** (для Liquid Glass) - создание снимков страницы

## Стилевые переменные (CSS Variables)

Определены в `:root` в `style/style.css`:
- `--main-clr: #00ff7f` - акцентный цвет
- `--primary-bg-clr: #0d1117` - основной фон
- `--secondary-bg-clr: #222` - вторичный фон
- `--primary-text-clr: #fff` - основной цвет текста
- `--secondary-text-clr: #eee` - вторичный цвет текста

## Инструкции для агентов

### При добавлении новых файлов:
1. Обновите дерево проекта выше
2. Укажите назначение файла
3. Обновите дату "Обновлено"

### При изменении структуры:
1. Отразите изменения в дереве
2. Обновите описания если изменилось назначение
3. Проверьте актуальность зависимостей
