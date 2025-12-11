<?php
// ===============================
// DB 연결 및 세션 시작
// ===============================
include_once('db_connect.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 로그인 확인
if (!isset($_SESSION['user_no'])) {
    die("<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>");
}

$user_no = $_SESSION['user_no'];

// POST 데이터 받기
$showtime_no = isset($_POST['showtime_no']) ? intval($_POST['showtime_no']) : 0;
$movie_no = isset($_POST['movie_no']) ? intval($_POST['movie_no']) : 0;
$selected_seats_json = isset($_POST['selected_seats_json']) ? $_POST['selected_seats_json'] : '[]';

$selected_seats = json_decode($selected_seats_json, true);

if ($showtime_no === 0 || $movie_no === 0 || empty($selected_seats)) {
    die("<script>alert('필수 예매 정보가 누락되었습니다.'); location.href='index.php';</script>");
}

// 선택된 좌석 유효성 검사 (최대 4석 제한)
if (count($selected_seats) < 1 || count($selected_seats) > 4) {
    die("<script>alert('좌석은 최소 1석, 최대 4석까지만 선택 가능합니다.'); history.back();</script>");
}

$conn->begin_transaction(); // 트랜잭션 시작 (원자성 확보)

try {
    $success_count = 0;
    
    // 예매된 좌석 중복 확인 및 삽입
    foreach ($selected_seats as $seat_id) {
        $seat_id = htmlspecialchars(trim($seat_id));

        // 1. 이미 예매된 좌석인지 재확인 (Race condition 방지)
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE showtime_no = ? AND seat_id = ?");
        $stmt_check->bind_param("is", $showtime_no, $seat_id);
        $stmt_check->execute();
        $is_booked = $stmt_check->get_result()->fetch_row()[0];
        $stmt_check->close();

        if ($is_booked > 0) {
            // 이미 예매된 좌석이 있다면 트랜잭션 롤백
            $conn->rollback();
            die("<script>alert('선택하신 좌석 중 [{$seat_id}]은(는) 이미 예매되었습니다. 다시 선택해주세요.'); location.href='booking.php?movie_no={$movie_no}';</script>");
        }

        // 2. 예매 기록 삽입
        $booking_date = date('Y-m-d H:i:s');
        $stmt_insert = $conn->prepare("
            INSERT INTO bookings (user_no, showtime_no, seat_id, booking_date) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("iiss", $user_no, $showtime_no, $seat_id, $booking_date);
        
        if (!$stmt_insert->execute()) {
            // 삽입 실패 시 롤백
            $conn->rollback();
            die("<script>alert('예매 처리 중 오류가 발생했습니다: " . $conn->error . "'); location.href='booking.php?movie_no={$movie_no}';</script>");
        }
        $stmt_insert->close();
        $success_count++;
    }

    $conn->commit(); // 모든 좌석 삽입 성공 시 커밋
    
    // 최종 성공 메시지
    $seats_list = implode(', ', $selected_seats);
    echo "<script>alert('예매가 성공적으로 완료되었습니다! (좌석: {$seats_list})'); location.href='my_page.php';</script>";

} catch (Exception $e) {
    $conn->rollback();
    die("<script>alert('예매 처리 중 예상치 못한 오류가 발생했습니다.'); location.href='booking.php?movie_no={$movie_no}';</script>");
}

$conn->close();
?>