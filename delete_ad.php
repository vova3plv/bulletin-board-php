<?php
// 1. Налаштування, запуск сесії та підключення до БД
ini_set('display_errors', 1); // Для налагодження
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/database.php'; // Підключення до БД ($pdo)
$base_url = ''; // Адаптуйте, якщо потрібно

// 2. Перевірка, чи користувач авторизований
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Будь ласка, увійдіть, щоб видаляти оголошення.";
    header('Location: ' . $base_url . '/login.php');
    exit;
}
$current_user_id = $_SESSION['user_id'];

// 3. Отримання ID оголошення з GET-запиту
$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ad_id <= 0) {
    $_SESSION['error_message'] = "Неправильний ID оголошення для видалення.";
    header('Location: ' . $base_url . '/profile.php'); // Або index.php
    exit;
}

try {
    // 4. Отримуємо інформацію про оголошення, зокрема user_id (для перевірки авторства) та image_path
    $stmt_ad_info = $pdo->prepare("SELECT user_id, image_path FROM ads WHERE id = :ad_id");
    $stmt_ad_info->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
    $stmt_ad_info->execute();
    $ad_info = $stmt_ad_info->fetch();

    if (!$ad_info) {
        $_SESSION['error_message'] = "Оголошення з ID {$ad_id} не знайдено.";
        header('Location: ' . $base_url . '/profile.php');
        exit;
    }

    // 5. Перевірка, чи поточний користувач є автором оголошення
    if ($ad_info['user_id'] != $current_user_id) {
        $_SESSION['error_message'] = "Ви не можете видалити це оголошення, оскільки не є його автором.";
        header('Location: ' . $base_url . '/profile.php'); // Або index.php
        exit;
    }

    // 6. Якщо всі перевірки пройшли, видаляємо оголошення з БД
    $stmt_delete = $pdo->prepare("DELETE FROM ads WHERE id = :ad_id AND user_id = :user_id");
    $stmt_delete->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
    $stmt_delete->bindParam(':user_id', $current_user_id, PDO::PARAM_INT); // Додаткова перевірка user_id

    if ($stmt_delete->execute()) {
        // 7. Якщо оголошення успішно видалено з БД, видаляємо файл зображення (якщо він є)
        if (!empty($ad_info['image_path'])) {
            $image_file_on_server = __DIR__ . '/' . $ad_info['image_path'];
            if (file_exists($image_file_on_server)) {
                if (!@unlink($image_file_on_server)) { // @, щоб приглушити помилку, якщо файл вже видалено або немає прав
                    error_log("Failed to delete image file: {$image_file_on_server} for ad ID: {$ad_id}");
                    // Можна додати повідомлення для користувача, якщо видалення файлу критичне,
                    // але зазвичай оголошення з БД важливіше.
                }
            }
        }
        $_SESSION['success_message'] = "Оголошення успішно видалено!";
    } else {
        $_SESSION['error_message'] = "Не вдалося видалити оголошення з бази даних.";
    }

} catch (PDOException $e) {
    error_log("Error deleting ad ID {$ad_id}: " . $e->getMessage());
    $_SESSION['error_message'] = "Помилка бази даних під час видалення оголошення: " . $e->getMessage();
}

// 8. Перенаправляємо користувача на сторінку профілю
header('Location: ' . $base_url . '/profile.php');
exit;
?>