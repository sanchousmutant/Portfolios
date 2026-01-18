# Liquid Glass Theme Guide

Руководство по использованию Liquid Glass стиля в портфолио.

## Компоненты

### 1. Toggle Button (🔮)
Кнопка переключения в левом верхнем углу:
- **Неактивна** — тёмный фон
- **Активна** — зелёный фон, glass-эффекты включены
- **Загрузка** — мигающая анимация

### 2. Glass Dock
Плавающая панель навигации внизу экрана:
- 🏠 — Начало (#home)
- 👤 — Обо мне (#about)
- 💼 — Проекты (#projects)
- ✉️ — Контакты (#contact)

Клик по кнопке плавно прокручивает к нужной секции.

### 3. Glass Buttons
Автоматическая замена обычных кнопок на glass-версии:
- Кнопки в секции Home ("обо мне", "скачать CV")
- Кнопки проектов
- Кнопка отправки формы

## Файлы

| Файл | Описание |
|------|----------|
| `js/liquid-glass-theme.js` | Основная логика управления темой |
| `style/liquid-glass-override.css` | CSS стили для glass-элементов |
| `liquid-glass-js-main/container.js` | WebGL контейнер (из библиотеки) |
| `liquid-glass-js-main/button.js` | WebGL кнопка (из библиотеки) |
| `liquid-glass-js-main/glass.css` | Базовые glass стили |

## Подключение

В `index.html`:
```html
<!-- Glass CSS -->
<link rel="stylesheet" href="liquid-glass-js-main/glass.css">
<link rel="stylesheet" href="style/liquid-glass-override.css">

<!-- Glass JS (в конце body) -->
<script src="js/liquid-glass-theme.js"></script>
```

## API

```javascript
// Глобальный объект
window.liquidGlassTheme

// Методы
liquidGlassTheme.enable()   // Включить glass-режим
liquidGlassTheme.disable()  // Выключить
liquidGlassTheme.toggle()   // Переключить

// Проверка состояния
liquidGlassTheme.isEnabled  // true/false
```

## Настройки Dock

В `liquid-glass-theme.js`, метод `createGlassDock()`:
```javascript
const navItems = [
    { text: '🏠', href: '#home', title: 'Начало' },
    { text: '👤', href: '#about', title: 'Обо мне' },
    { text: '💼', href: '#projects', title: 'Проекты' },
    { text: '✉️', href: '#contact', title: 'Контакты' }
];
```

## Кастомизация CSS

В `liquid-glass-override.css`:
```css
/* Стиль toggle кнопки */
.glass-toggle-btn { ... }
.glass-toggle-btn.active { ... }

/* Стиль dock */
.glass-dock-wrapper { ... }
.glass-dock { ... }

/* Glass-режим для секций */
.liquid-glass-mode .navbar { ... }
.liquid-glass-mode .about .row .box { ... }
```

## Требования
- WebGL поддержка в браузере
- Современный браузер (Chrome, Firefox, Safari, Edge)

## Ограничения
- Google Maps iframe исключён из glass-эффектов (CORS)
- На мобильных устройствах эффекты могут влиять на производительность
