<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

if (isset($_SESSION['user_no'])) {
    $current_user_no = $_SESSION['user_no'];
} else {
    // 비로그인 사용자일 경우, is_liked 쿼리가 작동하도록 0 또는 NULL로 설정
    $current_user_no = 0; // 또는 'NULL'을 사용하고 SQL 쿼리를 수정
}

// ===============================
// 정렬 기준 처리
// ===============================
$sort_by = $_GET['sort'] ?? 'reserve_rate';

$allowed_sorts = ['reserve_rate', 'release_date', 'rating_avg', 'wishlist'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'reserve_rate';
}

$join_clause = "";
$where_clause = "";
$order_clause = "";
$select_liked_at = "";

switch ($sort_by) {
    case 'release_date':
        $order_clause = "m.release_date DESC, m.reserve_rate DESC"; // 개봉일 최신순
        break;
    case 'rating_avg':
        $order_clause = "rating_avg DESC, m.reserve_rate DESC"; // 평점 높은 순
        break;
    case 'wishlist':
        // 로그인한 경우에만 찜 목록 필터링
        if ($current_user_no > 0) {
            // 찜 목록(likes) 테이블 조인 및 사용자 필터링
            $join_clause = "INNER JOIN likes l ON m.movie_no = l.movie_no";
            $where_clause = "WHERE l.user_no = {$current_user_no}";
            
            // 찜한 시간순(created_at)으로 정렬 (테이블에 추가된 컬럼 사용)
            $order_clause = "l.created_at DESC, m.reserve_rate DESC";
            
            // ORDER BY에 사용되는 컬럼은 SELECT 절에도 명시되어야 함
            $select_liked_at = ', l.created_at';
        } else {
            // 비로그인 시 예매율순으로 대체
            $sort_by = 'reserve_rate';
            $order_clause = "m.reserve_rate DESC, m.release_date DESC";
        }
        break;
    default:
        $order_clause = "m.reserve_rate DESC, m.release_date DESC"; // 예매율 높은 순
        break;
}

// ===============================
// 모든 영화 데이터 조회 (최신순 또는 가나다순 정렬)
// ===============================
$sql = "
    SELECT 
        m.movie_no,
        title,
        m.poster,
        age_rating,
        running_time,
        release_date,
        IFNULL(reserve_rate, 0) AS reserve_rate,
        IFNULL(AVG(r.rating), 0) AS rating_avg
        {$select_liked_at}
    FROM 
        movies m
    LEFT JOIN 
        reviews r ON m.movie_no = r.movie_no
        {$join_clause}  /* wishlist 일 때만 INNER JOIN likes 추가 */
        {$where_clause} /* wishlist 일 때만 WHERE user_no 필터링 추가 */
    GROUP BY 
        m.movie_no
    ORDER BY 
        {$order_clause};
";

$movies = [];
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['rating_avg'] = number_format($row['rating_avg'], 1);
        $movies[] = $row;
    }
    $result->free();
} else {
    die("영화 목록 쿼리 오류: " . $conn->error);
}
?>

<main class="movie-list-main-content">
    <div class="movie-list-container container-1200">

        <div class="movie-list-header">
            <h2 class="section-title">현재 상영작 (<?= count($movies) ?>)</h2>
            <div class="sort-options">
                <select name="sort" id="movie-sort">
                    <option value="reserve_rate" <?= ($sort_by == 'reserve_rate' ? 'selected' : '') ?>>예매율순</option>
                    <option value="rating_avg" <?= ($sort_by == 'rating_avg' ? 'selected' : '') ?>>평점순</option>
                    <option value="release_date" <?= ($sort_by == 'release_date' ? 'selected' : '') ?>>개봉일순</option>
                    <option value="wishlist" <?= ($sort_by == 'wishlist' ? 'selected' : '') ?>>보고싶어요</option>
                </select>
            </div>
        </div>

        <div class="movie-grid">
            <?php if (count($movies) > 0): ?>
                <?php foreach ($movies as $movie) : ?>
                    <?php
                        // 연령가 파일명 포맷
                        $age_file = strtolower($movie['age_rating']);
                        // 상세 페이지 링크
                        $detail_link = "movie_detail.php?movie_no=" . $movie['movie_no'];
                    ?>
                    
                    <div class="movie-item-card">
                        <a href="<?= $detail_link ?>" class="poster-link">
                            <img src="images/<?= htmlspecialchars($movie['poster']) ?>" 
                                 alt="<?= htmlspecialchars($movie['title']) ?> 포스터" 
                                 class="movie-poster-img">
                            
                            <div class="card-overlay">
                                <p>⭐ 평점: <?= $movie['rating_avg'] ?></p>
                                <p>예매율: <?= number_format($movie['reserve_rate'], 1) ?>%</p>
                            </div>
                        </a>
                        
                        <div class="movie-item-info">
                            <div class="title-group">
                                <img src="images/ic_age_<?= $age_file ?>.png" 
                                     alt="<?= htmlspecialchars($movie['age_rating']) ?>" 
                                     class="age-icon-small">
                                <span class="movie-title-short"><?= htmlspecialchars($movie['title']) ?></span>
                            </div>
                            <p class="movie-runtime"><?= $movie['running_time'] ?>분</p>
                            
                            <a href="booking.php?movie_no=<?= $movie['movie_no'] ?>" class="btn-book-small">예매</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-movie-msg">현재 상영 중인 영화가 없습니다.</p>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<script>
    document.getElementById('movie-sort').addEventListener('change', function() {
        const selectedSort = this.value;
        
        // 현재 URL의 파라미터를 가져옵니다.
        const url = new URL(window.location.href);
        
        // 'sort' 파라미터를 새로 선택된 값으로 설정합니다.
        url.searchParams.set('sort', selectedSort);
        
        // 페이지를 새로고침하여 PHP가 새 정렬 기준을 사용하도록 합니다.
        window.location.href = url.toString();
    });
</script>

<?php include_once('footer.php'); ?>