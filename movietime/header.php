<?php
// 세션이 아직 시작되지 않았을 때만 시작
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_no']);
$username = $is_logged_in ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>MOVIE TIME - 영화 예매</title>

    <link rel="stylesheet" href="style.css">
    
    </head>
<body>

<!-- ===========================================================
     🎬 메인 헤더 영역
=========================================================== -->
<div class="header-container">
    <div class="top-bar-menu container-1000">
        <div class="logo-title">
            <a href="index.php">
                <img src="images/logo.png" alt="로고" class="logo-icon">
                <span class="logo-text">MOVIE TIME</span>
            </a>
        </div>

        <div class="auth-links">
            <?php if ($is_logged_in): ?>
                <span class="username-display"><?= htmlspecialchars($username) ?>님</span>
                <a href="logout.php" class="btn-small-auth">로그아웃</a>
            <?php else: ?>
                <a href="login.php" class="btn-small-auth">로그인</a>
                <a href="register.php" class="btn-small-auth">회원가입</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="main-navigation container-1000">
        <div class="menu-wrapper">
            <ul class="main-menu">
                <li><a href="booking.php">예매</a></li>
                <li><a href="movie.php">영화</a></li>
                <li><a href="#">극장</a></li>
                <li><a href="#">이벤트</a></li>
                <li><a href="#">스토어</a></li>
            </ul>
        </div>

        <div class="action-buttons">
            <a href="booking.php" class="btn-action icon-only">
                <img src="images/ic_calendar.png" alt="즉시예매">
            </a>
            <a href="mypage.php" class="btn-action icon-only">
                <img src="images/ic_my.png" alt="내정보">
            </a>
        </div>
    </nav>
</div>