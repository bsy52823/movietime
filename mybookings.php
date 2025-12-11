<?php
include_once('db_connect.php');
include_once('header.php');

if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('로그인 후 확인 가능합니다.'); location.href='login.php';</script>";
    exit;
}
$user_no = $_SESSION['user_no'];

// LEFT JOIN으로 좌석 없는 예매도 표시
$sql = "
SELECT 
    B.book_no, B.total_price, B.booked_at,
    M.title, M.age_rating, M.movie_no,
    T.name AS theater_name,
    ST.start_time,
    DATE_ADD(ST.start_time, INTERVAL M.running_time MINUTE) AS end_time,
    GROUP_CONCAT(BS.seat_code ORDER BY BS.seat_code) AS seats_raw,
    COUNT(BS.book_seat_no) AS total_person_count
FROM bookings B
JOIN showtimes ST ON B.showtime_no = ST.showtime_no
JOIN movies M ON ST.movie_no = M.movie_no
JOIN theaters T ON ST.theater_no = T.theater_no
LEFT JOIN booked_seats BS ON B.book_no = BS.book_no
WHERE B.user_no = ?
GROUP BY B.book_no
ORDER BY ST.start_time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$user_no);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="my-page-wrapper container-1000">
    <h2 class="page-title">내 예매 내역</h2>
    <?php if(empty($bookings)): ?>
        <div class="no-bookings-message">
            <p>아직 예매 내역이 없습니다.</p>
            <a href="index.php" class="btn-go-home">영화 예매하러 가기</a>
        </div>
    <?php else: ?>
        <div class="booking-list">
        <?php foreach($bookings as $booking):
            $poster_num = $booking['movie_no']>=1 && $booking['movie_no']<=8 ? $booking['movie_no']:"default";
            $poster_path = "images/movie{$poster_num}.jpg";
            $age_rating_for_file = ($booking['age_rating']=="전체")?"all":( ($booking['age_rating']=="청불"||$booking['age_rating']=="18")?"18":strtolower($booking['age_rating']) );
            $icon_path = "images/ic_age_{$age_rating_for_file}.png";
            $seats_display = $booking['seats_raw'] ? $booking['seats_raw'] : '';
            $person_summary = "총 {$booking['total_person_count']}명";
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
                        <strong>좌석:</strong> <?= htmlspecialchars($seats_display) ?> (총 <?= $booking['total_person_count'] ?>석)
                    </p>
                    <div class="payment-summary">
                        총 결제 금액: <strong class="final-price"><?= $formatted_price ?>원</strong>
                    </div>
                    <div class="booking-actions">
                        <a href="booking_complete.php?book_no=<?= $booking['book_no'] ?>" class="btn-primary">예매 상세/티켓</a>
                        <button class="btn-secondary" onclick="alert('예매 취소 기능은 준비 중입니다.');">예매 취소</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once('footer.php'); ?>
