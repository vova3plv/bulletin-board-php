<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/database.php';
$base_url = '';

$ad = null;
$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_errors = [];

if ($ad_id > 0) {
    try {
        // Отримуємо дані оголошення
        $sql = "SELECT 
                    ads.*, 
                    users.username AS author_username,
                    users.email AS author_email, 
                    users.phone_number AS author_phone, 
                    categories.name AS category_name,
                    categories.slug AS category_slug
                FROM ads
                JOIN users ON ads.user_id = users.id
                JOIN categories ON ads.category_id = categories.id
                WHERE ads.id = :ad_id AND ads.status = 'active'"; 
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt->execute();
        $ad = $stmt->fetch();

        if ($ad) {
            // Якщо оголошення знайдено, збільшуємо лічильник переглядів
            $update_views_sql = "UPDATE ads SET views_count = views_count + 1 WHERE id = :ad_id";
            $update_stmt = $pdo->prepare($update_views_sql);
            $update_stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            // Оновлюємо кількість переглядів у поточному завантаженому масиві $ad для відображення
            $ad['views_count'] = (isset($ad['views_count']) ? $ad['views_count'] + 1 : 1);

            $page_title = $ad['title']; 
        } else {
            $page_errors[] = "Оголошення не знайдено, проходить модерацію або воно неактивне.";
            $page_title = "Оголошення не знайдено";
        }
    } catch (PDOException $e) {
        error_log("Error fetching ad ID {$ad_id}: " . $e->getMessage());
        $page_errors[] = "Помилка завантаження оголошення.";
        $page_title = "Помилка";
    }
} else {
    $page_errors[] = "Неправильний ID оголошення.";
    $page_title = "Помилка";
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-container">
    <?php if (!empty($page_errors)): ?>
        <div class="error-message" style="background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <ul>
                <?php foreach ($page_errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($ad): ?>
        <article class="ad-full-details" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h1 style="font-size: 28px; margin-bottom: 15px; color: #1c1e21;"><?php echo htmlspecialchars($ad['title']); ?></h1>

            <div class="ad-main-image" style="margin-bottom: 20px; text-align: center;">
                <?php
                $image_url_full = $base_url . '/images/placeholder.png'; // За замовчуванням
                if (!empty($ad['image_path'])) {
                    $server_image_path_full = __DIR__ . '/' . $ad['image_path'];
                    if (file_exists($server_image_path_full)) {
                        $image_url_full = $base_url . '/' . ltrim($ad['image_path'], '/');
                    }
                }
                ?>
                <img src="<?php echo htmlspecialchars($image_url_full); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" style="max-width: 100%; max-height:500px; border-radius: 6px; border: 1px solid #dddfe2;">
            </div>
            
            <div class="ad-meta-full" style="margin-bottom: 20px; font-size: 14px; color: #606770; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <p><strong>Категорія:</strong> <a href="<?php echo $base_url; ?>/category_view.php?slug=<?php echo htmlspecialchars($ad['category_slug']); ?>"><?php echo htmlspecialchars($ad['category_name']); ?></a></p>
                <p><strong>Автор:</strong> <?php echo htmlspecialchars($ad['author_username']); ?></p>
                <p><strong>Дата публікації:</strong> <?php echo date('d.m.Y \о H:i', strtotime($ad['created_at'])); ?></p>
                <p><strong>Переглядів:</strong> <?php echo (int)$ad['views_count']; ?></p>
                <?php if (!is_null($ad['location']) && !empty(trim($ad['location']))): ?>
                    <p><strong>Місцезнаходження:</strong> <?php echo htmlspecialchars($ad['location']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!is_null($ad['price']) && $ad['price'] > 0): ?>
                <div class="ad-price-full" style="font-size: 24px; font-weight: bold; color: #1877f2; margin-bottom: 20px;">
                    Ціна: <?php echo htmlspecialchars(number_format($ad['price'], 2, '.', ' ')) . ' ' . htmlspecialchars($ad['currency']); ?>
                </div>
            <?php else: ?>
                <div class="ad-price-full" style="font-size: 20px; font-weight: bold; color: #1c1e21; margin-bottom: 20px;">
                    Ціна: Договірна
                </div>
            <?php endif; ?>

            <div class="ad-description-full" style="font-size: 16px; line-height: 1.7; white-space: pre-wrap; word-wrap: break-word;">
                <h3 style="font-size: 20px; margin-bottom: 10px;">Опис оголошення:</h3>
                <?php echo nl2br(htmlspecialchars($ad['description'])); // nl2br для збереження переносів рядків ?>
            </div>

            <div class="ad-author-contacts" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                 <h3 style="font-size: 18px; margin-bottom: 10px;">Контакти автора:</h3>
                 <p><strong>Ім'я:</strong> <?php echo htmlspecialchars($ad['author_username']); ?></p>
                 <?php if (!empty($ad['author_phone'])): // Показуємо телефон, якщо він є ?>
                    <p><strong>Телефон:</strong> <a href="tel:<?php echo htmlspecialchars(str_replace([' ', '(', ')', '-'], '', $ad['author_phone'])); ?>"><?php echo htmlspecialchars($ad['author_phone']); ?></a></p>
                 <?php endif; ?>
                 <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($ad['author_email']); ?>"><?php echo htmlspecialchars($ad['author_email']); ?></a></p>
                 </div>


            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $ad['user_id']): ?>
                <div class="ad-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h3 style="font-size: 18px; margin-bottom: 10px;">Дії з оголошенням:</h3>
                    <a href="<?php echo $base_url; ?>/edit_ad.php?id=<?php echo $ad['id']; ?>" style="text-decoration: none; background-color: #ffc107; color: #212529; padding: 10px 15px; border-radius: 6px; margin-right: 10px;">Редагувати</a>
                    <a href="<?php echo $base_url; ?>/delete_ad.php?id=<?php echo $ad['id']; ?>" onclick="return confirm('Ви впевнені, що хочете видалити це оголошення?');" style="text-decoration: none; background-color: #dc3545; color: white; padding: 10px 15px; border-radius: 6px;">Видалити</a>
                    </div>
            <?php endif; ?>

        </article>
    <?php elseif (empty($page_errors)) : ?>
        <p>Оголошення, яке ви шукаєте, не знайдено.</p>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/layout/footer.php';
?>