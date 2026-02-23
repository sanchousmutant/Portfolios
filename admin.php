<?php
/**
 * Admin panel for portfolio site.
 * Allows uploading projects (image + video + GitHub URL)
 * and generates HTML snippets for index.html.
 */

session_start();

// --- AUTH SETTINGS ---
$admin_login = 'admin';
// Hash for password '12345' — regenerate with: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
$admin_password_hash = '$2y$12$0kpB7rOymgQlIs2H/GkKju6FsOIai/6BR08sGs7FEHaK8VLC53VHC'; // PLACEHOLDER — will be set on first run if not changed

// Generate a real hash on first visit (remove this block after setting your password)
if ($admin_password_hash === '$2y$10$YourHashHere') {
    $admin_password_hash = password_hash('12345', PASSWORD_DEFAULT);
}

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// --- ALLOWED FILE TYPES ---
$allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$allowed_video_ext = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
$allowed_image_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$allowed_video_mime = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];

function validate_file(array $file, array $allowed_ext, array $allowed_mime): string|false {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        return false;
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed_mime, true)) {
        return false;
    }
    return $ext;
}

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- LOGIN ---
$login_error = '';
if (isset($_POST['do_login'])) {
    if (verify_csrf() && $_POST['login'] === $admin_login && password_verify($_POST['password'], $admin_password_hash)) {
        $_SESSION['auth'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $login_error = "Неверный логин или пароль!";
    }
}

$is_auth = isset($_SESSION['auth']) && $_SESSION['auth'] === true;

// --- DIRECTORIES ---
$imgDir = 'img/';
$videoDir = 'video/';
$dataDir = 'data/';
$jsonFile = $dataDir . 'projects.json';

// --- LOAD PROJECTS ---
function load_projects(string $jsonFile): array {
    if (!file_exists($jsonFile)) {
        return [];
    }
    $data = json_decode(file_get_contents($jsonFile), true);
    return is_array($data) ? $data : [];
}

function save_projects(string $jsonFile, array $projects): void {
    $dir = dirname($jsonFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($jsonFile, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function sync_index_html(string $jsonFile): string {
    $indexPath = __DIR__ . '/index.html';
    if (!file_exists($indexPath)) {
        return 'Ошибка: index.html не найден.';
    }

    $html = file_get_contents($indexPath);
    $startMarker = '<!-- ADMIN-PROJECTS-START -->';
    $endMarker = '<!-- ADMIN-PROJECTS-END -->';

    $startPos = strpos($html, $startMarker);
    $endPos = strpos($html, $endMarker);

    if ($startPos === false || $endPos === false) {
        return 'Ошибка: маркеры ADMIN-PROJECTS не найдены в index.html.';
    }

    $projects = load_projects($jsonFile);
    $indent = '            '; // 12 spaces to match existing indentation
    $generated = '';

    foreach ($projects as $proj) {
        $altText = htmlspecialchars($proj['title']);
        $generated .= "\n" . $indent . '<div class="box" data-aos="zoom-in" data-aos-delay="0">' . "\n";
        if (!empty($proj['github']) || !empty($proj['video'])) {
            $generated .= $indent . '    <div class="overlay">' . "\n";
            if (!empty($proj['github'])) {
                $generated .= $indent . '        <a href="' . htmlspecialchars($proj['github']) . '" target="_blank" class="btn">GitHub</a>' . "\n";
            }
            if (!empty($proj['video'])) {
                $generated .= $indent . '        <a href="#" class="btn video-btn" data-video="' . htmlspecialchars($proj['video']) . '">Смотреть видео</a>' . "\n";
            }
            $generated .= $indent . '    </div>' . "\n";
        }
        $generated .= $indent . '    <img src="' . htmlspecialchars($proj['image']) . '" alt="' . $altText . '">' . "\n";
        $generated .= $indent . '</div>';
    }

    if ($generated !== '') {
        $generated .= "\n" . $indent;
    } else {
        $generated = "\n" . $indent;
    }

    $before = substr($html, 0, $startPos + strlen($startMarker));
    $after = substr($html, $endPos);
    $newHtml = $before . $generated . $after;

    if (file_put_contents($indexPath, $newHtml) === false) {
        return 'Ошибка: не удалось записать index.html.';
    }

    return '';
}

// --- DELETE PROJECT ---
$delete_message = '';
if ($is_auth && isset($_POST['delete_project']) && verify_csrf()) {
    $delete_id = (int)$_POST['delete_project'];
    $projects = load_projects($jsonFile);
    $found = false;
    foreach ($projects as $key => $proj) {
        if ($proj['id'] === $delete_id) {
            // Delete files
            if (!empty($proj['image']) && file_exists($proj['image'])) {
                unlink($proj['image']);
            }
            if (!empty($proj['video']) && file_exists($proj['video'])) {
                unlink($proj['video']);
            }
            unset($projects[$key]);
            $found = true;
            break;
        }
    }
    if ($found) {
        $projects = array_values($projects);
        save_projects($jsonFile, $projects);
        $syncError = sync_index_html($jsonFile);
        $delete_message = "Проект удалён.";
        if ($syncError) {
            $delete_message .= " " . $syncError;
        }
    }
}

// --- UPLOAD & CREATE PROJECT ---
$message = "";

if ($is_auth && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_project'])) {
    if (!verify_csrf()) {
        $message = "Ошибка: недействительный CSRF-токен.";
    } else {
        if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
        if (!is_dir($videoDir)) mkdir($videoDir, 0755, true);

        $githubUrl = trim($_POST['github'] ?? '');
        $projectTitle = trim($_POST['title'] ?? '');
        $imagePath = "";
        $videoPath = "";
        $errors = [];

        // Validate & upload image (required)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = validate_file($_FILES['image'], $GLOBALS['allowed_image_ext'], $GLOBALS['allowed_image_mime']);
            if ($ext === false) {
                $errors[] = "Недопустимый формат изображения.";
            } else {
                $imgName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
                $targetImg = $imgDir . $imgName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetImg)) {
                    $imagePath = $targetImg;
                } else {
                    $errors[] = "Не удалось сохранить изображение.";
                }
            }
        } else {
            $errors[] = "Изображение обязательно для загрузки.";
        }

        // Validate & upload video (optional)
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $ext = validate_file($_FILES['video'], $GLOBALS['allowed_video_ext'], $GLOBALS['allowed_video_mime']);
            if ($ext === false) {
                $errors[] = "Недопустимый формат видео.";
            } else {
                $videoName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['video']['name']));
                $targetVid = $videoDir . $videoName;
                if (move_uploaded_file($_FILES['video']['tmp_name'], $targetVid)) {
                    $videoPath = $targetVid;
                } else {
                    $errors[] = "Не удалось сохранить видео.";
                }
            }
        }

        // Validate GitHub URL if provided
        if ($githubUrl !== '' && !filter_var($githubUrl, FILTER_VALIDATE_URL)) {
            $errors[] = "Некорректный GitHub URL.";
        }

        if (!empty($errors)) {
            $message = "Ошибка: " . implode(' ', $errors);
        } elseif ($imagePath) {
            // Save to JSON
            $projects = load_projects($jsonFile);
            $maxId = 0;
            foreach ($projects as $p) {
                if ($p['id'] > $maxId) $maxId = $p['id'];
            }

            $project = [
                'id' => $maxId + 1,
                'title' => $projectTitle ?: ('Project ' . ($maxId + 1)),
                'image' => $imagePath,
                'video' => $videoPath,
                'github' => $githubUrl,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $projects[] = $project;
            save_projects($jsonFile, $projects);

            $syncError = sync_index_html($jsonFile);
            $message = "Проект успешно загружен и добавлен в index.html!";
            if ($syncError) {
                $message = "Проект загружен, но: " . $syncError;
            }
        }
    }
}

// Load all projects for listing
$allProjects = load_projects($jsonFile);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center p-4 md:p-10 font-sans">

    <?php if (!$is_auth): ?>
        <!-- Login form -->
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8 mt-20">
            <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Авторизация</h2>

            <?php if ($login_error): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 text-sm rounded-lg text-center">
                    <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="do_login" value="1">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Логин</label>
                    <input type="text" name="login" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Пароль</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700 transition">Войти</button>
            </form>
        </div>

    <?php else: ?>
        <!-- Main panel -->
        <div class="w-full max-w-2xl">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Новый проект</h1>
                <a href="?logout=1" class="text-sm text-red-600 hover:underline">Выйти</a>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= strpos($message, 'успешно') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($delete_message): ?>
                <div class="mb-6 p-4 rounded-lg bg-yellow-100 text-yellow-700">
                    <?= htmlspecialchars($delete_message) ?>
                </div>
            <?php endif; ?>

            <!-- Upload form -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="upload_project" value="1">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Название проекта (необязательно)</label>
                        <input type="text" name="title" placeholder="Мой проект" class="w-full px-4 py-2 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Изображение (обязательно)</label>
                        <input type="file" name="image" accept="image/*" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Видео (необязательно)</label>
                        <input type="file" name="video" accept="video/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GitHub URL (необязательно)</label>
                        <input type="url" name="github" placeholder="https://github.com/..." class="w-full px-4 py-2 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg">Загрузить проект</button>
                </form>
            </div>

            <!-- Auto-sync note -->
            <div class="mt-6 p-3 bg-blue-50 text-blue-700 text-sm rounded-lg">
                Проекты автоматически синхронизируются с index.html.
            </div>

            <!-- Projects list -->
            <?php if (!empty($allProjects)): ?>
                <div class="mt-10">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Загруженные проекты (<?= count($allProjects) ?>)</h2>
                    <div class="space-y-4">
                        <?php foreach (array_reverse($allProjects) as $proj): ?>
                            <div class="bg-white rounded-xl shadow-md p-4 flex flex-col sm:flex-row gap-4">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($proj['image']) && file_exists($proj['image'])): ?>
                                        <img src="<?= htmlspecialchars($proj['image']) ?>" alt="<?= htmlspecialchars($proj['title']) ?>" class="w-32 h-24 object-cover rounded-lg">
                                    <?php else: ?>
                                        <div class="w-32 h-24 bg-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs">Нет фото</div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <h3 class="font-bold text-gray-800"><?= htmlspecialchars($proj['title']) ?></h3>
                                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($proj['created_at']) ?></p>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <?php if (!empty($proj['github'])): ?>
                                            <a href="<?= htmlspecialchars($proj['github']) ?>" target="_blank" class="text-xs bg-gray-100 text-blue-600 px-2 py-1 rounded hover:bg-gray-200">GitHub</a>
                                        <?php endif; ?>
                                        <?php if (!empty($proj['video'])): ?>
                                            <span class="text-xs bg-purple-50 text-purple-600 px-2 py-1 rounded">Видео</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 flex items-start">
                                    <form method="POST" onsubmit="return confirm('Удалить проект?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="delete_project" value="<?= $proj['id'] ?>">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-200 rounded hover:bg-red-50 transition">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>
