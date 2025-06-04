<?php
// 1. Налаштування, запуск сесії та підключення до БД
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

require_once __DIR__ . '/core/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Будь ласка, увійдіть, щоб редагувати оголошення.";
    header('Location: ' . $base_url . '/login.php');
    exit;
}
$current_user_id = $_SESSION['user_id'];

$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ad = null; 
$errors = []; 
$page_title_dynamic = "Редагування оголошення"; 

if ($ad_id <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Неправильний ID оголошення для редагування.";
    header('Location: ' . $base_url . '/profile.php'); 
    exit;
}

if ($ad_id > 0) {
    try {
        $stmt_ad_check = $pdo->prepare("SELECT * FROM ads WHERE id = :ad_id");
        $stmt_ad_check->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt_ad_check->execute();
        $ad = $stmt_ad_check->fetch();

        if ($ad) {
            if ($ad['user_id'] != $current_user_id) {
                $_SESSION['error_message'] = "Ви не можете редагувати це оголошення.";
                header('Location: ' . $base_url . '/index.php');
                exit;
            }
            $page_title_dynamic = "Редагування: " . htmlspecialchars($ad['title']);
        } else {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                 $_SESSION['error_message'] = "Оголошення з ID {$ad_id} не знайдено.";
                 header('Location: ' . $base_url . '/profile.php');
                 exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching ad ID {$ad_id} for editing (pre-POST or GET): " . $e->getMessage());
        $errors[] = "Помилка завантаження даних оголошення.";
        $ad = null; 
    }
}


// Обробка відправленої POST-форми (якщо це POST-запит)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!$ad) { 
        $errors[] = "Не вдалося завантажити дані оголошення для оновлення. Можливо, ID невірний.";
    } else { // $ad завантажено, можна обробляти POST
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $currency = trim($_POST['currency'] ?? 'UAH');
        $location = trim($_POST['location'] ?? '');
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $delete_image = isset($_POST['delete_image']) ? true : false;

        // Валідація даних
        if (empty($title)) $errors[] = "Заголовок оголошення є обов'язковим.";
        if (strlen($title) > 255) $errors[] = "Заголовок занадто довгий.";
        if (empty($description)) $errors[] = "Опис оголошення є обов'язковим.";
        if (!$category_id) $errors[] = "Будь ласка, виберіть категорію.";
        if (!empty($price) && !is_numeric($price)) $errors[] = "Ціна повинна бути числом.";
        elseif (!empty($price) && $price < 0) $errors[] = "Ціна не може бути від'ємною.";

        $new_image_path = $ad['image_path']; // Поточний шлях до зображення

        if (empty($errors)) { // Тільки якщо немає помилок валідації полів
            // Обробка завантаження нового зображення
            if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] == UPLOAD_ERR_OK) {
                $image_file = $_FILES['ad_image'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_file_size = 5 * 1024 * 1024; // 5 MB

                if (!in_array($image_file['type'], $allowed_types)) {
                    $errors[] = "Неприпустимий тип файлу для нового зображення.";
                } elseif ($image_file['size'] > $max_file_size) {
                    $errors[] = "Новий файл занадто великий (макс. 5MB).";
                } else {
                    $upload_dir = __DIR__ . '/uploads/ads_images/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0775, true)) {
                             $errors[] = "Не вдалося створити директорію для завантажень.";
                        }
                    }
                    
                    if (is_dir($upload_dir) && is_writable($upload_dir) && empty($errors)) {
                        $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
                        $new_filename = uniqid('ad_', true) . '.' . $file_extension;
                        $destination = $upload_dir . $new_filename;

                        if (move_uploaded_file($image_file['tmp_name'], $destination)) {
                            if (!empty($ad['image_path']) && file_exists(__DIR__ . '/' . $ad['image_path'])) {
                                @unlink(__DIR__ . '/' . $ad['image_path']); // @ щоб приглушити помилку, якщо файл вже видалено
                            }
                            $new_image_path = 'uploads/ads_images/' . $new_filename;
                        } else {
                            $errors[] = "Не вдалося перемістити завантажене зображення.";
                        }
                    } elseif(empty($errors)) { // Якщо помилок ще не було
                        $errors[] = "Директорія для завантажень недоступна для запису.";
                    }
                }
            } elseif ($delete_image && !empty($ad['image_path'])) {
                if (file_exists(__DIR__ . '/' . $ad['image_path'])) {
                    @unlink(__DIR__ . '/' . $ad['image_path']);
                }
                $new_image_path = null;
            } elseif (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['ad_image']['error'] != UPLOAD_ERR_OK) {
                $errors[] = "Помилка при завантаженні нового зображення (код: " . $_FILES['ad_image']['error'] . ").";
            }
        } 
        if (empty($errors)) {
            try {
                $sql_update = "UPDATE ads SET 
                                title = :title, description = :description, price = :price, 
                                currency = :currency, location = :location, category_id = :category_id,
                                image_path = :image_path, status = :status 
                             WHERE id = :ad_id AND user_id = :user_id";
                
                $stmt_update = $pdo->prepare($sql_update);
                
                $price_to_db = !empty($price) ? (float)$price : null;
                $current_status = 'pending'; // після редагування завжди на модерацію

                $stmt_update->bindParam(':title', $title);
                $stmt_update->bindParam(':description', $description);
                $stmt_update->bindParam(':price', $price_to_db);
                $stmt_update->bindParam(':currency', $currency);
                $stmt_update->bindParam(':location', $location);
                $stmt_update->bindParam(':category_id', $category_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':image_path', $new_image_path); // Може бути null
                $stmt_update->bindParam(':status', $current_status);
                $stmt_update->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    $_SESSION['success_message'] = "Оголошення успішно оновлено та відправлено на розгляд!";
                    header('Location: ' . $base_url . '/view_ad.php?id=' . $ad_id);
                    exit; // ВАЖЛИВО: exit після header()
                } else {
                    $errors[] = "Не вдалося оновити оголошення в базі даних.";
                }
            } catch (PDOException $e) {
                error_log("Error updating ad ID {$ad_id} in DB: " . $e->getMessage());
                $errors[] = "Помилка бази даних при оновленні: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $ad['title'] = $title; // Перезаписуємо значення в $ad для відображення у формі
            $ad['description'] = $description;
            $ad['price'] = $price;
            $ad['currency'] = $currency;
            $ad['location'] = $location;
            $ad['category_id'] = $category_id;
        }
    } 
} 


// Завантажуємо категорії для випадаючого списку (якщо ще не завантажені або для GET запиту)
if (empty($categories) && empty($errors)) { 
    try {
        $stmt_cat_form = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
        $categories = $stmt_cat_form->fetchAll();
    } catch (PDOException $e) {
        $errors[] = "Не вдалося завантажити категорії для форми: " . $e->getMessage();
        error_log("Error fetching categories for edit_ad.php form: " . $e->getMessage());
    }
}

// Встановлюємо заголовок сторінки для хедера
$page_title = $page_title_dynamic;

// підключаємо хедер
include __DIR__ . '/layout/header.php';
?>

<div class="page-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="error-message" style="background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <strong>Будь ласка, виправте наступні помилки:</strong>
            <ul> <?php foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>'; ?> </ul>
        </div>
    <?php endif; ?>

    <?php if ($ad): // Показуємо форму тільки якщо оголошення $ad успішно завантажено ?>
        <form action="edit_ad.php?id=<?php echo $ad_id; ?>" method="POST" enctype="multipart/form-data" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="form-group">
                <label for="title">Заголовок оголошення:</label>
                <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($ad['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Опис:</label>
                <textarea id="description" name="description" required rows="6"><?php echo htmlspecialchars($ad['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="category_id">Категорія:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Виберіть категорію --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category_item): ?>
                            <option value="<?php echo $category_item['id']; ?>" <?php echo (($ad['category_id'] ?? '') == $category_item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category_item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Ціна:</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($ad['price'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="currency">Валюта:</label>
                <select id="currency" name="currency">
                    <option value="UAH" <?php echo (($ad['currency'] ?? 'UAH') == 'UAH') ? 'selected' : ''; ?>>UAH</option>
                    <option value="USD" <?php echo (($ad['currency'] ?? '') == 'USD') ? 'selected' : ''; ?>>USD</option>
                    <option value="EUR" <?php echo (($ad['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                </select>
            </div>

            <div class="form-group">
                <label for="location">Місцезнаходження (місто):</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($ad['location'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="ad_image">Змінити зображення (до 5MB, JPG/PNG/GIF/WEBP):</label>
                <input type="file" id="ad_image" name="ad_image" accept="image/jpeg,image/png,image/gif,image/webp">
                <?php if (!empty($ad['image_path']) && file_exists(__DIR__ . '/' . $ad['image_path'])): ?>
                    <div style="margin-top: 10px;">
                        <p>Поточне зображення:</p>
                        <img src="<?php echo $base_url . '/' . htmlspecialchars($ad['image_path']); ?>" alt="Поточне зображення" style="max-width: 200px; max-height: 200px; border-radius: 4px; margin-bottom: 5px;">
                        <br>
                        <label><input type="checkbox" name="delete_image" value="1"> Видалити поточне зображення</label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 25px;">
                <input type="submit" value="Зберегти зміни">
                <a href="<?php echo $base_url; ?>/view_ad.php?id=<?php echo $ad_id; ?>" style="display: inline-block; margin-left: 10px; color: #606770; text-decoration: none;">Скасувати</a>
            </div>
        </form>
    <?php elseif (empty($errors)) : ?>
        <p>Не вдалося завантажити дані оголошення для редагування. Можливо, оголошення не існує або у вас немає прав доступу.</p>
        <p><a href="<?php echo $base_url; ?>/profile.php">Повернутися до моїх оголошень</a></p>
    <?php endif; ?>
</div>

<?php
// Підключаємо футер
include __DIR__ . '/layout/footer.php';
?>