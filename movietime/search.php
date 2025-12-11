<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// 사용자 번호 설정 (로그인 상태 확인을 위해)
$current_user_no = $_SESSION['user_no'] ?? 0;

// ===============================
// 검색어 받기
// ===============================
// index.php에서 GET 방식으로 전송된 'query' 값을 받습니다.
$search_query = trim($_GET['query'] ?? '');

// 검색어가 비어있거나 너무 짧으면 검색하지 않음
if (empty($search_query) || mb_strlen($search_query) < 2) {
    // 검색 목록 대신 안내 메시지를 표시하거나 index.php로 리디렉션할 수 있습니다.
    $movies = [];
    $search_message = "검색어를 2자 이상 입력해주세요.";
} else {
    $search_message = "'{$search_query}' 검색 결과";

    // ===============================
    // 영화 데이터 조회
    // ===============================
    // LIKE 검색을 사용하여 제목에 검색어가 포함된 영화들을 찾습니다.
    $sql = "
        SELECT 
            m.movie_no,
            m.title,
            m.poster,
            m.age_rating AS age,
            m.reserve_rate,
            IFNULL(AVG(r.rating), 0) AS rating,
            COUNT(DISTINCT b.user_no) AS audience_count,
            (SELECT COUNT(like_no) FROM likes l WHERE l.movie_no = m.movie_no AND l.user_no = ?) AS is_liked
        FROM 
            movies m
        LEFT JOIN 
            reviews r ON m.movie_no = r.movie_no
        LEFT JOIN 
            showtimes s ON m.movie_no = s.movie_no
        LEFT JOIN 
            bookings b ON s.showtime_no = b.showtime_no
        WHERE 
            m.title LIKE ? 
        GROUP BY 
            m.movie_no, m.title, m.poster, m.age_rating, m.reserve_rate
        ORDER BY 
            m.reserve_rate DESC;
    ";

    $movies = [];
    $rank = 1;
    $search_param = "%" . $search_query . "%"; // 'LIKE %검색어%' 형태

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $current_user_no, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        // **결과가 1개인 경우: 상세 페이지로 즉시 리디렉션**
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $movie_no = $row['movie_no'];
            $detail_url = "movie_detail.php?movie_no=" . $movie_no;
            
            // 리디렉션 실행
            header("Location: " . $detail_url);
            exit;
        }

        // 결과가 0개이거나 여러 개인 경우: 목록을 구성하여 보여줍니다.
        while ($row = $result->fetch_assoc()) {
            $row['rating'] = number_format($row['rating'], 1);
            $row['reserve_rate'] = number_format($row['reserve_rate'], 1) . '%';
            $row['rank'] = $rank++; // 검색 결과에서도 순위(번호)를 표시
            $row['audience'] = number_format($row['audience_count']) . '명';
            $movies[] = $row;
        }
        $result->free();
    } else {
        die("영화 정보 쿼리 오류: " . $conn->error);
    }
    $stmt->close();
}
?>

<main class="search-result-main-content container-1200">
    <section class="search-results-section">
        <h2 class="section-header">🔍 <?= $search_message ?></h2>
        
        <?php if (!empty($movies)): ?>
            <div class="movie-list-grid">
                <?php foreach ($movies as $movie) : ?>
                    <div class="movie-card">
                        <a href="movie_detail.php?movie_no=<?= $movie['movie_no'] ?>" class="poster-link-wrapper">
                            <div class="poster-container">
                                <span class="movie-rank"><?= $movie['rank'] ?></span>
                                <button class="like-button" 
                                    data-movie-no="<?= $movie['movie_no'] ?>" 
                                    data-liked="<?= $movie['is_liked'] ? '1' : '0' ?>">
                                    <img src="images/<?= $movie['is_liked'] ? 'ic_heart_filled.png' : 'ic_heart_unfilled.png' ?>" alt="좋아요">
                                </button>
                                <img src="images/<?= $movie['poster'] ?>" 
                                    alt="<?= $movie['title'] ?> 포스터" 
                                    class="movie-poster">
                            </div>
                        </a>

                        <div class="movie-info">
                            <div class="title-age-group">
                                <span class="movie-title"><?= $movie['title'] ?></span>
                                <img src="images/ic_age_<?= $movie['age'] ?>.png" class="age-icon">
                            </div>
                            <p class="movie-stats">
                                ⭐ <?= $movie['rating'] ?> 
                                <span class="divider">|</span>
                                예매율 <?= $movie['reserve_rate'] ?>
                            </p>
                            <a href="booking.php?movie_no=<?= $movie['movie_no'] ?>" class="btn-book">예매하기</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif(mb_strlen($search_query) >= 2): ?>
            <p class="no-results-message">검색어 **<?= htmlspecialchars($search_query) ?>**에 해당하는 영화를 찾을 수 없습니다.</p>
        <?php else: ?>
             <p class="no-results-message"><?= htmlspecialchars($search_message) ?></p>
        <?php endif; ?>
    </section>
</main>

<?php include_once('footer.php'); ?>