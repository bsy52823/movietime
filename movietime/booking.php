<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// 로그인 확인
if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('예매는 로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

// ===============================
// 기준 날짜 설정 (2025년 12월 12일 금요일)
// ===============================
$today = "2025-12-12";
$today_obj = new DateTime($today);

// ===============================
// DB에서 지역 및 영화관 정보 로드
// ===============================

// 1. 지역 목록 및 각 지역별 영화관 목록 로드
$locations_data = [];
$region_sql = "SELECT region_no, name FROM regions ORDER BY region_no ASC";
$region_res = $conn->query($region_sql);

if ($region_res) {
    while ($region = $region_res->fetch_assoc()) {
        $region_name = $region['name'];
        $locations_data[$region_name] = [
            'region_no' => $region['region_no'],
            'theaters' => []
        ];

        // 해당 지역의 영화관 지점 로드
        $cinema_sql = "SELECT name FROM cinemas WHERE region_no = {$region['region_no']} ORDER BY name ASC";
        $cinema_res = $conn->query($cinema_sql);
        if ($cinema_res) {
            while ($cinema = $cinema_res->fetch_assoc()) {
                $locations_data[$region_name]['theaters'][] = $cinema['name'];
            }
        }
    }
}
$locations = $locations_data; // 기존 변수명 유지

// ===============================
// 2. DB에서 영화 목록 불러오기 (예매율 순)
// ===============================
$movie_sql = "SELECT * FROM movies ORDER BY reserve_rate DESC, movie_no ASC"; // 예매율 순으로 변경
$movie_res = $conn->query($movie_sql);

// 3. 날짜 및 상영 시간 데이터 (오늘 날짜부터 7일)
$date_list = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $today_obj)->modify("+$i days");
    $date_list[] = $d->format("Y-m-d");
}
$day_names = ['일','월','화','수','목','금','토'];

// ===============================
// 상영 시간표 조회 함수
// ===============================
function getShowtimes($conn, $movie_no, $date, $cinema_name = null) {
    $movie_no = (int)$movie_no; 
    $date = $conn->real_escape_string($date);
    $cinema_name_safe = $cinema_name ? $conn->real_escape_string($cinema_name) : null;
    
    $start = $date . " 00:00:00";
    $end = $date . " 23:59:59";

    $sql = "
        SELECT s.showtime_no, 
               s.start_time, 
               c.name AS cinema_name, 
               t.name AS theater_name,
               t.total_seats
        FROM showtimes s
        JOIN theaters t ON s.theater_no = t.theater_no
        JOIN cinemas c ON t.cinema_no = c.cinema_no
        WHERE s.movie_no = $movie_no
        AND s.start_time BETWEEN '$start' AND '$end'
    ";
    
    // 영화관 이름으로 필터링 추가
    if ($cinema_name_safe) {
        $sql .= " AND c.name = '$cinema_name_safe'";
    }
    
    $sql .= "
        ORDER BY s.start_time ASC
    ";
    return $conn->query($sql);
}


// 선택 값 처리
$selected_movie = $_GET['movie_no'] ?? $_GET['movie'] ?? null;
$selected_date = $_GET['date'] ?? $today;
$selected_region = $_GET['region'] ?? null;
$selected_theater = $_GET['theater'] ?? null;
$selected_movie_info = null;

if ($selected_movie !== null) {
    $movie_no_safe = (int)$selected_movie;
    $info_sql = "SELECT title, running_time, age_rating FROM movies WHERE movie_no = $movie_no_safe";
    $info_res = $conn->query($info_sql);

    if ($info_res && $info_res->num_rows > 0) {
        $selected_movie_info = $info_res->fetch_assoc();
    }
}
?>

<main class="booking-main-content">
    <div class="booking-layout container-1200">

    <div class="booking-selection-panel">

        <div class="selection-box region-selection">
            <h3 class="selection-title">지역선택</h3>
            <ul class="region-list">
                <?php foreach ($locations as $region => $data): ?>
                    <?php $theater_count = count($data['theaters']); // DB에서 가져온 영화관 수 ?>
                                <li class="region-item <?= ($selected_region == $region ? 'selected':'') ?>" data-region="<?= $region ?>">
                        <?= $region ?>
                        <span class="theater-count">(<?= $theater_count ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

            <div class="selection-box theater-selection">
                <h3 class="selection-title">영화관선택</h3>
                <ul class="theater-list" id="theater-list">
                    <?php
                    if ($selected_region && isset($locations[$selected_region])):
                        // DB에서 로드된 데이터를 사용
                        $theaters = $locations[$selected_region]['theaters'];
                        foreach ($theaters as $theater_name): ?>
                            <li class="theater-item <?= ($selected_theater == $theater_name ? 'selected':'') ?>" data-theater-name="<?= $theater_name ?>">
                                <span><?= $theater_name ?></span>
                            </li>
                        <?php endforeach;
                    else: ?>
                        <li class="no-selection-msg">지역을 선택해 주세요.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="selection-box movie-selection">
    <h3 class="selection-title">영화선택</h3>
    <ul class="movie-list">
        <?php 
        if ($movie_res) $movie_res->data_seek(0);
        while($m = $movie_res->fetch_assoc()): 
            $age_rating = strtolower($m['age_rating']);
            $age_rating_for_file = $age_rating;
            $icon_path = "images/ic_age_{$age_rating_for_file}.png";
        ?>
            <li class="movie-item <?= ($selected_movie == $m['movie_no'] ? 'selected':'') ?>"
                data-movie-no="<?= $m['movie_no'] ?>">
                
                <img src="<?= $icon_path ?>" alt="연령가: <?= htmlspecialchars($m['age_rating']) ?>" class="age-icon">
                
                <?= htmlspecialchars($m['title']) ?>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
            
        </div>
        
        <div class="booking-schedule-panel">
            
            <div class="schedule-calendar-container">
                <button class="calendar-arrow prev-month-btn" disabled>&#10094;</button>
                <div class="date-header">
                    <?= $today_obj->format('Y-m') ?>
                </div>
                <button class="calendar-arrow next-month-btn" disabled>&#10095;</button>
                
                <div class="date-slider-wrapper">
                    <ul class="date-slider" id="date-slider">
                        <?php foreach ($date_list as $index => $date_str): 
                            $date_obj = new DateTime($date_str);
                            $day_of_week = (int)$date_obj->format('w');
                            $is_selected = ($date_str == $selected_date);
                        ?>
                            <li class="date-item <?= $is_selected ? 'selected' : '' ?>" 
                                data-date="<?= $date_str ?>">
                                <span class="date-day <?= $day_names[$day_of_week] === '일' ? 'sunday' : ($day_names[$day_of_week] === '토' ? 'saturday' : '') ?>">
                                    <?= $day_names[$day_of_week] ?>
                                </span>
                                <span class="date-num">
                                    <?= $date_obj->format('d') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="schedule-list-container">
                <h3 class="schedule-list-title" id="schedule-title">상영 시간표</h3>

                <div class="time-list-wrapper" id="time-list-wrapper">
                    <?php if (!$selected_movie): ?>
                        <p class="no-schedule-msg">영화를 먼저 선택해 주세요.</p>
                    <?php else: ?>
                        <?php
                        $times = getShowtimes($conn, $selected_movie, $selected_date, $selected_theater); 
                        
                        if ($times->num_rows == 0):
                        ?>
                            <p class="no-schedule-msg">
                                <?php if ($selected_theater): ?>
                                    선택한 영화관(<?= htmlspecialchars($selected_theater) ?>)에 해당 날짜 상영 정보가 없습니다.
                                <?php else: ?>
                                    해당 날짜에 상영 정보가 없습니다.
                                <?php endif; ?>
                            </p>

                        <?php else:
                            $temp_movie_title = $selected_movie_info ? htmlspecialchars($selected_movie_info['title']) : '선택된 영화';
                            $running_time = $selected_movie_info ? $selected_movie_info['running_time'] : '0';
                        ?>
                            <div class="schedule-block">
                                <div class="schedule-times">
                                    <?php 
                                        $count = 0;
                                        while($t = $times->fetch_assoc()): 
                                            $count++;
                                            $reserved_seats = $count * 5;
                                            $available_seats = $t['total_seats'] - $reserved_seats;
                                            $seat_info = $available_seats . ' / ' . $t['total_seats'];
                                    ?>
                                        <a href="booking_seats.php?showtime_no=<?= $t['showtime_no'] ?>" class="time-button">
                                            <span class="time-value">
                                                <?= date("H:i", strtotime($t['start_time'])) ?>
                                            </span>
                                            <span class="time-seat-info">
                                                <?= $seat_info ?>
                                            </span>
                                            <span class="time-theater-name">
                                                <?= $t['theater_name'] ?>
                                            </span>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // Note: theaterData는 이제 PHP에서 DB로부터 로드된 데이터를 기반으로 합니다.
    const theaterData = <?= json_encode($locations) ?>;
    const regionList = document.querySelector('.region-list');
    const theaterList = document.getElementById('theater-list');
        
    // 헬퍼 함수: 현재 선택된 값을 DOM에서 가져옴
    function getSelectedMovie() {
        const selectedMovieItem = document.querySelector('.movie-item.selected');
        return selectedMovieItem ? selectedMovieItem.dataset.movieNo : null;
    }

    function getSelectedDate() {
        const selectedDateItem = document.querySelector('.date-item.selected');
        return selectedDateItem ? selectedDateItem.dataset.date : <?= json_encode($today) ?>; 
    }

    function getSelectedRegion() {
        // PHP에서 selected 클래스를 적용했으므로, DOM에서 읽어옴
        const selectedRegionItem = document.querySelector('.region-item.selected');
        return selectedRegionItem ? selectedRegionItem.dataset.region : null;
    }

    function getSelectedTheater() {
        // PHP에서 selected 클래스를 적용했으므로, DOM에서 읽어옴
        const selectedTheaterItem = document.querySelector('.theater-item.selected');
        // NOTE: data-theater-name은 이제 영화관 지점 이름('강남')입니다.
        return selectedTheaterItem ? selectedTheaterItem.dataset.theaterName : null;
    }

    // ----------------------------------------------------
    // 핵심 함수: URL을 구성하고 페이지를 리로드합니다.
    // ----------------------------------------------------
    function updateUrl(movieNo, date, region, theater) {
        const params = new URLSearchParams();
        // null이 아닌 값만 URL에 추가
        if (movieNo) params.append('movie', movieNo);
        if (date) params.append('date', date);
        if (region) params.append('region', region); 
        if (theater) params.append('theater', theater); 
        
        window.location.href = 'booking.php?' + params.toString();
    }

    // ----------------------------------------------------
    // [이벤트 리스너]
    // ----------------------------------------------------
    
    // 지역 선택 시: 선택 상태 변경 후 URL 업데이트 (영화관은 초기화)
    regionList.addEventListener('click', function(e) {
        let regionItem = e.target.closest('.region-item');
        if (!regionItem) return;

        // 클라이언트 측에서 selected 클래스 변경 (필수 X, UX 개선)
        document.querySelectorAll('.region-item').forEach(item => item.classList.remove('selected'));
        regionItem.classList.add('selected');
                
        const region = regionItem.dataset.region;
        
        // URL 업데이트 시, 지역을 변경했으니 영화관은 null로 초기화하여 페이지 리로드
        updateUrl(getSelectedMovie(), getSelectedDate(), region, null);
    });

    // 영화관 선택 시: 선택 상태 변경 후 URL 업데이트
    theaterList.addEventListener('click', function(e) {
        let theaterItem = e.target.closest('.theater-item');
        if (!theaterItem) return;

        // 클라이언트 측에서 selected 클래스 변경 (필수 X, UX 개선)
        document.querySelectorAll('.theater-item').forEach(t => t.classList.remove('selected'));
        theaterItem.classList.add('selected');
        
        const theaterName = theaterItem.dataset.theaterName;
        
        // URL 업데이트 시, 현재 선택된 모든 정보를 유지
        updateUrl(getSelectedMovie(), getSelectedDate(), getSelectedRegion(), theaterName);
    });

    // 영화 선택 시: 선택 상태 변경 후 URL 업데이트
    document.querySelector('.movie-list').addEventListener('click', function(e) {
        let movieItem = e.target.closest('.movie-item');
        if (!movieItem) return;

        // 클라이언트 측에서 selected 클래스 변경 (필수 X, UX 개선)
        document.querySelectorAll('.movie-item').forEach(item => item.classList.remove('selected'));
        movieItem.classList.add('selected');

        const movieNo = movieItem.dataset.movieNo;
        
        // URL 업데이트 시, 현재 선택된 모든 정보를 유지
        updateUrl(getSelectedMovie(), getSelectedDate(), getSelectedRegion(), getSelectedTheater());
    });

    // 날짜 선택 시: 선택 상태 변경 후 URL 업데이트
    document.getElementById('date-slider').addEventListener('click', function(e) {
        let dateItem = e.target.closest('.date-item');
        if (!dateItem) return;

        // 클라이언트 측에서 selected 클래스 변경 (필수 X, UX 개선)
        document.querySelectorAll('.date-item').forEach(item => item.classList.remove('selected'));
        dateItem.classList.add('selected');

        const date = dateItem.dataset.date;
    
        // URL 업데이트 시, 현재 선택된 모든 정보를 유지
        updateUrl(getSelectedMovie(), date, getSelectedRegion(), getSelectedTheater());
    });
</script>

<?php include_once('footer.php'); ?>