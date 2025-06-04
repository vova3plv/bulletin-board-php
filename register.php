<?php
session_start();

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/core/database.php';

$errors = [];
$success_message = '';
$username = '';
$email = '';
$phone_number = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? ''; 

    // === Перевірка reCAPTCHA ===
    if (empty($recaptcha_response)) {
        $errors[] = "Будь ласка, підтвердіть, що ви не робот (пройдіть reCAPTCHA).";
    } else {
        // Формуємо URL для запиту до Google
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $post_data = http_build_query([
            'secret'   => RECAPTCHA_SECRET_KEY, 
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] 
        ]);

        // Налаштування для file_get_contents для POST-запиту
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $post_data
            ]
        ];
        $context = stream_context_create($options);
        $verify_result_json = file_get_contents($verify_url, false, $context);
        
        if ($verify_result_json === FALSE) {
            $errors[] = "Не вдалося перевірити reCAPTCHA. Проблема з сервером Google або налаштуваннями PHP.";
            error_log("reCAPTCHA: Failed to get response from Google verify API.");
        } else {
            $verify_result = json_decode($verify_result_json);

            if (!$verify_result->success) {
                $errors[] = "Перевірка reCAPTCHA не пройдена. Спробуйте ще раз.";
                if (isset($verify_result->{'error-codes'})) {
                    error_log("reCAPTCHA verification failed with error codes: " . implode(', ', $verify_result->{'error-codes'}));
                }
            }
        }
    }
    
    // === Валідація даних (виконується ТІЛЬКИ якщо reCAPTCHA пройшла успішно або не було помилок з нею) ===
    if (empty($errors)) {
        if (empty($username)) {
            $errors[] = "Ім'я користувача (логін) є обов'язковим.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Ім'я користувача повинно містити від 3 до 50 символів.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Ім'я користувача може містити лише латинські літери, цифри та знак підкреслення (_).";
        }

        if (empty($email)) {
            $errors[] = "Email є обов'язковим.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Введіть коректний Email.";
        }

        if (!empty($phone_number) && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone_number)) {
            $errors[] = "Введіть коректний номер телефону (наприклад, +380XXXXXXXXX).";
        }

        if (empty($password)) {
            $errors[] = "Пароль є обов'язковим.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Пароль повинен містити щонайменше 6 символів.";
        }

        if ($password !== $confirm_password) {
            $errors[] = "Паролі не співпадають.";
        }

        // === Перевірка на унікальність логіна та email ===
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors[] = "Це ім'я користувача вже зайняте. Будь ласка, виберіть інше.";
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "Цей email вже зареєстрований. Можливо, ви хочете <a href='login.php'>увійти</a>?";
                }
            } catch (PDOException $e) {
                $errors[] = "Помилка перевірки даних в базі. Спробуйте пізніше.";
                error_log("DB check error: " . $e->getMessage());
            }
        }
    }


    // === Якщо помилок немає (включаючи reCAPTCHA) - реєструємо користувача ===
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $sql = "INSERT INTO users (username, email, password_hash, phone_number) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $phone_to_insert = !empty($phone_number) ? $phone_number : null;
            
            if ($stmt->execute([$username, $email, $password_hash, $phone_to_insert])) {
                $success_message = "Ви успішно зареєстровані! Тепер можете <a href='login.php'>увійти</a>.";
                $username = $email = $phone_number = ''; // Очищаємо поля
            } else {
                $errors[] = "Не вдалося зареєструвати користувача. Спробуйте пізніше.";
            }
        } catch (PDOException $e) {
            $errors[] = "Помилка при збереженні даних. Спробуйте пізніше.";
            error_log("DB insert error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Реєстрація - Дошка оголошень</title>
    <style>
        /* ... ваші стилі ... (залишаються ті ж самі) */
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 500px; margin: 30px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        .form-group input[type="submit"] {
            background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; display: block; width: 100%;
        }
        .form-group input[type="submit"]:hover { background-color: #4cae4c; }
        .messages { margin-bottom: 15px; }
        .error-message ul, .success-message { list-style-type: none; padding: 10px; margin: 0; border-radius: 4px; }
        .error-message ul { border: 1px solid #d9534f; background-color: #f2dede; color: #a94442; }
        .error-message ul li { margin-bottom: 5px; }
        .success-message { border: 1px solid #5cb85c; background-color: #dff0d8; color: #3c763d; }
        .login-link { text-align: center; margin-top: 20px; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .g-recaptcha { margin-bottom: 15px; } 
    </style>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="container">
        <h2>Реєстрація нового користувача</h2>

        <div class="messages">
            <?php
            if (!empty($errors)) {
                echo '<div class="error-message"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($success_message)) {
                echo '<div class="success-message">' . $success_message . '</div>';
            }
            ?>
        </div>

        <?php if (empty($success_message)): ?>
        <form action="register.php" method="POST" novalidate>
            <div class="form-group">
                <label for="username">Ім'я користувача (логін):</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label for="phone_number">Номер телефону (необов'язково):</label>
                <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
            </div>

            <div class="form-group">
                <label for="password">Пароль (мін. 6 символів):</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Підтвердіть пароль:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>

            <div class="form-group">
                <input type="submit" value="Зареєструватися">
            </div>
        </form>
        <?php endif; ?>
        <div class="login-link">
            Вже зареєстровані? <a href="login.php">Увійти</a>
        </div>
    </div>
</body>
</html>