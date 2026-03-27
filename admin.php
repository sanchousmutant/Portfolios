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
        $descText = htmlspecialchars($proj['description'] ?? '');
        $generated .= "\n" . $indent . '<div class="box" data-aos="zoom-in" data-aos-delay="0">' . "\n";
        $generated .= $indent . '    <div class="overlay">' . "\n";
        if (!empty($proj['title'])) {
            $generated .= $indent . '        <h4 class="project-title">' . $altText . '</h4>' . "\n";
        }
        if (!empty($proj['description'])) {
            $generated .= $indent . '        <p class="project-desc">' . $descText . '</p>' . "\n";
        }
        if (!empty($proj['github'])) {
            $generated .= $indent . '        <a href="' . htmlspecialchars($proj['github']) . '" target="_blank" class="btn">GitHub</a>' . "\n";
        }
        if (!empty($proj['video'])) {
            $generated .= $indent . '        <a href="#" class="btn video-btn" data-video="' . htmlspecialchars($proj['video']) . '">Смотреть видео</a>' . "\n";
        }
        $generated .= $indent . '    </div>' . "\n";
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

// --- EDIT PROJECT ---
$edit_message = '';
if ($is_auth && isset($_POST['edit_project']) && verify_csrf()) {
    $edit_id = (int)$_POST['edit_project'];
    $projects = load_projects($jsonFile);
    foreach ($projects as &$proj) {
        if ($proj['id'] === $edit_id) {
            $proj['title'] = trim($_POST['edit_title'] ?? '') ?: $proj['title'];
            $proj['description'] = trim($_POST['edit_description'] ?? '');
            $proj['github'] = trim($_POST['edit_github'] ?? '');

            // Validate GitHub URL if provided
            if ($proj['github'] !== '' && !filter_var($proj['github'], FILTER_VALIDATE_URL)) {
                $edit_message = "Некорректный GitHub URL.";
                break;
            }

            // Replace image if new one uploaded
            if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
                $ext = validate_file($_FILES['edit_image'], $GLOBALS['allowed_image_ext'], $GLOBALS['allowed_image_mime']);
                if ($ext !== false) {
                    if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                    $imgName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['edit_image']['name']));
                    $targetImg = $imgDir . $imgName;
                    if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $targetImg)) {
                        if (!empty($proj['image']) && file_exists($proj['image'])) {
                            unlink($proj['image']);
                        }
                        $proj['image'] = $targetImg;
                    }
                } else {
                    $edit_message = "Недопустимый формат изображения.";
                    break;
                }
            }

            // Replace video if new one uploaded
            if (isset($_FILES['edit_video']) && $_FILES['edit_video']['error'] === UPLOAD_ERR_OK) {
                $ext = validate_file($_FILES['edit_video'], $GLOBALS['allowed_video_ext'], $GLOBALS['allowed_video_mime']);
                if ($ext !== false) {
                    if (!is_dir($videoDir)) mkdir($videoDir, 0755, true);
                    $videoName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['edit_video']['name']));
                    $targetVid = $videoDir . $videoName;
                    if (move_uploaded_file($_FILES['edit_video']['tmp_name'], $targetVid)) {
                        if (!empty($proj['video']) && file_exists($proj['video'])) {
                            unlink($proj['video']);
                        }
                        $proj['video'] = $targetVid;
                    }
                } else {
                    $edit_message = "Недопустимый формат видео.";
                    break;
                }
            }

            // Remove video if requested
            if (!empty($_POST['remove_video']) && !empty($proj['video'])) {
                if (file_exists($proj['video'])) {
                    unlink($proj['video']);
                }
                $proj['video'] = '';
            }

            save_projects($jsonFile, $projects);
            $syncError = sync_index_html($jsonFile);
            $edit_message = "Проект обновлён.";
            if ($syncError) {
                $edit_message .= " " . $syncError;
            }
            break;
        }
    }
    unset($proj);
}

// --- IMPORT HTML CARDS & SYNC ---
function import_html_cards(string $jsonFile): array {
    $indexPath = __DIR__ . '/index.html';
    if (!file_exists($indexPath)) {
        return ['imported' => 0, 'error' => 'index.html не найден.'];
    }

    $html = file_get_contents($indexPath);
    $startMarker = '<!-- ADMIN-PROJECTS-START -->';
    $endMarker = '<!-- ADMIN-PROJECTS-END -->';
    $startPos = strpos($html, $startMarker);
    $endPos = strpos($html, $endMarker);

    if ($startPos === false || $endPos === false) {
        return ['imported' => 0, 'error' => 'Маркеры не найдены.'];
    }

    $block = substr($html, $startPos + strlen($startMarker), $endPos - $startPos - strlen($startMarker));

    // Parse all .box divs with regex
    $projects = load_projects($jsonFile);

    // Collect existing images to avoid duplicate imports
    $existingImages = [];
    foreach ($projects as $p) {
        if (!empty($p['image'])) {
            $existingImages[] = $p['image'];
        }
    }

    $maxId = 0;
    foreach ($projects as $p) {
        if ($p['id'] > $maxId) $maxId = $p['id'];
    }

    $imported = 0;

    // Match each <div class="box" ...> ... </div> at top level
    // Using a simple approach: find each <img src="..." alt="..."> inside the block
    if (preg_match_all('/<div\s+class="box"[^>]*>(.*?)<\/div>\s*<\/div>/s', $block, $boxMatches)) {
        // This won't work well with nested divs. Use DOMDocument instead.
    }

    // Use DOMDocument + XPath for reliable parsing of nested divs
    $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $block . '</body></html>';
    $dom = new DOMDocument();
    @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);

    $boxes = $xpath->query('//div[contains(@class, "box") and not(contains(@class, "box-container")) and not(contains(@class, "modal"))]');

    foreach ($boxes as $div) {
        $imgs = $div->getElementsByTagName('img');
        if ($imgs->length === 0) continue;
        $imgSrc = $imgs->item(0)->getAttribute('src');
        $imgAlt = $imgs->item(0)->getAttribute('alt');

        // Already in JSON?
        if (in_array($imgSrc, $existingImages, true)) {
            continue;
        }

        // Find links and video buttons in overlay
        $github = '';
        $video = '';
        $title = $imgAlt;
        $description = '';
        $links = $div->getElementsByTagName('a');
        foreach ($links as $a) {
            $href = $a->getAttribute('href');
            $cls = $a->getAttribute('class') ?? '';
            if (strpos($cls, 'video-btn') !== false) {
                $video = $a->getAttribute('data-video');
            } elseif ($href && $href !== '#') {
                $github = $href;
            }
        }

        // Find project-title / project-desc
        $h4s = $div->getElementsByTagName('h4');
        foreach ($h4s as $h4) {
            if (strpos($h4->getAttribute('class') ?? '', 'project-title') !== false) {
                $title = trim($h4->textContent);
            }
        }
        $ps = $div->getElementsByTagName('p');
        foreach ($ps as $p) {
            if (strpos($p->getAttribute('class') ?? '', 'project-desc') !== false) {
                $description = trim($p->textContent);
            }
        }

        $maxId++;
        $projects[] = [
            'id' => $maxId,
            'title' => $title ?: ('Project ' . $maxId),
            'description' => $description,
            'image' => $imgSrc,
            'video' => $video,
            'github' => trim($github),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $existingImages[] = $imgSrc;
        $imported++;
    }

    save_projects($jsonFile, $projects);
    return ['imported' => $imported, 'error' => ''];
}

// --- FORCE SYNC ---
$sync_message = '';
if ($is_auth && isset($_POST['force_sync']) && verify_csrf()) {
    // First import any hand-coded cards from HTML into JSON
    $importResult = import_html_cards($jsonFile);
    // Then regenerate HTML from JSON
    $syncError = sync_index_html($jsonFile);

    $parts = [];
    if ($importResult['imported'] > 0) {
        $parts[] = "Импортировано карточек: " . $importResult['imported'];
    }
    if ($importResult['error']) {
        $parts[] = $importResult['error'];
    }
    if ($syncError) {
        $parts[] = $syncError;
    }
    $parts[] = "index.html синхронизирован.";
    $sync_message = implode(' ', $parts);
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
        $projectDescription = trim($_POST['description'] ?? '');
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
                'description' => $projectDescription,
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

            <?php if ($edit_message): ?>
                <div class="mb-6 p-4 rounded-lg <?= strpos($edit_message, 'обновлён') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($edit_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($sync_message): ?>
                <div class="mb-6 p-4 rounded-lg <?= strpos($sync_message, 'синхронизирован') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($sync_message) ?>
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Описание (необязательно)</label>
                        <textarea name="description" rows="3" placeholder="Краткое описание проекта..." class="w-full px-4 py-2 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
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

            <!-- Sync button -->
            <div class="mt-6">
                <form method="POST" class="flex items-center gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="force_sync" value="1">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-lg hover:bg-green-700 transition shadow">Синхронизировать все проекты</button>
                    <span class="text-xs text-gray-400">Обновит index.html из projects.json</span>
                </form>
            </div>

            <!-- Projects list -->
            <?php if (!empty($allProjects)): ?>
                <div class="mt-10">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Загруженные проекты (<?= count($allProjects) ?>)</h2>
                    <div class="space-y-4">
                        <?php foreach (array_reverse($allProjects) as $proj): ?>
                            <div class="bg-white rounded-xl shadow-md p-4" id="project-<?= $proj['id'] ?>">
                                <!-- View mode -->
                                <div class="flex flex-col sm:flex-row gap-4" id="view-<?= $proj['id'] ?>">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($proj['image']) && file_exists($proj['image'])): ?>
                                            <img src="<?= htmlspecialchars($proj['image']) ?>" alt="<?= htmlspecialchars($proj['title']) ?>" class="w-32 h-24 object-cover rounded-lg">
                                        <?php else: ?>
                                            <div class="w-32 h-24 bg-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs">Нет фото</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow">
                                        <h3 class="font-bold text-gray-800"><?= htmlspecialchars($proj['title']) ?></h3>
                                        <?php if (!empty($proj['description'])): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($proj['description']) ?></p>
                                        <?php endif; ?>
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
                                    <div class="flex-shrink-0 flex items-start gap-2">
                                        <button onclick="toggleEdit(<?= $proj['id'] ?>)" class="text-xs text-blue-500 hover:text-blue-700 px-2 py-1 border border-blue-200 rounded hover:bg-blue-50 transition">Редактировать</button>
                                        <form method="POST" onsubmit="return confirm('Удалить проект?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="delete_project" value="<?= $proj['id'] ?>">
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-200 rounded hover:bg-red-50 transition">Удалить</button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Edit mode (hidden by default) -->
                                <div class="hidden mt-4" id="edit-<?= $proj['id'] ?>">
                                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="edit_project" value="<?= $proj['id'] ?>">

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Название</label>
                                            <input type="text" name="edit_title" value="<?= htmlspecialchars($proj['title']) ?>" class="w-full px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Описание</label>
                                            <textarea name="edit_description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-y"><?= htmlspecialchars($proj['description'] ?? '') ?></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">GitHub URL</label>
                                            <input type="url" name="edit_github" value="<?= htmlspecialchars($proj['github'] ?? '') ?>" placeholder="https://github.com/..." class="w-full px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Заменить изображение</label>
                                            <input type="file" name="edit_image" accept="image/*" class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Заменить видео</label>
                                            <input type="file" name="edit_video" accept="video/*" class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                            <?php if (!empty($proj['video'])): ?>
                                                <label class="inline-flex items-center gap-1 mt-1 text-xs text-gray-500">
                                                    <input type="checkbox" name="remove_video" value="1"> Удалить текущее видео
                                                </label>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex gap-2">
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">Сохранить</button>
                                            <button type="button" onclick="toggleEdit(<?= $proj['id'] ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition">Отмена</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
    function toggleEdit(id) {
        const view = document.getElementById('view-' + id);
        const edit = document.getElementById('edit-' + id);
        view.classList.toggle('hidden');
        edit.classList.toggle('hidden');
    }
    </script>

</body>
</html>
