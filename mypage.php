<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// ===============================
// 로그인 확인 (필수)
// ===============================
if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('마이페이지는 로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$user_no = $_SESSION['user_no'];

// ===============================
// 사용자 정보 및 통계 데이터 로드
// ===============================

// 1. 사용자 이름 로드
$user_name = '';
$user_sql = "SELECT username FROM users WHERE user_no = $user_no"; // 💡 수정: name -> username
$user_res = $conn->query($user_sql);
if ($user_res && $user_res->num_rows > 0) {
    $user_data = $user_res->fetch_assoc();
    $user_name = htmlspecialchars($user_data['username']); // 💡 수정: $user_data['username']
}

// 2. 총 예매 건수 로드
$reserve_count = 0;
$count_sql = "SELECT COUNT(*) AS total FROM bookings WHERE user_no = $user_no"; 
$count_res = $conn->query($count_sql);
if ($count_res) {
    $count_data = $count_res->fetch_assoc();
    $reserve_count = (int)$count_data['total'];
}
?>

<main class="mypage-main-content">
    <div class="mypage-layout">
        
        <h2 class="mypage-title"><?= $user_name ?> 님의 마이페이지</h2>

        <div class="mypage-dashboard">
            <div class="dashboard-greeting">
                <p class="greeting-highlight">환영합니다, 
                    <span class="user-name-highlight"><?= $user_name ?></span> 님!
                </p>
                <p>이곳에서는 영화 예매 서비스 이용 내역을 확인하고 </p>
                <p>정보를 관리할 수 있습니다.</p>
            </div>

            <div class="dashboard-stats">
                <h3>나의 활동 정보</h3>
                <div class="stat-card">
                    <p class="stat-label">총 예매 건수</p>
                    <p class="stat-value"><?= $reserve_count ?> 건</p>
                </div>
                </div>
        </div>

        <div class="mypage-navigation">
            <h3>메뉴</h3>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="mybookings.php" class="nav-link">
                        <img src="images/ic_ticket.png" alt="예매 내역 아이콘" class="icon-img">
                        <span class="text">예매 내역 조회 및 취소</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mypage_info.php" class="nav-link">
                        <img src="images/ic_myinfo.png" alt="회원정보 아이콘" class="icon-img">
                        <span class="text">회원 정보 수정</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link action-logout">
                        <img src="images/ic_logout.png" alt="로그아웃 아이콘" class="icon-img">
                        <span class="text">로그아웃</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</main>

<?php include_once('footer.php'); ?>