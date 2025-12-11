<?php
// ===============================
// DB 연결
// ===============================
include_once('db_connect.php');

include_once('header.php'); 

// 이미 로그인되어 있다면 메인 페이지로 리다이렉트
if (isset($_SESSION['user_no'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';

// 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];

    if (empty($user_id) || empty($password)) {
        $error_message = "아이디와 비밀번호를 모두 입력해 주세요.";
    } else {
        // 2. 사용자 ID로 정보 조회
        $sql = "SELECT user_no, username, password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 3. 비밀번호 확인 (DB에 해시된 비밀번호가 저장되어 있다고 가정)
            if (password_verify($password, $user['password'])) {
                // 로그인 성공: 세션에 사용자 정보 저장
                $_SESSION['user_no'] = $user['user_no'];
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user['username'];

                // 메인 페이지로 리다이렉트
                header('Location: index.php');
                exit;
            } else {
                $error_message = "아이디 또는 비밀번호가 올바르지 않습니다.";
            }
        } else {
            $error_message = "아이디 또는 비밀번호가 올바르지 않습니다.";
        }

        $stmt->close();
    }
}
?>

<main class="auth-main-content">
    <div class="auth-container">
        <h2 class="auth-title">로그인</h2>

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <form action="" method="POST" class="auth-form">
            <div class="input-group">
                <label for="user_id">아이디</label>
                <input type="text" id="user_id" name="user_id" placeholder="아이디를 입력하세요" required
                       value="<?= isset($user_id) && $error_message ? htmlspecialchars($user_id) : '' ?>">
            </div>
            
            <div class="input-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" placeholder="비밀번호를 입력하세요" required>
            </div>
            
            <button type="submit" class="btn-auth">로그인</button>
        </form>

        <div class="auth-options">
            <a href="register.php">회원가입</a>
            <span class="divider">|</span>
            <a href="#">아이디/비밀번호 찾기</a>
        </div>
    </div>
</main>

<?php 
// 4. Footer 포함
include_once('footer.php'); 
?>