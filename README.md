# Веб-ресурс "Дошка оголошень"

## Опис

"Дошка оголошень" – це веб-платформа, розроблена на PHP та MySQL, що надає користувачам можливість розміщувати, переглядати, редагувати та видаляти приватні оголошення різноманітної тематики. Проєкт реалізовано в рамках курсової роботи.

## Основні можливості (реалізований функціонал)

* Реєстрація нових користувачів із захистом Google reCAPTCHA v2.
* Авторизація та вихід користувачів із системи (керування сесіями).
* Створення нових оголошень авторизованими користувачами (із заголовком, описом, категорією, ціною, місцезнаходженням та завантаженням одного зображення).
* Перегляд списку всіх активних оголошень на головній сторінці з пагінацією.
* Перегляд оголошень за обраною категорією з пагінацією.
* Детальний перегляд окремого оголошення з відображенням повної інформації та лічильником переглядів.
* Сторінка профілю користувача зі списком його власних оголошень та пагінацією.
* Можливість для автора редагувати свої оголошення (змінювати текст, ціну, категорію, замінювати/видаляти зображення).
* Можливість для автора видаляти свої оголошення (з підтвердженням та видаленням файлу зображення).

## Технологічний стек

* **Серверна частина:** PHP (без використання фреймворків)
* **База даних:** MySQL
* **Взаємодія з БД:** Розширення PDO (PHP Data Objects)
* **Клієнтська частина:** HTML5, CSS3, мінімальне використання JavaScript
* **Безпека форм:** Google reCAPTCHA v2
* **Веб-сервер (приклади):** Вбудований PHP-сервер для розробки
* **Інструменти розробки:** VSCode, DataGrip

## Встановлення та запуск

Для розгортання та запуску проєкту на локальному сервері або хостингу необхідно виконати наступні кроки:

### 1. Передумови

* Встановлений веб-сервер (наприклад, Apache, Nginx) з підтримкою PHP.
* Встановлена система управління базами даних MySQL (або MariaDB).
* Встановлений PHP (рекомендована версія 7.4 або новіша) з наступними розширеннями:
    * `pdo_mysql`
    * `session`
    * `mbstring`
    * `gd` (для можливої обробки зображень, хоча в поточній версії не використовується для генерації)
* Доступ до інструменту управління базами даних (наприклад, phpMyAdmin, DataGrip, MySQL Workbench).

### 2. Налаштування бази даних

Для коректної роботи веб-ресурсу необхідно створити базу даних та відповідні таблиці. Виконайте наступні SQL-запити у вашому інструменті управління базами даних (наприклад, phpMyAdmin, DataGrip або MySQL Workbench):

```sql
-- Створення бази даних
CREATE DATABASE IF NOT EXISTS bulletin_board_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bulletin_board_db;

-- Таблиця users (Користувачі)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone_number` VARCHAR(20) NULL,
    `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця categories (Категорії оголошень)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `parent_id` INT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця ads (Оголошення)
CREATE TABLE IF NOT EXISTS `ads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(12, 2) NULL,
    `currency` VARCHAR(5) NULL DEFAULT 'UAH',
    `location` VARCHAR(255) NULL,
    `image_path` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive', 'sold', 'pending', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `views_count` INT DEFAULT 0,
    `is_urgent` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця ad_images (Додаткові зображення для оголошень)
CREATE TABLE IF NOT EXISTS `ad_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ad_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Додавання початкових категорій
INSERT INTO `categories` (`name`, `slug`) VALUES
('Нерухомість', 'neruhomist'),
('Транспорт', 'transport'),
('Електроніка', 'elektronika'),
('Робота', 'robota'),
('Послуги', 'poslugy'),
('Особисті речі', 'osobysti-rechi'),
('Дім і сад', 'dim-i-sad');
```


### 4. Конфігурація додатку

Для налаштування підключення до бази даних та інтеграції сервісу Google reCAPTCHA виконайте наступні кроки:

1.  **Створення файлу конфігурації:**
    * У кореневій директорії вашого проєкту знайдіть файл `db_config.php.example`.
    * Створіть копію цього файлу та назвіть її `db_config.php`.

2.  **Налаштування підключення до бази даних:**
    * Відкрийте створений файл `db_config.php` у текстовому редакторі.
    * Знайдіть наступні константи та вкажіть ваші актуальні дані:
        * `DB_USER`: замініть `'your_db_user'` на ваше ім'я користувача бази даних MySQL.
        * `DB_PASS`: замініть `'your_db_password'` на ваш пароль для доступу до бази даних MySQL.
    * Переконайтеся, що значення констант `DB_HOST` (зазвичай `'localhost'`) та `DB_NAME` (у вашому випадку `'bulletin_board_db'`) відповідають налаштуванням вашого середовища.

3.  **Налаштування Google reCAPTCHA v2:**
    * Перейдіть до [консолі адміністратора Google reCAPTCHA](https://www.google.com/recaptcha/admin/create) та зареєструйте ваш сайт (домен). Для локальної розробки ви можете додати `localhost` або `127.0.0.1` до списку доменів.
    * Оберіть тип reCAPTCHA "reCAPTCHA v2" (ймовірно, "I'm not a robot" Checkbox).
    * Після реєстрації сайту ви отримаєте два ключі: **Site Key (Ключ сайту)** та **Secret Key (Секретний ключ)**.
    * Поверніться до файлу `db_config.php` та вставте отримані ключі у відповідні константи:
        * `RECAPTCHA_SITE_KEY`: замініть `'YOUR_RECAPTCHA_SITE_KEY_HERE'` на ваш Ключ сайту.
        * `RECAPTCHA_SECRET_KEY`: замініть `'YOUR_RECAPTCHA_SECRET_KEY_HERE'` на ваш Секретний ключ.

4.  **Збережіть файл `db_config.php`**.

Після виконання цих кроків ваш додаток буде налаштовано для роботи з базою даних та сервісом reCAPTCHA. Пам'ятайте, що файл `db_config.php` містить конфіденційну інформацію і не повинен потрапляти до публічного репозиторію (він вже має бути доданий до `.gitignore`).
