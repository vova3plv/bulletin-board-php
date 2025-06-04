<?php

require_once __DIR__ . '/../db_config.php'; 

try {
    // Створюємо екземпляр PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
    //echo "Підключення до бази даних '" . DB_NAME . "' успішне!";
} catch (PDOException $e) {
    error_log("Помилка підключення до БД: " . $e->getMessage());
    die("Не вдалося підключитися до бази даних. Будь ласка, спробуйте пізніше.");
}



?>