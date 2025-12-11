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
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$user_no = $_SESSION['user_no'];

$user_info = null;
$load_sql = "SELECT username, user_id, email FROM users WHERE user_no = $user_no";
$load_res = $conn->query($load_sql);

if ($load_res && $load_res->num_rows > 0) {
    $user_info = $load_res->fetch_assoc();
} else {
    // DB에서 정보를 찾을 수 없는 경우 (치명적인 오류)
    echo "<script>alert('사용자 정보를 찾을 수 없습니다.'); location.href='mypage.php';</script>";
    exit;
}

// ===============================
// 정보 수정 처리 로직 (POST)
// ===============================
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 폼에서 전송된 데이터
    $new_username = $conn->real_escape_string($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $new_password_confirm = $_POST['new_password_confirm'];

    // 1. 현재 비밀번호 확인을 위한 DB 쿼리
    $check_sql = "SELECT password FROM users WHERE user_no = $user_no";
    $check_res = $conn->query($check_sql);
    $hashed_password = $check_res->fetch_assoc()['password'];
    
    // password_verify()를 사용하여 입력된 비밀번호와 해시된 비밀번호 비교
    if (!password_verify($current_password, $hashed_password)) {
        $message = "현재 비밀번호가 일치하지 않습니다. 정보를 수정할 수 없습니다.";
    } else {
        $update_parts = [];

        // 2. username 변경
        if ($new_username != $user_info['username']) {
            $update_parts[] = "username = '$new_username'";
        }

        // 3. 비밀번호 변경 (새 비밀번호가 입력된 경우에만)
        if (!empty($new_password)) {
            if ($new_password !== $new_password_confirm) {
                $message = "새 비밀번호와 비밀번호 확인이 일치하지 않습니다.";
            } else {
                // 비밀번호는 반드시 해싱하여 저장
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_parts[] = "password = '$hashed_new_password'";
            }
        }
        
        // 4. DB 업데이트 실행
        if ($message === '' && !empty($update_parts)) {
            $update_sql = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE user_no = $user_no";
            
            if ($conn->query($update_sql)) {
                // 세션의 username 업데이트 (즉시 반영)
                $_SESSION['username'] = $new_username; 
                $message = "정보가 성공적으로 수정되었습니다.";
                
                // 정보 수정 후, 새로고침을 위해 페이지를 다시 로드
                echo "<script>alert('정보가 성공적으로 수정되었습니다.'); location.href='mypage_info.php';</script>";
                exit;
            } else {
                $message = "DB 업데이트 중 오류 발생: " . $conn->error;
            }
        } else if (empty($update_parts)) {
            $message = "변경 사항이 없습니다.";
        }
    }
}

// 최종적으로 DB에서 로드된 정보를 HTML에 표시
?>

<main class="mypage-main-content">
    <div class="mypage-layout">
        
        <h2 class="mypage-title">회원 정보 수정</h2>
        
        <div class="info-update-container">

            <?php if ($message): ?>
                <div class="alert <?= strpos($message, '성공') !== false ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="mypage_info.php" method="POST" class="user-info-form">
                
                <div class="form-group readonly">
                    <label for="user_id">아이디</label>
                    <input type="text" id="user_id" value="<?= htmlspecialchars($user_info['user_id']) ?>" readonly>
                </div>
                
                <div class="form-group readonly">
                    <label for="email">이메일</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user_info['email']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="username">사용자 표시 이름</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user_info['username']) ?>" required>
                </div>

                <hr>
                
                <h4 class="section-title">비밀번호 변경</h4>

                 <div class="form-group">
                    <label for="current_password">현재 비밀번호 <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required placeholder="현재 비밀번호를 입력해야 수정 가능">
                </div>

                <div class="form-group">
                    <label for="new_password">새 비밀번호</label>
                    <input type="password" id="new_password" name="new_password" placeholder="변경하려면 입력하세요">
                </div>
                
                <div class="form-group">
                    <label for="new_password_confirm">새 비밀번호 확인</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" placeholder="새 비밀번호를 다시 입력하세요">
                </div>
                
                <hr>


                <div class="form-actions">
                    <button type="submit" class="btn-submit">정보 수정</button>
                    </div>
            </form>
        </div>
    </div>
</main>

<?php include_once('footer.php'); ?>