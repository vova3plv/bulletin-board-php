<?php
// Налаштування та підключення
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/database.php'; 

$base_url = '';

$page_title = "Головна"; 

// Налаштування пагінації
$ads_per_page = 10; // Кількість оголошень на сторінці
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// Отримання загальної кількості активних оголошень
try {
    $total_ads_stmt = $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'");
    $total_ads = (int)$total_ads_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching total ads count on index.php: " . $e->getMessage());
    $total_ads = 0;
    // Можна додати змінну для виведення помилки користувачу в HTML
    $page_errors[] = "Не вдалося отримати кількість оголошень.";
}

$total_pages = $total_ads > 0 ? ceil($total_ads / $ads_per_page) : 1; // Якщо 0 оголошень, то 1 сторінка
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $ads_per_page;
if ($offset < 0) $offset = 0; // На випадок якщо $current_page стало 0 або менше

// Отримання оголошень для поточної сторінки
$ads = [];
if ($total_ads > 0) {
    try {
        $sql = "SELECT 
                    ads.id, 
                    ads.title, 
                    ads.description, 
                    ads.price, 
                    ads.currency, 
                    ads.image_path, 
                    ads.created_at, 
                    users.username AS author_username, 
                    categories.name AS category_name,
                    categories.slug AS category_slug
                FROM ads
                JOIN users ON ads.user_id = users.id
                JOIN categories ON ads.category_id = categories.id
                WHERE ads.status = 'active'
                ORDER BY ads.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $ads_stmt = $pdo->prepare($sql);
        $ads_stmt->bindParam(':limit', $ads_per_page, PDO::PARAM_INT);
        $ads_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $ads_stmt->execute();
        $ads = $ads_stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error fetching ads for page {$current_page} on index.php: " . $e->getMessage());
        $page_errors[] = "Не вдалося завантажити оголошення.";
    }
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-container"> <?php if (!empty($page_errors)): // Виведення помилок, якщо вони були на етапі PHP ?>
        <div style="background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <strong>Помилка:</strong>
            <ul>
                <?php foreach ($page_errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Останні оголошення</h2>

    <?php if (!empty($ads)): ?>
        <div class="ads-grid">
            <?php foreach ($ads as $ad): ?>
                <div class="ad-item">
                    <?php
                    $image_url = $base_url . '/images/placeholder.png'; 
                    if (!empty($ad['image_path'])) {
                        $server_image_path = __DIR__ . '/' . $ad['image_path']; 
                        if (file_exists($server_image_path)) {
                            $image_url = $base_url . '/' . ltrim($ad['image_path'], '/');
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                    
                    <h3>
                        <a href="<?php echo $base_url; ?>/view_ad.php?id=<?php echo $ad['id']; ?>">
                            <?php echo htmlspecialchars($ad['title']); ?>
                        </a>
                    </h3>
                    <div class="ad-meta">
                        Категорія: <a href="<?php echo $base_url; ?>/category_view.php?slug=<?php echo htmlspecialchars($ad['category_slug']); ?>"><?php echo htmlspecialchars($ad['category_name']); ?></a>
                    </div>
                    <div class="ad-meta">
                        Автор: <?php echo htmlspecialchars($ad['author_username']); ?>
                    </div>
                    <div class="ad-meta">
                        Дата: <?php echo date('d.m.Y H:i', strtotime($ad['created_at'])); ?>
                    </div>
                    <?php if (!is_null($ad['price']) && $ad['price'] > 0): ?>
                        <div class="ad-price">
                            <?php echo htmlspecialchars(number_format($ad['price'], 2, '.', ' ')) . ' ' . htmlspecialchars($ad['currency']); ?>
                        </div>
                    <?php else: ?>
                        <div class="ad-price">Договірна</div>
                    <?php endif; ?>
                    <div class="ad-description-short">
                        <?php echo nl2br(htmlspecialchars(mb_substr(strip_tags($ad['description']), 0, 100))); ?>
                        <?php if (mb_strlen(strip_tags($ad['description'])) > 100) echo '...'; ?>
                    </div>
                     <a href="<?php echo $base_url; ?>/view_ad.php?id=<?php echo $ad['id']; ?>" style="font-size:14px; color:#1877f2; text-decoration:none;">Читати далі &rarr;</a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>">« Попередня</a>
                <?php else: ?>
                    <span class="disabled">« Попередня</span>
                <?php endif; ?>

                <?php
                $num_links = 2;
                $start = max(1, $current_page - $num_links);
                $end = min($total_pages, $current_page + $num_links);

                if ($start > 1) {
                    echo '<a href="?page=1">1</a>';
                    if ($start > 2) {
                        echo '<span>...</span>';
                    }
                }

                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>">Наступна »</a>
                <?php else: ?>
                    <span class="disabled">Наступна »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($total_ads === 0 && empty($page_errors)): ?>
        <div class="no-ads">
            <p>Наразі немає активних оголошень.</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <p><a href="<?php echo $base_url; ?>/create_ad.php">Будьте першим, хто додасть оголошення!</a></p>
            <?php else: ?>
                <p><a href="<?php echo $base_url; ?>/login.php">Увійдіть</a> або <a href="<?php echo $base_url; ?>/register.php">зареєструйтесь</a>, щоб додати оголошення.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div> <?php
include __DIR__ . '/layout/footer.php';
?>