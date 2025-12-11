<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// 사용자 번호 설정
$current_user_no = $_SESSION['user_no'] ?? 0;

// movie_no가 URL에 있는지 확인
$movie_no = $_GET['movie_no'] ?? null;
if (!$movie_no || !is_numeric($movie_no)) {
    die("<div class='container-1200'><p>유효하지 않은 영화 번호입니다.</p></div>");
}
$movie_no = (int)$movie_no;

// ===============================
// 좋아요 처리 (POST 요청 시)
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    if ($current_user_no <= 0) {
        // 비로그인 사용자에게 좋아요 요청이 들어온 경우 처리
        // 사용자에게 알리고 로그인 페이지로 리디렉션
        echo "<script>alert('로그인이 필요한 기능입니다.'); window.location.href='login.php';</script>";
        exit();
    }
    
    // 좋아요 상태 확인
    $sql_check_like = "SELECT like_no FROM likes WHERE movie_no = ? AND user_no = ?";
    $stmt_check_like = $conn->prepare($sql_check_like);
    $stmt_check_like->bind_param("ii", $movie_no, $current_user_no);
    $stmt_check_like->execute();
    $result_check_like = $stmt_check_like->get_result();

    if ($result_check_like->num_rows > 0) {
        // 이미 좋아요를 눌렀으면 취소 (DELETE)
        $sql_toggle = "DELETE FROM likes WHERE movie_no = ? AND user_no = ?";
    } else {
        // 좋아요를 누르지 않았으면 추가 (INSERT)
        $sql_toggle = "INSERT INTO likes (movie_no, user_no) VALUES (?, ?)";
    }
    $stmt_check_like->close();
    
    $stmt_toggle = $conn->prepare($sql_toggle);
    $stmt_toggle->bind_param("ii", $movie_no, $current_user_no);
    
    if (!$stmt_toggle->execute()) {
        if ($stmt_toggle->errno == 1452) { // 외래 키 오류 코드
            // 세션 정보가 무효하다고 판단, 세션을 초기화하고 로그인을 유도합니다.
            session_unset();
            session_destroy();
            echo "<script>alert('사용자 정보가 유효하지 않아 로그아웃 처리되었습니다. 다시 로그인해 주세요.'); window.location.href='login.php';</script>";
            exit();
        } else {
             // 기타 DB 오류
             $alert_message = "좋아요 처리 중 데이터베이스 오류가 발생했습니다: " . $stmt_toggle->error;
             echo "<script>alert('" . htmlspecialchars($alert_message) . "'); window.history.back();</script>";
             exit();
        }
    }
    $stmt_toggle->close();

    // POST-Redirect-GET 패턴
    header("Location: movie_detail.php?movie_no=" . $movie_no);
    exit();
}


// ===============================
// 리뷰 작성 처리 (POST 요청 시)
// ===============================
$review_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $rating = $_POST['rating'] ?? 0;
    $content = trim($_POST['content'] ?? '');
    
    if ($current_user_no <= 0) {
        $review_error = "리뷰를 작성하려면 먼저 로그인해야 합니다.";
        // 리뷰 폼이 표시되도록 로직은 계속 진행하지만 $review_error를 설정하여 알림
        // 또는 즉시 리디렉션: header("Location: login.php"); exit();
    } elseif ($rating < 1 || $rating > 5) {
        $review_error = "평점은 1점에서 5점 사이여야 합니다.";
    } elseif (empty($content)) {
        $review_error = "리뷰 내용을 입력해주세요.";
    } else {
        // 중복 체크: 이미 해당 영화에 대해 리뷰를 작성했는지 확인
        $sql_check = "SELECT review_no FROM reviews WHERE movie_no = ? AND user_no = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $movie_no, $current_user_no);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $review_error = "이미 이 영화에 대한 리뷰를 작성하셨습니다.";
        } else {
            // 리뷰 삽입
            $sql_insert = "INSERT INTO reviews (movie_no, user_no, rating, content) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iiis", $movie_no, $current_user_no, $rating, $content);
            
            if ($stmt_insert->execute()) {
                // 성공 시 페이지 새로고침 (POST-Redirect-GET 패턴)
                header("Location: movie_detail.php?movie_no=" . $movie_no . "#reviews");
                exit();
            } else {
                $review_error = "리뷰 작성 중 오류가 발생했습니다: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}


// ===============================
// 2. 영화 기본 정보 및 평점/좋아요 수 조회
// ===============================
$sql = "
    SELECT 
        m.*,
        IFNULL(AVG(r.rating), 0) AS rating_avg,
        COUNT(r.review_no) AS review_count,
        COUNT(l.like_no) AS like_count
    FROM 
        movies m
    LEFT JOIN 
        reviews r ON m.movie_no = r.movie_no
    LEFT JOIN 
        likes l ON m.movie_no = l.movie_no
    WHERE 
        m.movie_no = ?
    GROUP BY 
        m.movie_no;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div class='container-1200'><p>해당 영화 정보를 찾을 수 없습니다.</p></div>");
}

$movie = $result->fetch_assoc();
$movie['rating_avg'] = number_format($movie['rating_avg'], 1);
$stmt->close();

// 예고편 URL에서 YouTube 비디오 ID 추출
$trailer_id = '';
if (!empty($movie['trailer_url'])) {
    $parsed_url = parse_url($movie['trailer_url']);
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
        $trailer_id = $query_params['v'] ?? '';
    }
}

// 연령가 파일명 포맷
$age_file = strtolower($movie['age_rating']);


// ===============================
// 3. 현재 사용자의 좋아요/리뷰 상태 확인
// ===============================
$is_liked = false;
$sql_user_like = "SELECT like_no FROM likes WHERE movie_no = ? AND user_no = ?";
$stmt_user_like = $conn->prepare($sql_user_like);
$stmt_user_like->bind_param("ii", $movie_no, $current_user_no);
$stmt_user_like->execute();
if ($stmt_user_like->get_result()->num_rows > 0) {
    $is_liked = true;
}
$stmt_user_like->close();

$has_reviewed = false;
$sql_user_review = "SELECT review_no FROM reviews WHERE movie_no = ? AND user_no = ?";
$stmt_user_review = $conn->prepare($sql_user_review);
$stmt_user_review->bind_param("ii", $movie_no, $current_user_no);
$stmt_user_review->execute();
if ($stmt_user_review->get_result()->num_rows > 0) {
    $has_reviewed = true;
}
$stmt_user_review->close();


// ===============================
// 4. 해당 영화의 리뷰 목록 조회
// ===============================
$reviews = [];
$sql_reviews = "
    SELECT 
        r.rating,
        r.content,
        r.created_at,
        u.username
    FROM 
        reviews r
    JOIN 
        users u ON r.user_no = u.user_no
    WHERE 
        r.movie_no = ?
    ORDER BY 
        r.created_at DESC;
";

$stmt_reviews = $conn->prepare($sql_reviews);
$stmt_reviews->bind_param("i", $movie_no);
$stmt_reviews->execute();
$result_reviews = $stmt_reviews->get_result();

if ($result_reviews) {
    while ($row = $result_reviews->fetch_assoc()) {
        $reviews[] = $row;
    }
    $result_reviews->free();
}
$stmt_reviews->close();
?>

<main class="movie-detail-main-content">
    
    <div class="top-info-background">
        <div class="movie-detail-container container-1200">
            
            <section class="movie-info-section">
                
                <div class="poster-area">
                    <div class="poster-frame">
                        <img src="images/<?= htmlspecialchars($movie['poster']) ?>" 
                             alt="<?= htmlspecialchars($movie['title']) ?> 포스터" 
                             class="movie-poster-large">
                    </div>
                </div>

                <div class="info-area">
                    
                    <div class="title-group-detail">
                        <h1>
                            <?= htmlspecialchars($movie['title']) ?>
                            <img src="images/ic_age_<?= $age_file ?>.png" 
                                 alt="<?= htmlspecialchars($movie['age_rating']) ?>" 
                                 class="age-icon-title"> 
                        </h1>

                        <div class="meta-inline-group compact-meta">
                            <span><?= $movie['release_date'] ?> 개봉</span>
                            <span class="divider">|</span>
                            <span><?= $movie['running_time'] ?>분</span>
                            <span class="divider">|</span>
                            <span>⭐<?= $movie['rating_avg'] ?></span>
                            <span class="divider">|</span>
                            <span>예매율 <?= $movie['reserve_rate'] ?>%</span>
                        </div>
                    </div>

                    <div class="booking-box">
                        <div class="action-buttons-group">
                            <a href="booking.php?movie_no=<?= $movie['movie_no'] ?>" class="btn-detail-book">
                                예매하기
                            </a>
                            <form method="POST" class="like-form">
                                <input type="hidden" name="action" value="toggle_like">
                                <button type="submit" class="btn-toggle-like <?= $is_liked ? 'liked' : '' ?>" title="좋아요">
                                    <img src="images/<?= $is_liked ? 'ic_heart_filled.png' : 'ic_heart_unfilled.png' ?>" 
                                        alt="좋아요" 
                                        class="like-icon-img">
                                    <span><?= $movie['like_count'] ?></span>
                                </button>
                            </form>
                        </div>
                        <p class="movie-summary"><?= nl2br(htmlspecialchars($movie['synopsis'])) ?></p>
                    </div>
                    </div>
                </div>
            </section>
        </div>
    </div> <div class="movie-detail-container container-1200">
        <section class="detail-tabs">
            <nav class="tab-menu">
                <a href="#info" class="tab-item active">상세정보</a>
                <a href="#reviews" class="tab-item">관람평 (<?= number_format($movie['review_count']) ?>)</a>
            </nav>
            
            <div id="info" class="tab-content active">
                <div class="tab-content-section">
                    <h2>영화 정보</h2>
                    <p class="tab-meta-info">
                        장르: 판타지, 뮤지컬, 어드벤처 / 국가: 미국 
                    </p>
                    <p class="tab-meta-info">감독: ??? / 출연: ???</p>
                    
                    <h2 class="trailer-title">트레일러</h2>
                    <?php if ($trailer_id): ?>
                        <div class="video-container">
                            <iframe 
                                src="https://www.youtube.com/embed/<?= htmlspecialchars($trailer_id) ?>?autoplay=0" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    <?php else: ?>
                        <p class="no-trailer">제공되는 예고편이 없습니다.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="reviews" class="tab-content">
                
                <div class="review-form-area">
                    <h3>리뷰 작성하기</h3>
                    <?php if (isset($review_error)): ?>
                        <p class="review-error"><?= htmlspecialchars($review_error) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($has_reviewed): ?>
                        <p class="review-already-written">이미 이 영화에 대한 리뷰를 작성하셨습니다.</p>
                    <?php else: ?>
                        <form action="movie_detail.php?movie_no=<?= $movie_no ?>" method="POST" class="review-form">
                            <input type="hidden" name="action" value="submit_review">
                            
                            <div class="rating-input-group">
                                <label for="rating">별점:</label>
                                <select name="rating" id="rating" required>
                                    <option value="">선택</option>
                                    <option value="5">⭐⭐⭐⭐⭐ (5점)</option>
                                    <option value="4">⭐⭐⭐⭐ (4점)</option>
                                    <option value="3">⭐⭐⭐ (3점)</option>
                                    <option value="2">⭐⭐ (2점)</option>
                                    <option value="1">⭐ (1점)</option>
                                </select>
                            </div>
                            
                            <textarea name="content" rows="5" placeholder="리뷰 내용을 작성해주세요 (최대 500자)" maxlength="500" required></textarea>

                            <div class="button-container">
                                <button type="submit" class="btn-submit-review">리뷰 제출</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="review-list-area">
                    <h3>총 관람평 (<?= count($reviews) ?>건)</h3>
                    <?php if (count($reviews) > 0): ?>
                        <ul class="review-list">
                            <?php foreach ($reviews as $review): ?>
                                <li class="review-item">
                                    <div class="review-header">
                                        <span class="review-rating">
                                            <?= str_repeat('⭐', $review['rating']) ?> 
                                        </span>
                                        <span class="review-meta">
                                            작성자: **<?= htmlspecialchars($review['username']) ?>** | 
                                            작성일: <?= date('Y.m.d H:i', strtotime($review['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="review-content"><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-reviews">아직 작성된 리뷰가 없습니다. 첫 리뷰를 남겨주세요!</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>
</main>

<script>
// 탭 메뉴 활성화 JavaScript
document.querySelectorAll('.tab-menu .tab-item').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // 모든 탭과 콘텐츠의 active 클래스 제거
        document.querySelectorAll('.tab-menu .tab-item').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // 클릭한 탭을 active로 설정
        this.classList.add('active');
        
        // 해당 콘텐츠를 active로 설정
        const targetId = this.getAttribute('href');
        document.querySelector(targetId).classList.add('active');
        
        // URL 해시 변경 (뒤로가기/앞으로가기 가능하게)
        window.history.pushState(null, null, targetId);
    });

    // 페이지 로드 시 URL 해시에 따라 탭 활성화 (예: #reviews)
    if (window.location.hash) {
        const initialTab = document.querySelector(`.tab-menu a[href="${window.location.hash}"]`);
        if (initialTab) {
            // 기본 active 클래스 제거 후, 선택된 해시 탭 활성화
            const activeTab = document.querySelector('.tab-menu .tab-item.active');
            if(activeTab) activeTab.classList.remove('active');
            
            const activeContent = document.querySelector('.tab-content.active');
            if(activeContent) activeContent.classList.remove('active');
            
            initialTab.classList.add('active');
            document.querySelector(window.location.hash).classList.add('active');
        }
    }
});
</script>

<?php include_once('footer.php'); ?>