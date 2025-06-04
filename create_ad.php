<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/core/database.php'; 

$errors = [];
$success_message = '';

$categories = [];
try {
    $stmt_cat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_cat->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Не вдалося завантажити категорії: " . $e->getMessage();
    error_log("Error fetching categories: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $currency = trim($_POST['currency'] ?? 'UAH');
    $location = trim($_POST['location'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    
    if (empty($title)) {
        $errors[] = "Заголовок оголошення є обов'язковим.";
    } elseif (strlen($title) > 255) {
        $errors[] = "Заголовок занадто довгий (максимум 255 символів).";
    }

    if (empty($description)) {
        $errors[] = "Опис оголошення є обов'язковим.";
    }

    if (!empty($price) && !is_numeric($price)) {
        $errors[] = "Ціна повинна бути числом.";
    } elseif (!empty($price) && $price < 0) {
        $errors[] = "Ціна не може бути від'ємною.";
    }
    
    if (!$category_id) {
        $errors[] = "Будь ласка, виберіть категорію.";
    } else {
        $stmt_check_cat = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt_check_cat->execute([$category_id]);
        if (!$stmt_check_cat->fetch()) {
            $errors[] = "Обрана недійсна категорія.";
            $category_id = null; 
        }
    }

    $image_path_to_db = null; 
    if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] == UPLOAD_ERR_OK) {
        $image = $_FILES['ad_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; 

        if (!in_array($image['type'], $allowed_types)) {
            $errors[] = "Неприпустимий тип файлу. Дозволені: JPG, PNG, GIF, WEBP.";
        } elseif ($image['size'] > $max_file_size) {
            $errors[] = "Файл занадто великий. Максимальний розмір: 5MB.";
        } else {
            // Створюємо унікальне ім'я файлу та директорію для завантаження
            $upload_dir = __DIR__ . '/uploads/ads_images/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) { // Створюємо директорію, якщо її немає
                     $errors[] = "Не вдалося створити директорію для завантажень.";
                     error_log("Failed to create upload directory: " . $upload_dir);
                }
            }
            
            if (is_dir($upload_dir) && is_writable($upload_dir)) { 
                $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('ad_', true) . '.' . strtolower($file_extension);
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($image['tmp_name'], $destination)) {
                    $image_path_to_db = 'uploads/ads_images/' . $new_filename;
                } else {
                    $errors[] = "Не вдалося завантажити зображення. Помилка переміщення файлу.";
                    error_log("Failed to move uploaded file: from " . $image['tmp_name'] . " to " . $destination);
                }
            } else {
                 $errors[] = "Директорія для завантажень недоступна.";
                 error_log("Upload directory not writable or does not exist: " . $upload_dir);
            }
        }
    } elseif (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => "Розмір файлу перевищує максимально допустимий директивою upload_max_filesize в php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "Розмір файлу перевищує максимально допустимий директивою MAX_FILE_SIZE, вказаною в HTML-формі.",
            UPLOAD_ERR_PARTIAL    => "Файл було завантажено лише частково.",
            UPLOAD_ERR_NO_TMP_DIR => "Відсутня тимчасова папка для завантаження файлів.",
            UPLOAD_ERR_CANT_WRITE => "Не вдалося записати файл на диск.",
            UPLOAD_ERR_EXTENSION  => "PHP-розширення зупинило завантаження файлу."
        ];
        $error_code = $_FILES['ad_image']['error'];
        $errors[] = $upload_errors[$error_code] ?? "Невідома помилка при завантаженні файлу.";
        error_log("File upload error code: " . $error_code);
    }

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO ads (user_id, category_id, title, description, price, currency, location, image_path, status) 
                    VALUES (:user_id, :category_id, :title, :description, :price, :currency, :location, :image_path, :status)";
            $stmt = $pdo->prepare($sql);
            
            $user_id_from_session = $_SESSION['user_id'];
            $price_to_db = !empty($price) ? $price : null;
            $status_default = 'pending'; 

            $stmt->bindParam(':user_id', $user_id_from_session, PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price_to_db);
            $stmt->bindParam(':currency', $currency);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':image_path', $image_path_to_db); 
            $stmt->bindParam(':status', $status_default);

            if ($stmt->execute()) {
                $success_message = "Оголошення успішно додано!";
                 $_POST = []; 
                 $title = $description = $price = $currency = $location = $category_id = ''; 
            } else {
                $errors[] = "Не вдалося зберегти оголошення. Спробуйте пізніше.";
            }
        } catch (PDOException $e) {
            $errors[] = "Помилка бази даних при збереженні оголошення: " . $e->getMessage();
            error_log("Error saving ad: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Створити оголошення - Дошка оголошень</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 700px; margin: 30px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="file"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group input[type="submit"] {
            background-color: #007bff; color: white; padding: 10px 15px; border: none;
            border-radius: 4px; cursor: pointer; font-size: 16px; display: block; width: 100%;
        }
        .form-group input[type="submit"]:hover { background-color: #0056b3; }
        .messages { margin-bottom: 15px; }
        .error-message ul, .success-message { list-style-type: none; padding: 10px; margin: 0; border-radius: 4px; }
        .error-message ul { border: 1px solid #d9534f; background-color: #f2dede; color: #a94442; }
        .error-message ul li { margin-bottom: 5px; }
        .success-message { border: 1px solid #5cb85c; background-color: #dff0d8; color: #3c763d; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/layout/header.php';?>
    <div class="container">
        <h2>Створити нове оголошення</h2>

        <div class="messages">
            <?php
            if (!empty($errors)) {
                echo '<div class="error-message"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($success_message)) {
                echo '<div class="success-message">' . htmlspecialchars($success_message) . '</div>';
            }
            ?>
        </div>

        <form action="create_ad.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Заголовок оголошення:</label>
                <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Опис:</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="category_id">Категорія:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Виберіть категорію --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Ціна:</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="currency">Валюта:</label>
                <select id="currency" name="currency">
                    <option value="UAH" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'UAH') ? 'selected' : ((!isset($_POST['currency'])) ? 'selected' : ''); ?>>UAH</option>
                    <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                    <option value="EUR" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                </select>
            </div>

            <div class="form-group">
                <label for="location">Місцезнаходження (місто):</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="ad_image">Зображення (до 5MB, JPG/PNG/GIF/WEBP):</label>
                <input type="file" id="ad_image" name="ad_image" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>

            <div class="form-group">
                <input type="submit" value="Опублікувати оголошення">
            </div>
        </form>
    </div>
    <?php include __DIR__ . '/layout/footer.php';?>
</body>
</html>