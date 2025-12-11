<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// ===============================
// 로그인 확인 및 사용자 번호 설정
// ===============================
if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('예매 내역은 로그인 후 확인 가능합니다.'); location.href='login.php';</script>";
    exit;
}
$user_no = $_SESSION['user_no'];

// ===============================
// 사용자의 예매 내역 조회 (SQL 쿼리 수정)
// ===============================
$sql = "
    SELECT 
        B.book_no,                  -- 예매 고유 번호
        B.total_price,              -- 총 예매 가격
        B.booked_at,                -- 예매 시각
        M.title, 
        M.age_rating,
        M.movie_no,
        M.poster,
        T.name AS theater_name,
        ST.start_time,              -- showtimes 테이블의 시작 시간
        DATE_ADD(ST.start_time, INTERVAL M.running_time MINUTE) AS end_time, -- 종료 시간 계산
        
        -- booked_seats 테이블을 JOIN하여 좌석 코드를 콤마로 묶음
        GROUP_CONCAT(BS.seat_code ORDER BY BS.seat_code) AS seats_raw, 
        
        -- 좌석의 총 개수를 계산 (인원 수)
        COUNT(BS.book_seat_no) AS total_person_count
    FROM 
        bookings B -- 별칭 B로 통일
    JOIN 
        showtimes ST ON B.showtime_no = ST.showtime_no -- 별칭 ST로 통일
    JOIN 
        movies M ON ST.movie_no = M.movie_no -- 별칭 M으로 통일
    JOIN 
        theaters T ON ST.theater_no = T.theater_no -- 별칭 T로 통일
    JOIN
        booked_seats BS ON B.book_no = BS.book_no -- ⭐ [필수 추가] booked_seats 테이블 JOIN
    WHERE 
        B.user_no = ?
    GROUP BY 
        B.book_no -- ⭐ [필수 추가] GROUP_CONCAT 사용을 위해 예매 번호별로 그룹화
    ORDER BY 
        ST.start_time DESC, M.title, T.name; -- ❌ b.seat_number는 삭제
";

// 라인 48: $stmt = $conn->prepare($sql);
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // 혹시 모를 SQL prepare 오류 시 메시지 출력
    die('SQL Prepare failed: ' . $conn->error);
}

$stmt->bind_param("i", $user_no);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];

if ($result) {
    // SQL에서 GROUP_CONCAT과 GROUP BY를 사용했으므로, 
    // PHP에서 복잡한 그룹화 로직(grouped_bookings)은 필요하지 않습니다.
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $result->free();
}
$stmt->close();
// ----------------------------------------------------
// PHP에서 중복 그룹화 로직 (grouped_bookings 관련)은 삭제되었습니다.
// ----------------------------------------------------
?>

<div class="my-page-wrapper container-1200">
    <h2 class="page-title">내 예매 내역</h2>

    <?php if (empty($bookings)): ?>
        <div class="no-bookings-message">
            <p>아직 예매 내역이 없습니다.</p>
            <a href="index.php" class="btn-go-home">영화 예매하러 가기</a>
        </div>
    <?php else: ?>
        <div class="booking-list">
            <?php foreach ($bookings as $booking): 
                // 포스터 경로 설정 (movie_no가 1~8이라고 가정)
                $poster_num = $booking['movie_no'] >= 1 && $booking['movie_no'] <= 8 ? $booking['movie_no'] : 'default';
                $poster_path = "images/movie{$poster_num}.jpg";
                $icon_path = "images/ic_age_" . strtolower($booking['age_rating']) . ".png";
                
                // SQL에서 가져온 좌석 목록과 인원수 사용
                $seats_display = $booking['seats_raw']; // SQL GROUP_CONCAT 결과
                $person_count = $booking['total_person_count'];
                $person_summary = "총 {$person_count}명"; // 인원 요약 추가
                
                $formatted_price = number_format($booking['total_price']);
                $start_time_display = date('Y.m.d (D) H:i', strtotime($booking['start_time']));
                $end_time_display = date('H:i', strtotime($booking['end_time']));
            ?>
                <div class="booking-item-box">
                    <div class="movie-poster-image">
                        <img src="<?= htmlspecialchars($poster_path) ?>" alt="<?= htmlspecialchars($booking['title']) ?> 포스터">
                    </div>

                    <div class="detail-content">
                        <div class="movie-title-group">
                            <strong><?= htmlspecialchars($booking['title']) ?></strong> 
                            <img class="age-icon-in-detail" src="<?= htmlspecialchars($icon_path) ?>" alt="<?= htmlspecialchars($booking['age_rating']) ?> 등급">
                        </div>

                        <p class="detail-summary">
                            <strong>일시:</strong> <?= $start_time_display ?> ~ <?= $end_time_display ?> 
                            <span class="theater-name-display">(<?= htmlspecialchars($booking['theater_name']) ?>)</span>
                        </p>

                        <p class="detail-summary">
                            <strong>인원:</strong> <?= htmlspecialchars($person_summary) ?> |
                            <strong>좌석:</strong> <?= htmlspecialchars($seats_display) ?> (총 <?= $person_count ?>석)
                        </p>

                        <div class="payment-summary">
                            총 결제 금액: <strong class="final-price"><?= $formatted_price ?>원</strong>
                        </div>
                    </div>
                    
                    <div class="booking-actions">
                        <a href="booking_complete.php?book_no=<?= $booking['book_no'] ?>" class="btn-primary">예매 상세/티켓</a>
                        <button class="btn-secondary" onclick="alert('예매 취소 기능은 준비 중입니다.');">예매 취소</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once('footer.php'); ?>