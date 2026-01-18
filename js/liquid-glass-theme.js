/**
 * Liquid Glass Theme Manager v2
 * Полная интеграция: кнопки, блоки, dock
 */
class LiquidGlassTheme {
    constructor() {
        this.isEnabled = false;
        this.isLoading = false;
        this.scriptsLoaded = false;
        this.glassElements = [];
        this.dock = null;
        this.STORAGE_KEY = 'liquidGlassEnabled';
    }

    /**
     * Инициализация
     */
    init() {
        if (!this.checkWebGL()) {
            console.warn('Liquid Glass: WebGL не поддерживается');
            this.hideToggle();
            return;
        }

        this.createToggleButton();

        const saved = localStorage.getItem(this.STORAGE_KEY);
        if (saved === 'true') {
            // Небольшая задержка для загрузки страницы
            setTimeout(() => this.enable(), 500);
        }

        console.log('Liquid Glass: initialized');
    }

    checkWebGL() {
        try {
            const canvas = document.createElement('canvas');
            return !!(window.WebGLRenderingContext &&
                (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
        } catch (e) {
            return false;
        }
    }

    createToggleButton() {
        const button = document.createElement('button');
        button.id = 'liquid-glass-toggle';
        button.className = 'glass-toggle-btn';
        button.innerHTML = '🔮';
        button.title = 'Переключить Liquid Glass стиль';
        button.addEventListener('click', () => this.toggle());
        document.body.appendChild(button);
        this.toggleButton = button;
    }

    hideToggle() {
        const btn = document.getElementById('liquid-glass-toggle');
        if (btn) btn.style.display = 'none';
    }

    toggle() {
        if (this.isLoading) return;
        this.isEnabled ? this.disable() : this.enable();
    }

    async enable() {
        if (this.isEnabled || this.isLoading) return;
        this.isLoading = true;

        if (this.toggleButton) {
            this.toggleButton.classList.add('loading');
        }

        try {
            if (!this.scriptsLoaded) {
                await this.loadDependencies();
            }

            document.body.classList.add('liquid-glass-mode');

            // Ждём немного для рендеринга страницы
            await new Promise(r => setTimeout(r, 100));

            // Создаём все glass элементы
            await this.createAllGlassElements();

            this.isEnabled = true;
            this.savePreference(true);

            if (this.toggleButton) {
                this.toggleButton.classList.add('active');
            }

            this.setupScrollRefresh();

            console.log('Liquid Glass: enabled');
        } catch (error) {
            console.error('Liquid Glass: error enabling', error);
        } finally {
            this.isLoading = false;
            if (this.toggleButton) {
                this.toggleButton.classList.remove('loading');
            }
        }
    }

    disable() {
        if (!this.isEnabled) return;

        if (this.scrollHandler) {
            window.removeEventListener('scroll', this.scrollHandler);
            this.scrollHandler = null;
        }

        document.body.classList.remove('liquid-glass-mode');
        this.destroyAllGlassElements();

        this.isEnabled = false;
        this.savePreference(false);

        if (this.toggleButton) {
            this.toggleButton.classList.remove('active');
        }

        console.log('Liquid Glass: disabled');
    }

    async loadDependencies() {
        try {
            if (!window.html2canvas) {
                await this.loadScript('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js');
            }
            if (!window.Container) {
                await this.loadScript('liquid-glass-js-main/container.js');
            }
            if (!window.Button) {
                await this.loadScript('liquid-glass-js-main/button.js');
            }
            this.scriptsLoaded = true;
        } catch (error) {
            console.error('Liquid Glass: Failed to load dependencies', error);
            throw error;
        }
    }

    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            document.head.appendChild(script);
        });
    }

    /**
     * Создание всех glass элементов
     */
    async createAllGlassElements() {
        // Сброс состояния
        if (window.Container) {
            Container.pageSnapshot = null;
            Container.isCapturing = false;
            Container.waitingForSnapshot = [];
            Container.instances = [];
        }

        // 1. Создать glass dock
        this.createGlassDock();

        // 2. Заменить кнопки на glass - ОТКЛЮЧЕНО
        // this.replaceButtons();

        // Ждём инициализации WebGL
        await new Promise(r => setTimeout(r, 800));
    }

    /**
     * Создание плавающего glass dock
     */
    createGlassDock() {
        // Контейнер для dock
        const dockWrapper = document.createElement('div');
        dockWrapper.id = 'glass-dock-wrapper';
        dockWrapper.className = 'glass-dock-wrapper';

        // Glass container для dock
        const dockContainer = new Container({
            type: 'pill',
            borderRadius: 30,
            tintOpacity: 0.25
        });

        dockContainer.element.classList.add('glass-dock');

        // Кнопки навигации
        const navItems = [
            { text: '🏠', href: '#home', title: 'Начало' },
            { text: '👤', href: '#about', title: 'Обо мне' },
            { text: '💼', href: '#projects', title: 'Проекты' },
            { text: '✉️', href: '#contact', title: 'Контакты' }
        ];

        navItems.forEach(item => {
            const btn = new Button({
                text: item.text,
                size: '28',
                type: 'circle',
                onClick: () => {
                    const target = document.querySelector(item.href);
                    if (target) {
                        // Используем Lenis если доступен, иначе нативный скролл
                        if (window.lenis) {
                            window.lenis.scrollTo(item.href);
                        } else {
                            target.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                }
            });
            btn.element.title = item.title;
            dockContainer.addChild(btn);
        });

        dockWrapper.appendChild(dockContainer.element);
        document.body.appendChild(dockWrapper);

        this.dock = dockWrapper;
        this.glassElements.push({ type: 'dock', element: dockWrapper, container: dockContainer });
    }

    /**
     * Замена обычных кнопок на glass
     */
    replaceButtons() {
        // Находим ВСЕ элементы с классом .btn
        const allButtons = document.querySelectorAll('.btn');

        allButtons.forEach(btn => {
            // Определяем тип кнопки (pill или rounded)
            // По умолчанию rounded, но если это кнопка "Скачать CV" или "Отправить", можно сделать pill
            // Или использовать простую логику: если текст короткий - pill, иначе rounded?
            // Оставим пока rounded как дефолт, но можно проверить классы или контекст.

            let type = 'rounded';

            // Если кнопка в contact form или download-box - делаем pill визуально красивее
            if (btn.closest('.download-box') || btn.closest('form')) {
                type = 'pill';
            }
            // Вторая кнопка в Home (Скачать CV) тоже лучше смотрится как pill
            if (btn.getAttribute('download') !== null) {
                type = 'pill';
            }

            this.replaceWithGlassButton(btn, type);
        });
    }

    /**
     * Заменить обычную кнопку на glass
     */
    replaceWithGlassButton(originalBtn, type = 'rounded') {
        // Проверяем, не заменена ли уже кнопка
        if (originalBtn.nextSibling && originalBtn.nextSibling.classList && originalBtn.nextSibling.classList.contains('glass-button')) {
            return;
        }

        const text = originalBtn.textContent.trim();
        const href = originalBtn.getAttribute('href');
        const download = originalBtn.getAttribute('download');
        const target = originalBtn.getAttribute('target');

        const glassBtn = new Button({
            text: text,
            size: '18',
            type: type,
            onClick: () => {
                // Если есть href, используем нашу навигацию
                if (href) {
                    if (href.startsWith('#')) {
                        const el = document.querySelector(href);
                        if (el) {
                            if (window.lenis) {
                                window.lenis.scrollTo(href);
                            } else {
                                el.scrollIntoView({ behavior: 'smooth' });
                            }
                        }
                    } else if (download) {
                        const link = document.createElement('a');
                        link.href = href;
                        link.download = download;
                        link.click();
                    } else {
                        window.open(href, target || '_self');
                    }
                } else {
                    // Для всего остального (формы, видео-кнопки, просто JS события)
                    // кликаем по оригинальной кнопке
                    originalBtn.click();
                }
            }
        });

        glassBtn.element.classList.add('glass-replaced-btn');

        // Скрываем оригинал, но оставляем его рабочим для событий (форм и т.д.)
        // Используем opacity: 0 и pointer-events: none, чтобы он не мешал, но был в DOM
        originalBtn.style.opacity = '0';
        originalBtn.style.position = 'absolute';
        originalBtn.style.pointerEvents = 'none';
        originalBtn.style.zIndex = '-1'; // Убираем назад

        originalBtn.parentNode.insertBefore(glassBtn.element, originalBtn.nextSibling);

        this.glassElements.push({
            type: 'button',
            original: originalBtn,
            glass: glassBtn
        });
    }

    /**
     * Удаление всех glass элементов
     */
    destroyAllGlassElements() {
        this.glassElements.forEach(item => {
            if (item.type === 'dock' && item.element) {
                item.element.remove();
            } else if (item.type === 'button') {
                // Восстановить оригинальную кнопку
                if (item.original) {
                    item.original.style.display = '';
                    item.original.style.opacity = '';
                    item.original.style.position = '';
                    item.original.style.pointerEvents = '';
                }
                if (item.glass && item.glass.element) {
                    item.glass.element.remove();
                }
            }
        });

        this.glassElements = [];
        this.dock = null;

        if (window.Container) {
            Container.instances = [];
            Container.pageSnapshot = null;
        }
    }

    setupScrollRefresh() {
        let isScrolling = false;
        let lastScrollY = window.scrollY;

        this.scrollHandler = () => {
            if (Math.abs(window.scrollY - lastScrollY) < 50) return; // Меньше порог для плавности

            if (!isScrolling) {
                isScrolling = true;
                requestAnimationFrame(() => {
                    this.refreshSnapshot();
                    lastScrollY = window.scrollY;
                    setTimeout(() => { isScrolling = false; }, 200); // Throttling
                });
            }
        };

        window.addEventListener('scroll', this.scrollHandler, { passive: true });
    }

    async refreshSnapshot() {
        if (!window.Container || !window.html2canvas) return;

        try {
            const snapshot = await html2canvas(document.body, {
                scale: 1,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null,
                ignoreElements: (element) => {
                    return (
                        element.classList.contains('glass-container') ||
                        element.classList.contains('glass-button') ||
                        element.classList.contains('glass-button-text') ||
                        element.classList.contains('glass-dock-wrapper') ||
                        element.tagName === 'IFRAME'
                    );
                }
            });

            Container.pageSnapshot = snapshot;

            const img = new Image();
            img.src = snapshot.toDataURL();
            img.onload = () => {
                Container.instances.forEach(container => {
                    if (container.gl_refs?.gl && container.gl_refs?.texture && !container.parent) {
                        const gl = container.gl_refs.gl;
                        gl.bindTexture(gl.TEXTURE_2D, container.gl_refs.texture);
                        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
                        if (container.render) container.render();
                    }
                });
            };
        } catch (error) {
            console.warn('Liquid Glass: snapshot refresh failed', error);
        }
    }

    savePreference(enabled) {
        localStorage.setItem(this.STORAGE_KEY, enabled.toString());
    }
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    window.liquidGlassTheme = new LiquidGlassTheme();
    window.liquidGlassTheme.init();
});
