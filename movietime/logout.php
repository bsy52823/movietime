<?php
// 1. 세션이 아직 시작되지 않았다면 시작합니다.
// (session_start() 없이 세션 변수를 조작하면 오류가 발생합니다.)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. 세션 변수 모두 제거
// 현재 세션에 저장된 모든 등록된 변수를 제거합니다.
$_SESSION = array();

// 3. 세션 쿠키 파괴
// 세션 ID가 저장된 쿠키를 삭제하여 브라우저가 더 이상 이 세션을 사용하지 않도록 합니다.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. 세션 파일 자체를 파괴
// 서버에 저장된 세션 데이터를 삭제합니다.
session_destroy();

// 5. 메인 페이지 또는 로그인 페이지로 리다이렉트
// index.php로 리다이렉트하는 것이 일반적입니다.
header('Location: index.php'); 
exit;
?>