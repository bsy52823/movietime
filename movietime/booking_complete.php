<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('db_connect.php');
include_once('header.php');

if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}
$user_no = $_SESSION['user_no'];

// ===============================
// 1. GET 파라미터
// ===============================
$showtime_no = $_GET['showtime_no'] ?? null;
$seats_raw = $_GET['seats'] ?? '';
$total_price = $_GET['total_price'] ?? 0;
$person_counts_json = $_GET['person_counts'] ?? '{}';

// 인원 검증
$person_counts = json_decode($person_counts_json, true);
if (!$person_counts || array_sum($person_counts) === 0) {
    echo "<script>alert('예매 정보가 불완전합니다.'); location.href='booking.php';</script>";
    exit;
}

$seats_array = array_filter(array_map('trim', explode(',', $seats_raw))); // 공백 제거

// 가격 매핑
$priceMap = ['adult'=>14000,'teen'=>12000,'senior'=>7000,'disabled'=>5000];

// person_queue 생성
$person_queue = [];
foreach ($person_counts as $type => $count) {
    for ($i=0;$i<$count;$i++) $person_queue[] = $type;
}

if (count($seats_array) !== count($person_queue)) {
    echo "<script>alert('좌석 수와 인원 수가 일치하지 않습니다.'); location.href='booking_seats.php?showtime_no={$showtime_no}';</script>";
    exit;
}

// ===============================
// 2. 트랜잭션 시작
// ===============================
$conn->begin_transaction();
try {
    // bookings 삽입
    $stmt = $conn->prepare("INSERT INTO bookings (user_no, showtime_no, total_price) VALUES (?, ?, ?)");
    if (!$stmt) throw new Exception("Prepare bookings 실패: ".$conn->error);
    $stmt->bind_param("iii", $user_no, $showtime_no, $total_price);
    if (!$stmt->execute()) throw new Exception("Bookings execute 실패: ".$stmt->error);
    $book_no = $conn->insert_id;
    $stmt->close();

    // booked_seats 삽입
    $stmt = $conn->prepare("INSERT INTO booked_seats (book_no, seat_code, ticket_type, price) VALUES (?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Prepare booked_seats 실패: ".$conn->error);

    for ($i=0;$i<count($seats_array);$i++) {
        $seat_code = $seats_array[$i];
        $ticket_type = $person_queue[$i] ?? 'adult';
        $price = $priceMap[$ticket_type] ?? 0;
        $stmt->bind_param("issi",$book_no,$seat_code,$ticket_type,$price);
        if (!$stmt->execute()) throw new Exception("Booked_seats execute 실패: ".$stmt->error);
    }
    $stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    echo "<pre>예매 중 오류 발생: ".$e->getMessage()."</pre>";
    exit;
}

// ===============================
// 3. 예매 상세 조회
// ===============================
$stmt = $conn->prepare("
    SELECT 
        B.book_no,B.total_price,
        M.title,M.age_rating,M.movie_no,
        T.name AS theater_name,
        ST.start_time,
        DATE_ADD(ST.start_time, INTERVAL M.running_time MINUTE) AS end_time,
        GROUP_CONCAT(BS.seat_code ORDER BY BS.seat_code) AS seats_raw,
        COUNT(BS.book_seat_no) AS total_person_count
    FROM bookings B
    JOIN showtimes ST ON B.showtime_no=ST.showtime_no
    JOIN movies M ON ST.movie_no=M.movie_no
    JOIN theaters T ON ST.theater_no=T.theater_no
    JOIN booked_seats BS ON B.book_no=BS.book_no
    WHERE B.book_no=?
    GROUP BY B.book_no
");
$stmt->bind_param("i",$book_no);
$stmt->execute();
$booking_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ===============================
// 4. 화면 출력 변수 설정
// ===============================
$movie_title = $booking_data['title'];
$age_rating = $booking_data['age_rating'];
$theater_name = $booking_data['theater_name'];
$total_price = $booking_data['total_price'];
$seats_array = explode(',',$booking_data['seats_raw']);
$person_summary = "총 {$booking_data['total_person_count']}명";
$movie_no = $booking_data['movie_no'];

$poster_path = "images/movie".($movie_no>=1&&$movie_no<=8?$movie_no:"default").".jpg";
$age_rating_for_file = ($age_rating=="전체")?"all":( ($age_rating=="청불"||$age_rating=="18")?"18":strtolower($age_rating) );
$icon_path = "images/ic_age_{$age_rating_for_file}.png";
$formatted_price = number_format($total_price);

$start_time_display = date('Y.m.d (D) H:i',strtotime($booking_data['start_time']));
$end_time = date('H:i',strtotime($booking_data['end_time']));
?>

<!-- HTML 출력 -->
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
                    <strong>일시:</strong> <?= $start_time_display ?> ~ <?= $end_time ?> 
                    <span class="theater-name-display">(<?= htmlspecialchars($theater_name) ?>)</span>
                </p>
                <p class="detail-summary">
                    <strong>인원:</strong> <?= htmlspecialchars($person_summary) ?> | 
                    <strong>좌석:</strong> <?= htmlspecialchars(implode(', ',$seats_array)) ?>
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
