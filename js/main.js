// Инициализация AOS с базовыми настройками
document.addEventListener('DOMContentLoaded', () => {
    AOS.init({
        duration: 800,          
        offset: 100,          
        once: false,           
        easing: 'ease',        
        delay: 100,           
        disable: 'mobile'      
    });
});

// Видео модальное окно
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('videoModal');
    const video = modal.querySelector('video');
    const closeBtn = modal.querySelector('.close-modal');

    // Открытие модального окна
    document.querySelectorAll('.video-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const videoUrl = this.getAttribute('data-video');
            video.src = videoUrl;
            modal.classList.add('active');
            video.play();
        });
    });

    // Закрытие модального окна
    closeBtn.addEventListener('click', function() {
        modal.classList.remove('active');
        video.pause();
        video.currentTime = 0;
    });

    // Закрытие по клику вне видео
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
            video.pause();
            video.currentTime = 0;
        }
    });

    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            modal.classList.remove('active');
            video.pause();
            video.currentTime = 0;
        }
    });
}); 