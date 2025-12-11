<?php
// =======================================================
// toggle_like.php
// 좋아요(LIKE) 추가/삭제를 처리하는 스크립트
// movie_detail.php에서 POST 요청을 받아 처리합니다.
// =======================================================

// 1. 세션 시작 및 DB 연결
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 이 파일이 별도로 실행될 경우 db_connect.php가 필요합니다.
// 실제 환경에 맞게 경로를 수정하세요.
include_once('db_connect.php'); 

// 2. 현재 사용자 정보 확인
$current_user_no = $_SESSION['user_no'] ?? 0;

if ($current_user_no <= 0) {
    // 비로그인 사용자 처리: JSON으로 반환 또는 로그인 페이지로 리디렉션
    // 일반적으로 AJAX로 호출되지만, 폼 제출 방식도 대비합니다.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // AJAX 요청인 경우
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit();
    } else {
        // 일반 폼 제출인 경우
        echo "<script>alert('로그인이 필요한 기능입니다.'); window.location.href='login.php';</script>";
        exit();
    }
}

// 3. POST 요청 및 필요한 데이터 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['movie_no'])) {
    // 유효하지 않은 요청 처리
    die("유효하지 않은 접근입니다.");
}

$movie_no = $_POST['movie_no'];
if (!is_numeric($movie_no)) {
    die("유효하지 않은 영화 번호입니다.");
}

// 4. 좋아요 상태 확인
$is_liked = false;
$sql_check_like = "SELECT like_no FROM likes WHERE movie_no = ? AND user_no = ?";
$stmt_check_like = $conn->prepare($sql_check_like);
$stmt_check_like->bind_param("ii", $movie_no, $current_user_no);
$stmt_check_like->execute();
$result_check_like = $stmt_check_like->get_result();

if ($result_check_like->num_rows > 0) {
    $is_liked = true;
    // 이미 좋아요를 눌렀으면 취소 (DELETE)
    $sql_toggle = "DELETE FROM likes WHERE movie_no = ? AND user_no = ?";
} else {
    // 좋아요를 누르지 않았으면 추가 (INSERT)
    $sql_toggle = "INSERT INTO likes (movie_no, user_no) VALUES (?, ?)";
}
$stmt_check_like->close();


// 5. 좋아요 토글 쿼리 실행
$stmt_toggle = $conn->prepare($sql_toggle);
$stmt_toggle->bind_param("ii", $movie_no, $current_user_no);

$success = false;
$message = '';
$new_like_status = !$is_liked; // 토글 후의 상태

if ($stmt_toggle->execute()) {
    $success = true;
    $message = $new_like_status ? "좋아요를 눌렀습니다." : "좋아요를 취소했습니다.";
} else {
    $message = "좋아요 처리 중 오류가 발생했습니다: " . $stmt_toggle->error;
    
    // 외래 키 오류(1452) 등 심각한 오류 발생 시 세션 초기화 유도
    if ($stmt_toggle->errno == 1452) {
        // Foreign Key Constraint Fails (user_no 또는 movie_no가 DB에 없는 경우)
        $message = "사용자 정보 또는 영화 정보가 유효하지 않습니다. 다시 로그인해 주세요.";
        // 세션 초기화
        session_unset();
        session_destroy();
    }
}
$stmt_toggle->close();


// 6. 좋아요 카운트 다시 조회
$sql_count = "SELECT COUNT(like_no) AS like_count FROM likes WHERE movie_no = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $movie_no);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$like_count = $result_count->fetch_assoc()['like_count'];
$stmt_count->close();
$conn->close();


// 7. 결과 반환 (JSON 응답 권장)
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'new_status' => $new_like_status, // true: 좋아요 상태, false: 좋아요 취소 상태
    'like_count' => (int)$like_count,
    'message' => $message
]);

exit();
?>