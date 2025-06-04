<?php
if (session_status() == PHP_SESSION_NONE) { 
    session_start();
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Дошка оголошень</title>
    <link rel="stylesheet" href="<?php echo isset($base_url) ? $base_url : ''; ?>/css/style.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f0f2f5; color: #1c1e21; display: flex; flex-direction: column; min-height: 100vh; }
        .site-header { background-color: #fff; padding: 15px 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-bottom: 1px solid #dddfe2; }
        .header-container { max-width: 1000px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .site-header h1 { margin: 0; font-size: 24px; }
        .site-header h1 a { text-decoration: none; color: #1877f2; font-weight: bold; }
        .site-header nav ul { list-style-type: none; margin: 0; padding: 0; display: flex; align-items: center; }
        .site-header nav ul li { margin-left: 20px; }
        .site-header nav ul li a { text-decoration: none; color: #050505; font-weight: 600; font-size: 15px; }
        .site-header nav ul li a:hover { text-decoration: underline; }
        .site-header nav ul li .welcome-user { color: #606770; font-size: 15px; }
        .site-header nav ul li .button-link { background-color: #42b72a; color: white; padding: 8px 12px; border-radius: 6px; font-weight: bold; }
        .site-header nav ul li .button-link:hover { background-color: #36a420; text-decoration: none; }
        .main-content { flex-grow: 1; width: 100%; } 
        .page-container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }

        .ads-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
        .ad-item { background-color: #fff; border: 1px solid #dddfe2; border-radius: 8px; padding: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .ad-item img { max-width: 100%; height: 180px; object-fit: cover; border-radius: 6px; margin-bottom: 10px; display: block; background-color: #eee; }
        .ad-item h3 { font-size: 18px; margin-top: 0; margin-bottom: 8px; }
        .ad-item h3 a { text-decoration: none; color: #050505; }
        .ad-item h3 a:hover { text-decoration: underline; }
        .ad-meta { font-size: 13px; color: #606770; margin-bottom: 5px; }
        .ad-price { font-size: 16px; font-weight: bold; color: #1c1e21; margin-bottom: 10px; }
        .ad-description-short { font-size: 14px; color: #050505; margin-bottom: 10px; height: 4.2em; overflow: hidden; text-overflow: ellipsis; }
        .pagination { text-align: center; margin-top: 30px; margin-bottom: 20px; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 3px; border: 1px solid #dddfe2; border-radius: 4px; text-decoration: none; color: #1877f2; background-color: #fff; }
        .pagination a:hover { background-color: #e7f3ff; }
        .pagination .current-page { background-color: #1877f2; color: white; border-color: #1877f2; }
        .pagination .disabled { color: #ced0d4; background-color: #f0f2f5; pointer-events: none; }
        .no-ads { text-align:center; padding: 50px; background-color: #fff; border-radius: 8px; margin-top:20px;}
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <h1><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/index.php">Дошка Оголошень</a></h1>
            <nav>
                <ul>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="welcome-user">Привіт, <?php echo htmlspecialchars($_SESSION['username']); ?>!</li>
                        <li><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/profile.php">Мій профіль</a></li>
                        <li><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/create_ad.php" class="button-link">Додати оголошення</a></li>
                        <li><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/logout.php">Вийти</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/login.php">Увійти</a></li>
                        <li><a href="<?php echo isset($base_url) ? $base_url : ''; ?>/register.php">Реєстрація</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="main-content">