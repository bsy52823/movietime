<?php
// ===============================
// 1. DB 연결 및 헤더 포함
// ===============================
include_once('db_connect.php');
include_once('header.php'); 

// 이미 로그인되어 있다면 메인 페이지로 리다이렉트
if (isset($_SESSION['user_no'])) {
    header('Location: index.php');
    exit;
}

$error_message = [];
$success_message = '';

// 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 폼 데이터 가져오기 및 정리
    $user_id = trim($_POST['user_id']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // 1. 기본 유효성 검사
    if (empty($user_id) || empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error_message[] = "모든 필수 정보를 입력해야 합니다.";
    }

    // 2. 비밀번호 일치 확인
    if ($password !== $password_confirm) {
        $error_message[] = "비밀번호와 비밀번호 확인이 일치하지 않습니다.";
    }
    
    // 3. 비밀번호 길이 및 복잡성 (선택 사항)
    if (strlen($password) < 6) {
        $error_message[] = "비밀번호는 최소 6자 이상이어야 합니다.";
    }

    // 4. 아이디 중복 확인 및 이메일 형식 확인
    if (empty($error_message)) {
        
        // 아이디 중복 확인
        $stmt_id = $conn->prepare("SELECT user_no FROM users WHERE user_id = ?");
        $stmt_id->bind_param("s", $user_id);
        $stmt_id->execute();
        if ($stmt_id->get_result()->num_rows > 0) {
            $error_message[] = "이미 사용 중인 아이디입니다.";
        }
        $stmt_id->close();
        
        // 이메일 형식 확인
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message[] = "유효하지 않은 이메일 형식입니다.";
        }
    }
    
    // 5. 모든 검사를 통과했을 경우 DB 저장
    if (empty($error_message)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (user_id, username, email, password) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssss", $user_id, $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            // 회원가입 성공 시 로그인 페이지로 리다이렉트
            header('Location: login.php?registered=true');
            exit;
        } else {
            $error_message[] = "회원가입 중 오류가 발생했습니다. 다시 시도해 주세요. (" . $conn->error . ")";
        }

        $stmt->close();
    }
}
?>

<main class="auth-main-content">
    <div class="auth-container">
        <h2 class="auth-title">회원가입</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-list">
                <?php foreach ($error_message as $msg): ?>
                    <p class="error-message">⚠️ <?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form">
            
            <div class="input-group">
                <label for="user_id">아이디</label>
                <input type="text" id="user_id" name="user_id" placeholder="사용하실 아이디를 입력하세요" required
                       value="<?= isset($user_id) ? htmlspecialchars($user_id) : '' ?>">
            </div>
            
            <div class="input-group">
                <label for="username">이름</label>
                <input type="text" id="username" name="username" placeholder="이름을 입력하세요" required
                       value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
            </div>
            
            <div class="input-group">
                <label for="email">이메일</label>
                <input type="email" id="email" name="email" placeholder="이메일 주소를 입력하세요" required
                       value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>
            
            <hr class="form-divider">

            <div class="input-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" placeholder="비밀번호 (최소 6자)" required>
            </div>
            
            <div class="input-group">
                <label for="password_confirm">비밀번호 확인</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="비밀번호를 다시 입력하세요" required>
            </div>
            
            <button type="submit" class="btn-auth">회원가입 완료</button>
        </form>

        <div class="auth-options">
            <a href="login.php">이미 계정이 있으신가요? 로그인</a>
        </div>
    </div>
</main>

<?php 
include_once('footer.php'); 
?>