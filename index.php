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
// 영화 데이터 조회 (예매율 순)
// ===============================
$sql = "
    SELECT 
        m.movie_no,
        m.title,
        m.poster,
        m.age_rating AS age,
        m.reserve_rate,
        IFNULL(AVG(r.rating), 0) AS rating,
        COUNT(DISTINCT b.user_no) AS audience_count,
        (SELECT COUNT(like_no) FROM likes l WHERE l.movie_no = m.movie_no AND l.user_no = {$current_user_no}) AS is_liked
    FROM 
        movies m
    LEFT JOIN 
        reviews r ON m.movie_no = r.movie_no
    LEFT JOIN 
        showtimes s ON m.movie_no = s.movie_no
    LEFT JOIN 
        bookings b ON s.showtime_no = b.showtime_no
    GROUP BY 
        m.movie_no, m.title, m.poster, m.age_rating, m.reserve_rate
    ORDER BY 
        m.reserve_rate DESC;
";

$movies = [];
$rank = 1;

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['rating'] = number_format($row['rating'], 1);
        $row['reserve_rate'] = number_format($row['reserve_rate'], 1) . '%';
        $row['rank'] = $rank++;
        $row['audience'] = number_format($row['audience_count']) . '명';
        $movies[] = $row;
    }
    $result->free();
} else {
    die("영화 정보 쿼리 오류: " . $conn->error);
}
?>

<!-- ===========================================================
     예매율 순위 영화 목록 (슬라이더)
=========================================================== -->
<section class="movie-slider-section">
    <div class="gradient-overlay"></div>
    <h2 class="section-header">예매율 순위</h2>

    <button class="slider-button slider-prev" onclick="prevSlide()">&#10094;</button>

    <div class="movie-slider-container">
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

    <button class="slider-button slider-next" onclick="nextSlide()">&#10095;</button>
</section>



<!-- ===========================================================
     메인 콘텐츠 (검색 / 이벤트 / 배너 / 고객센터)
=========================================================== -->
<main class="main-content-white container-1000">

    <!-- 검색 -->
    <section class="search-section">
        <h2>무엇을 찾고 있나요?</h2>

        <form action="search.php" method="GET" class="search-form">
            <input type="text" name="query" placeholder="영화 제목을 검색해보세요." class="search-input-large">
            <button type="submit" class="btn-search-icon">
                <img src="images/ic_search.png" alt="검색 아이콘">
            </button>
        </form>
    </section>

    <!-- 이벤트 -->
    <section class="event-section">
        <div class="section-header"> 
            <h2 class="section-title">이벤트</h2>
            <a href="event.php" class="btn-more">더보기 &gt;</a> 
        </div>
        <div class="event-image-container">
            <div class="event-box">
                <img src="images/event1.jpg" class="event-img">
            </div>
            <div class="event-box">
                <img src="images/event2.jpg" class="event-img">
            </div>
            <div class="event-box">
                <img src="images/event3.jpg" class="event-img">
            </div>
        </div>
    </section>

    <!-- 광고 -->
    <section class="ad-banner-section">
        <img src="images/ad_banner.jpg" class="ad-banner-img">
    </section>

    <!-- 고객센터 -->
    <section class="cs-section">
        <div class="cs-links">
            <div class="cs-item">
                <img src="images/ic_cs.png">
                <span>고객센터</span>
            </div>
            <div class="cs-item">
                <img src="images/ic_qna.png">
                <span>자주 묻는 질문</span>
            </div>
            <div class="cs-item">
                <img src="images/ic_ask.png">
                <span>1:1 문의</span>
            </div>
            <div class="cs-item">
                <img src="images/ic_group.png">
                <span>단체/대관문의</span>
            </div>
        </div>
    </section>

</main>

<script>
    const sliderContainer = document.querySelector('.movie-slider-container');
    const scrollAmount = 240; 
    const CURRENT_USER_NO = <?= $current_user_no ?>;

    function nextSlide() {
        if (sliderContainer) {
            sliderContainer.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        }
    }

    function prevSlide() {
        if (sliderContainer) {
            sliderContainer.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        }
    }

    // index.php 하단 <script> 태그에 추가
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (CURRENT_USER_NO <= 0) {
                alert('로그인이 필요한 기능입니다.');
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return; // 로그인하지 않았으면 여기서 함수 종료
            }
            
            const movieNo = this.dataset.movieNo;
            let isLiked = this.dataset.liked === '1';
            const img = this.querySelector('img');

            // 서버로 좋아요/취소 요청
            fetch('toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `movie_no=${movieNo}&action=${isLiked ? 'unlike' : 'like'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 이미지 토글
                    isLiked = !isLiked;
                    this.dataset.liked = isLiked ? '1' : '0';
                    img.src = isLiked ? 'images/ic_heart_filled.png' : 'images/ic_heart_unfilled.png';
                    
                    // (선택 사항: 좋아요 카운트 업데이트 로직 추가 가능)

                } else {
                    alert(data.message || '로그인이 필요합니다.');
                    if (data.requires_login) {
                        window.location.href = 'login.php';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('좋아요 처리 중 오류가 발생했습니다.');
            });
        });
    });

</script>

<!-- ===============================
     FOOTER
=============================== -->
<?php include_once('footer.php'); ?>
