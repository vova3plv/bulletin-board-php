<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include __DIR__ . '/layout/header.php';

require_once __DIR__ . '/core/database.php';
$base_url = ''; 
$page_title = "Мій профіль - Мої оголошення";

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . '/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$user_ads = [];
$page_errors = [];

// Налаштування пагінації для оголошень користувача
$ads_per_page = 5; 
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// Отримання загальної кількості оголошень поточного користувача
try {
    $total_ads_stmt = $pdo->prepare("SELECT COUNT(*) FROM ads WHERE user_id = :user_id");
    $total_ads_stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $total_ads_stmt->execute();
    $total_ads = (int)$total_ads_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching total ads count for user ID {$current_user_id} on profile.php: " . $e->getMessage());
    $total_ads = 0;
    $page_errors[] = "Не вдалося отримати кількість ваших оголошень.";
}

$total_pages = $total_ads > 0 ? ceil($total_ads / $ads_per_page) : 1;
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $ads_per_page;
if ($offset < 0) $offset = 0;

// Отримання оголошень поточного користувача для поточної сторінки
if ($total_ads > 0) {
    try {
        $sql = "SELECT 
                    ads.id, 
                    ads.title, 
                    ads.status, 
                    ads.created_at,
                    ads.price,
                    ads.currency,
                    ads.views_count,
                    categories.name as category_name
                FROM ads
                LEFT JOIN categories ON ads.category_id = categories.id
                WHERE ads.user_id = :user_id
                ORDER BY ads.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $ads_stmt = $pdo->prepare($sql);
        $ads_stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
        $ads_stmt->bindParam(':limit', $ads_per_page, PDO::PARAM_INT);
        $ads_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $ads_stmt->execute();
        $user_ads = $ads_stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error fetching ads for user ID {$current_user_id}, page {$current_page} on profile.php: " . $e->getMessage());
        $page_errors[] = "Не вдалося завантажити ваші оголошення.";
    }
}

?>

<div class="page-container">
    <h2>Мої оголошення</h2>

    <?php if (!empty($page_errors)): ?>
        <div class="error-message" style="background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <ul> <?php foreach ($page_errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>'; ?> </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message" style="background-color: #dff0d8; border: 1px solid #3c763d; color: #3c763d; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
     <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message" style="background-color: #ffebe8; border: 1px solid #dd3c10; color: #dd3c10; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>


    <?php if (!empty($user_ads)): ?>
        <table class="ads-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f0f2f5; border-bottom: 2px solid #dddfe2;">
                    <th style="padding: 10px; text-align: left;">Заголовок</th>
                    <th style="padding: 10px; text-align: left;">Категорія</th>
                    <th style="padding: 10px; text-align: left;">Статус</th>
                    <th style="padding: 10px; text-align: left;">Дата</th>
                    <th style="padding: 10px; text-align: right;">Перегляди</th>
                    <th style="padding: 10px; text-align: center;">Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_ads as $ad): ?>
                    <tr style="border-bottom: 1px solid #e9ebee;">
                        <td style="padding: 10px;"><?php echo htmlspecialchars($ad['title']); ?></td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($ad['category_name'] ?? 'N/A'); ?></td>
                        <td style="padding: 10px;">
                            <?php 
                                // Можна додати стилізацію для різних статусів
                                $status_display = [
                                    'active' => 'Активне',
                                    'pending' => 'На розгляді',
                                    'inactive' => 'Неактивне',
                                    'sold' => 'Продано',
                                    'rejected' => 'Відхилено'
                                ];
                                echo htmlspecialchars($status_display[$ad['status']] ?? $ad['status']); 
                            ?>
                        </td>
                        <td style="padding: 10px;"><?php echo date('d.m.Y H:i', strtotime($ad['created_at'])); ?></td>
                        <td style="padding: 10px; text-align: right;"><?php echo (int)$ad['views_count']; ?></td>
                        <td style="padding: 10px; text-align: center; white-space: nowrap;">
                            <a href="<?php echo $base_url; ?>/view_ad.php?id=<?php echo $ad['id']; ?>" title="Переглянути" style="margin-right: 5px; color: #1877f2; text-decoration:none;">👁️</a>
                            <a href="<?php echo $base_url; ?>/edit_ad.php?id=<?php echo $ad['id']; ?>" title="Редагувати" style="margin-right: 5px; color: #ffc107; text-decoration:none;">✏️</a>
                            <a href="<?php echo $base_url; ?>/delete_ad.php?id=<?php echo $ad['id']; ?>" title="Видалити" onclick="return confirm('Ви впевнені, що хочете видалити це оголошення?');" style="color: #dc3545; text-decoration:none;">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top:20px;">
                 <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>">« Попередня</a>
                <?php else: ?>
                    <span class="disabled">« Попередня</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>">Наступна »</a>
                <?php else: ?>
                    <span class="disabled">Наступна »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($total_ads === 0 && empty($page_errors)): ?>
        <div class="no-ads" style="text-align:center; padding: 30px; background-color: #fff; border-radius: 8px; margin-top:20px;">
            <p>Ви ще не додали жодного оголошення.</p>
            <p><a href="<?php echo $base_url; ?>/create_ad.php" class="button-link" style="display: inline-block; background-color: #42b72a; color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-weight: bold;">Створити перше оголошення</a></p>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/layout/footer.php';
?>