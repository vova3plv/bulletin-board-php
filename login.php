<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/core/database.php'; 

// Ініціалізація змінних для повідомлень та збереження введених даних
$errors = [];
$username_or_email_input = ''; 

// Якщо користувач вже авторизований, перенаправляємо на головну сторінку
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Обробка даних форми, якщо запит був методом POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Отримуємо та очищуємо введені дані
    $username_or_email_input = trim($_POST['username_or_email'] ?? '');
    $password_input = $_POST['password'] ?? '';

    // Валідація введених даних
    if (empty($username_or_email_input)) {
        $errors[] = "Будь ласка, введіть ім'я користувача або email.";
    }
    if (empty($password_input)) {
        $errors[] = "Будь ласка, введіть пароль.";
    }

    // Якщо початкова валідація пройшла успішно, намагаємося знайти користувача
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :uname OR email = :email_addr");
            
            $stmt->execute([
                ':uname' => $username_or_email_input,
                ':email_addr' => $username_or_email_input
            ]);
            
            $user = $stmt->fetch(); 

            if ($user) {
                if (password_verify($password_input, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = "Неправильне ім'я користувача/email або пароль.";
                }
            } else {
                $errors[] = "Неправильне ім'я користувача/email або пароль.";
            }
        } catch (PDOException $e) {
            $errors[] = "Виникла проблема зі зв'язком з базою даних. Спробуйте пізніше.";
            error_log("Login Page - PDOException: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід - Дошка оголошень</title>
    <style>
        /* Базові стилі для сторінки входу (можна винести в окремий CSS) */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; padding: 20px; background-color: #f0f2f5; color: #1c1e21; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { font-size: 24px; color: #1c1e21; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: #606770; }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #dddfe2;
            border-radius: 6px;
            box-sizing: border-box; /* Важливо для правильного розрахунку ширини */
            font-size: 16px;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            border-color: #1877f2;
            box-shadow: 0 0 0 2px #e7f3ff;
            outline: none;
        }
        .form-group input[type="submit"] {
            background-color: #1877f2;
            color: white;
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            transition: background-color 0.2s;
        }
        .form-group input[type="submit"]:hover { background-color: #166fe5; }
        .messages .error-message ul { list-style-type: none; padding: 10px; margin: 0 0 15px 0; border-radius: 6px; background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; font-size: 14px; }
        .messages .error-message ul li { margin-bottom: 5px; }
        .messages .error-message ul li:last-child { margin-bottom: 0; }
        .register-link { margin-top: 20px; font-size: 14px; }
        .register-link a { color: #1877f2; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Вхід на сайт</h2>

        <?php if (!empty($errors)): ?>
            <div class="messages">
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username_or_email">Ім'я користувача або Email:</label>
                <input type="text" id="username_or_email" name="username_or_email" required value="<?php echo htmlspecialchars($username_or_email_input); ?>">
            </div>

            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <input type="submit" value="Увійти">
            </div>
        </form>
        <div class="register-link">
            Ще не зареєстровані? <a href="register.php">Створити акаунт</a>
        </div>
    </div>
</body>
</html>