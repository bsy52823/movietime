<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('db_connect.php');
include_once('header.php');

$user_no = $_SESSION['user_no'];

// ===============================
// 1. GET 파라미터
// ===============================
$book_no = $_GET['book_no'] ?? null;
$showtime_no = $_GET['showtime_no'] ?? null;

// 기본 초기화
$movie_title = $age_rating = $theater_name = $start_time_display = $end_time = $person_summary = '';
$icon_path = 'images/ic_age_all.png';
$seats_array = [];
$total_price = 0;
$movie_no = 0;

// ===============================
// 2. DB 조회 (book_no 있는 경우)
// ===============================
$booking_data = null;

if ($book_no) {
    $sql_fetch = "
        SELECT 
            B.book_no, B.total_price,
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
        JOIN booked_seats BS ON B.book_no = BS.book_no 
        WHERE B.book_no = ?
        GROUP BY B.book_no;
    ";
    $stmt = $conn->prepare($sql_fetch);
    $stmt->bind_param("i", $book_no);
    $stmt->execute();
    $booking_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($booking_data) {
        $movie_title = $booking_data['title'];
        $age_rating = $booking_data['age_rating'];
        $theater_name = $booking_data['theater_name'];
        $total_price = $booking_data['total_price'];
        $seats_array = explode(',', $booking_data['seats_raw']);
        $person_summary = "총 {$booking_data['total_person_count']}명";
        $movie_no = $booking_data['movie_no'];

        $start_timestamp = strtotime($booking_data['start_time']);
        $day_of_week_korean = ['일','월','화','수','목','금','토'];
        $day_korean = $day_of_week_korean[date("w",$start_timestamp)];
        $start_time_display = date('Y.m.d', $start_timestamp)." ({$day_korean}) ".date('H:i', $start_timestamp);
        $end_time = date('H:i', strtotime($booking_data['end_time']));
    }
}

// ===============================
// 3. URL 파라미터로 대체 (DB 조회 실패 시)
// ===============================
if (!$booking_data) {
    $seats_raw = $_GET['seats'] ?? '';
    $total_price = $_GET['total_price'] ?? 0;
    $person_counts_json = $_GET['person_counts'] ?? '{}';
    $movie_title = $_GET['movie_title'] ?? '영화 제목 없음';
    $age_rating = $_GET['age_rating'] ?? '전체';
    $start_time_display = $_GET['start_time_display'] ?? '';
    $end_time = $_GET['end_time'] ?? '';
    $theater_name = $_GET['theater_name'] ?? '';
    $person_summary = $_GET['person_summary'] ?? '';
    $icon_path = $_GET['icon_path'] ?? 'images/ic_age_all.png';
    $movie_no = $_GET['movie_no'] ?? 0;

    $seats_array = explode(',', $seats_raw);
}

// ===============================
// 4. 포스터 및 등급 아이콘 설정
// ===============================
$poster_num = $movie_no >= 1 && $movie_no <= 8 ? $movie_no : 'default';
$poster_path = "images/movie{$poster_num}.jpg";

$age_rating_lower = strtolower($age_rating);
if ($age_rating_lower == '전체') {
    $age_rating_for_file = 'all'; 
} else if ($age_rating_lower == '청불' || $age_rating_lower == '18') {
    $age_rating_for_file = '18'; 
} else {
    $age_rating_for_file = $age_rating_lower;
}
$icon_path = "images/ic_age_{$age_rating_for_file}.png";

$formatted_price = number_format($total_price);

?>

<div class="seat-booking-wrapper-complete"> 

    <div class="complete-container">

        <div class="complete-header">
            <div class="complete-title">예매가 완료되었습니다!</div>
            <div class="complete-message">성공적으로 결제가 완료되었으며, 예매 정보가 등록되었습니다.</div>
        </div>

        <div class="booking-detail-box">
            <div class="movie-poster-image">
                <img src="<?= htmlspecialchars($poster_path) ?>" alt="<?= htmlspecialchars($movie_title) ?> 포스터">
            </div>

            <div class="detail-content">
                <div class="movie-title-group">
                    <strong><?= htmlspecialchars($movie_title) ?></strong> 
                    <img class="age-icon-in-detail" src="<?= htmlspecialchars($icon_path) ?>" alt="<?= htmlspecialchars($age_rating) ?> 등급">
                </div>

                <p class="detail-summary">
                    <strong>일시:</strong> <?= htmlspecialchars($start_time_display) ?> ~ <?= htmlspecialchars($end_time) ?> 
                    <span class="theater-name-display">(<?= htmlspecialchars($theater_name) ?>)</span>
                </p>

                <p class="detail-summary">
                    <strong>인원:</strong> <?= htmlspecialchars($person_summary) ?> | 
                    <strong>좌석:</strong> <?= htmlspecialchars(implode(', ', $seats_array)) ?>
                </p>

                <div class="payment-summary">
                    총 결제 금액: <strong class="final-price"><?= $formatted_price ?>원</strong>
                </div>
            </div>
        </div>

        <div class="complete_to_myhome">
            <a href="mybookings.php" class="btn-home">예매 내역 확인</a>
        </div>

    </div>
</div>

<?php include_once('footer.php'); ?>
