<?php
// 데이터베이스 연결 설정
$servername = "localhost"; 
$username = "root";
$password = "";
$dbname = "movietime";

// MySQL 연결
$conn = mysqli_connect($servername, $username, $password, $dbname);

// 연결 확인
if ($conn->connect_error) {
    die("🚨 데이터베이스 연결 실패: " . $conn->connect_error);
}

// 문자셋 설정 (한글 깨짐 방지)
$conn->set_charset("utf8mb4");
?>