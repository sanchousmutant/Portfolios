
const cat = document.getElementById('cat-container');
const eyeL = document.getElementById('eye-l');
const eyeR = document.getElementById('eye-r');
const mouth = document.getElementById('cat-mouth');
const speech = document.getElementById('cat-speech');

let isWalking = false;
let isTalking = false;

const MOUTH_CLOSED = "M90 90 Q 95 95 100 90 Q 105 95 110 90";
const MOUTH_OPEN = "M92 92 Q 100 105 108 92";

// Слежение глазами
document.addEventListener('mousemove', (e) => {
    const rect = cat.getBoundingClientRect();
    const catCenter = {
        x: rect.left + rect.width / 2,
        y: rect.top + rect.height / 2
    };

    const angle = Math.atan2(e.clientY - catCenter.y, e.clientX - catCenter.x);
    const distance = 3; 

    const moveX = Math.cos(angle) * distance;
    const moveY = Math.sin(angle) * distance;

    eyeL.setAttribute('transform', `translate(${moveX}, ${moveY})`);
    eyeR.setAttribute('transform', `translate(${moveX}, ${moveY})`);
});

// Взаимодействие
cat.addEventListener('mousedown', () => {
    showSpeech("Мур-р-р...");
    cat.classList.add('purring');
});

cat.addEventListener('mouseup', () => {
    cat.classList.remove('purring');
});

cat.addEventListener('click', () => {
    showSpeech("Погладь меня!");
});

cat.addEventListener('dblclick', () => {
    window.open('https://sanchousmutant.github.io/Kitt-Cues/', '_blank');
});

let lastTap = 0;
cat.addEventListener('touchend', (e) => {
    const currentTime = new Date().getTime();
    const tapLength = currentTime - lastTap;
    if (tapLength < 300 && tapLength > 0) {
        window.open('https://sanchousmutant.github.io/Kitt-Cues/', '_blank');
        e.preventDefault();
    }
    lastTap = currentTime;
});

function showSpeech(text) {
    if (isTalking) return;
    isTalking = true;

    speech.innerText = text;
    speech.style.display = 'block';
    mouth.setAttribute('d', MOUTH_OPEN);

    const catTransform = window.getComputedStyle(cat).getPropertyValue('transform');
    if (catTransform === 'matrix(-1, 0, 0, 1, 0, 0)') {
        speech.style.transform = 'translateX(-50%) scaleX(-1)';
    } else {
        speech.style.transform = 'translateX(-50%) scaleX(1)';
    }
    
    setTimeout(() => {
        speech.style.display = 'none';
        mouth.setAttribute('d', MOUTH_CLOSED);
        isTalking = false;
    }, 1500);
}

// Прогулка
function walk() {
    if (isWalking) return;
    
    isWalking = true;
    cat.classList.add('walking');

    const maxX = window.innerWidth - 170;
    const maxY = window.innerHeight - 170;
    
    const randomX = Math.random() * maxX;
    const randomY = Math.random() * maxY;

    const rect = cat.getBoundingClientRect();
    if (randomX > rect.left) {
        cat.style.transform = 'scaleX(1)';
    } else {
        cat.style.transform = 'scaleX(-1)';
    }

    cat.style.left = `${randomX}px`;
    cat.style.bottom = `${window.innerHeight - randomY - 150}px`;

    setTimeout(() => {
        cat.classList.remove('walking');
        isWalking = false;
        scheduleNextWalk();
    }, 2000);
}

function scheduleNextWalk() {
    setTimeout(walk, 4000 + Math.random() * 6000);
}

function randomTalk() {
    if (!isTalking && !isWalking) {
        const idleSounds = [
                "Мяу!",
                "Мрр-р-р-р",
                "Мяу-мяу!",
                "Я полосатый!",
                "Где рыбка?",
                "Покорми меня!",
                "Хочу играть!",
                "Я тебя вижу...",
                "Мур-мяу!",
                "Почеши за ушком",
                "Я тут главный",
                "Псс, есть сметана?",
                "Сплю...",
                "Мяу! Это мой сайт!",
                "Хочу на крышу!",
                "Где мышка?",
                "Лови хвост!",
                "Поймай меня, если сможешь!",
                "Хочу на ручки!",
                "Ты кто?",
                "Дай вкусняшку!",
                "Kitt-Cues"
            ];
        showSpeech(idleSounds[Math.floor(Math.random() * idleSounds.length)]);
    }
    scheduleNextTalk();
}

function scheduleNextTalk() {
    setTimeout(randomTalk, 3000 + Math.random() * 4000); // Between 3 and 7 seconds
}

scheduleNextWalk();
scheduleNextTalk();
