<?php
// ===============================
// DB 연결 및 HEADER
// ===============================
include_once('db_connect.php');
include_once('header.php');

// 로그인 확인
if (!isset($_SESSION['user_no'])) {
    echo "<script>alert('좌석 선택은 로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}
$user_no = $_SESSION['user_no'];

// URL 파라미터에서 showtime_no 가져오기
$showtime_no = $_GET['showtime_no'] ?? null;

if (!$showtime_no || !is_numeric($showtime_no)) {
    echo "<script>alert('잘못된 상영 정보입니다.'); location.href='booking.php';</script>";
    exit;
}

// ===============================
// 상영 정보 조회
// ===============================

// 상영관 크기 및 기본 정보 (A~L, 10열)
$row_count = 12; // A~L
$col_count = 10; // 1~10

$sql = "
    SELECT s.start_time, m.movie_no, m.title AS movie_title, m.age_rating,
           t.name AS theater_name, t.total_seats, m.running_time
    FROM showtimes s
    JOIN movies m ON s.movie_no = m.movie_no
    JOIN theaters t ON s.theater_no = t.theater_no
    WHERE s.showtime_no = " . (int)$showtime_no;

$result = $conn->query($sql);
$showtime_info = $result->fetch_assoc();

if (!$showtime_info) {
    echo "<script>alert('상영 정보를 찾을 수 없습니다.'); location.href='booking.php';</script>";
    exit;
}

$start_ts = strtotime($showtime_info['start_time']);
$week = ['일', '월', '화', '수', '목', '금', '토'];
$weekday = $week[date("w", $start_ts)];

$start_time_format = date("Y.m.d", $start_ts) . " ({$weekday}) " . date("H:i", $start_ts);
$end_time_format = date("H:i", $start_ts + $showtime_info['running_time'] * 60);



// 영화 등급 아이콘 경로 설정 (기존과 동일)
$age_rating = strtolower($showtime_info['age_rating']);
if ($age_rating == '전체') {
    $age_rating_for_file = 'all'; 
} else if ($age_rating == '청불' || $age_rating == '18') {
    $age_rating_for_file = '18'; 
} else {
    $age_rating_for_file = $age_rating;
}
$icon_path = "images/ic_age_{$age_rating_for_file}.png";

// 이미 예약된 좌석 조회
$reserved_seats = [];

$seats_sql = "
    SELECT bs.seat_code
    FROM bookings b
    JOIN booked_seats bs ON b.book_no = bs.book_no
    WHERE b.showtime_no = " . (int)$showtime_no;

$seats_result = $conn->query($seats_sql);

if ($seats_result) {
    while ($seat = $seats_result->fetch_assoc()) {
        $reserved_seats[] = $seat['seat_code'];
    }
}

// 총 좌석 선택 가능 개수
$MAX_SEATS = 8;
?>

<div class="seat-booking-wrapper">
    <div class="booking-main-box">
        
        <div class="info-person-panel">
            
            <div class="vertical-title-area">
                <h2 class="page-title">인원/좌석 선택</h2>
                <div class="max-seat-info-vertical">
                    인원은 최대 <?= $MAX_SEATS ?>명까지 선택 가능합니다.
                </div>
            </div>

            <div class="movie-showtime-info-left">
                <img src="<?= $icon_path ?>" alt="등급" class="info-age-icon">
                <strong><?= htmlspecialchars($showtime_info['movie_title']) ?> </strong> 
                <span class="info-time-detail">
                    <?= date("y.m.d(D)", strtotime($showtime_info['start_time'])) ?> 
                    <?= date("H:i", strtotime($showtime_info['start_time'])) ?> ~ 
                    <?= $end_time_format ?> | 
                    <?= htmlspecialchars($showtime_info['theater_name']) ?>
                </span>
            </div>
            
            <div class="person-counter-wrapper-horizontal" id="person-counter-wrapper">
                <div class="person-counter-item" data-type="adult">
                    <span>성인</span>
                    <button class="minus-btn" disabled>-</button>
                    <span class="count-value">0</span>
                    <button class="plus-btn">+</button>
                </div>
                <div class="person-counter-item" data-type="teen">
                    <span>청소년</span>
                    <button class="minus-btn" disabled>-</button>
                    <span class="count-value">0</span>
                    <button class="plus-btn">+</button>
                </div>
                <div class="person-counter-item" data-type="senior">
                    <span>경로</span>
                    <button class="minus-btn" disabled>-</button>
                    <span class="count-value">0</span>
                    <button class="plus-btn">+</button>
                </div>
                <div class="person-counter-item" data-type="disabled">
                    <span>우대</span>
                    <button class="minus-btn" disabled>-</button>
                    <span class="count-value">0</span>
                    <button class="plus-btn">+</button>
                </div>
            </div>
        </div>

        <div class="seat-selection-area-right">
            
            <div class="screen-indicator">S C R E E N</div>
            
            <p class="select-guide">인원을 선택하세요.</p>

            <div class="seat-grid-wrapper" id="seat-grid-wrapper">
                <?php
                $seat_row_letters = range('A', chr(ord('A') + $row_count - 1));

                echo '<div class="seat-grid">';
                
                foreach ($seat_row_letters as $row) {
                    echo "<div class='seat-row' data-row='{$row}'>";
                    
                    echo "<div class='seat-label'>{$row}</div>";
                    
                    for ($col = 1; $col <= $col_count; $col++) {
                        $seat_id = $row . $col;
                        $is_reserved = in_array($seat_id, $reserved_seats);
                        
                        // 예시 이미지의 좌석 패턴을 10열에 맞게 조정 (간격 조절)
                        $is_empty = false;

                        if ($is_empty) {
                            echo "<div class='seat-item seat-gap'></div>";
                            continue; 
                        }
                        
                        $status_class = $is_reserved ? 'reserved' : 'available';

                        // 좌석 버튼 생성
                        echo "<button type='button' 
                                      class='seat-item {$status_class}' 
                                      data-seat-id='{$seat_id}' 
                                      " . ($is_reserved ? 'disabled' : '') . ">
                                    {$col}
                              </button>";
                    }
                    echo '</div>'; // .seat-row 종료
                }
                
                echo '</div>'; // .seat-grid 종료
                ?>
            </div>
            
            <div class="seat-legend">
                <div class="legend-item"><span class="color-box special-seat"></span> 장애인석/우선석</div>
                <div class="legend-item"><span class="color-box reserved"></span> 예매완료</div>
                <div class="legend-item"><span class="color-box selected"></span> 선택</div>
                <div class="legend-item"><span class="color-box available"></span> 선택가능</div>
            </div>
        </div>

        <div class="booking-summary-bottom">
            <div class="total-price-display">
                총 합계 <strong id="total-price">0원</strong>
            </div>
            <button id="checkout-btn" class="btn-primary" disabled>
                결제하기
            </button>
        </div>
    </div> </div> <?php include_once('footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const personCounters = document.querySelectorAll('.person-counter-item');
    const checkoutBtn = document.getElementById('checkout-btn');
    const totalPriceDisplay = document.getElementById('total-price');
    
    const movieTitle = "<?= htmlspecialchars($showtime_info['movie_title']) ?>";
    const ageRating = "<?= htmlspecialchars($showtime_info['age_rating']) ?>";
    const iconPath = "<?= $icon_path ?>";

    const startDateTime = "<?= $start_time_format ?>"; 
    const endTime = "<?= $end_time_format ?>"; 
    const theaterName = "<?= htmlspecialchars($showtime_info['theater_name']) ?>";
    const movieNo = <?= $showtime_info['movie_no'] ?>;

    const startTimeRaw = "<?= $showtime_info['start_time'] ?>";

    const MAX_SEATS = <?= $MAX_SEATS ?>;
    let totalPersons = 0;
    let personCounts = { adult: 0, teen: 0, senior: 0, disabled: 0 };
    let selectedSeats = [];
    
    // 가격 정보 (성인 14000원, 청소년 12000원, 경로 7000원, 우대 5000원)
    const priceMap = { adult: 14000, teen: 12000, senior: 7000, disabled: 5000 };
    
    // 가격 계산을 위한 인원 순서 큐 
    let personQueue = []; 

    // =========================================================
    // 1. 요약 정보 및 총액 계산 함수 (다른 함수들이 참조하므로 먼저 정의)
    // =========================================================
    function updateSummary() {
        const selectedCount = selectedSeats.length;
        let totalPrice = 0;
        
        // 선택된 인원 수와 좌석 수가 정확히 일치할 때만 금액 계산 및 활성화
        const canCheckout = totalPersons > 0 && selectedCount === totalPersons;

        if (canCheckout) {
            // personQueue의 첫 번째 인원부터 좌석 가격을 순서대로 적용
            for (let i = 0; i < selectedCount; i++) {
                const personType = personQueue[i];
                if (personType && priceMap[personType]) {
                    totalPrice += priceMap[personType];
                }
            }
        }

        // 금액 표시 업데이트
        totalPriceDisplay.textContent = totalPrice.toLocaleString() + '원';
        
        // 결제 버튼 업데이트
        checkoutBtn.disabled = !canCheckout;
        if (canCheckout) {
            checkoutBtn.textContent = totalPrice.toLocaleString() + '원 결제하기';
        } else if (totalPersons > 0 && selectedCount < totalPersons) {
            checkoutBtn.textContent = '좌석을 모두 선택하세요';
        } else {
            checkoutBtn.textContent = '결제하기';
        }
    }
    
    // =========================================================
    // 2. 인원 카운터 버튼 활성화/비활성화
    // =========================================================
    function updateCounterButtons() {
        personCounters.forEach(counter => {
            const type = counter.dataset.type;
            const minusBtn = counter.querySelector('.minus-btn');
            const plusBtn = counter.querySelector('.plus-btn');

            minusBtn.disabled = personCounts[type] <= 0;
            plusBtn.disabled = totalPersons >= MAX_SEATS;
        });
        
        document.querySelector('.seat-selection-area-right .select-guide').textContent = totalPersons > 0 
            ? `선택된 인원 ${totalPersons}명에 맞춰 좌석을 선택하세요.` 
            : '인원을 선택하세요.';
    }

    // =========================================================
    // 3. 좌석 선택 초기화
    // =========================================================
    function clearSeatSelection(keepSummary = true) {
        selectedSeats = [];
        document.querySelectorAll('.seat-item.selected').forEach(s => s.classList.remove('selected'));
        if (keepSummary) {
            updateSummary();
        } else {
            // 인원 변경으로 인한 초기화 시 금액을 0원으로 즉시 설정
            totalPriceDisplay.textContent = '0원';
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = '결제하기';
        }
    }

    // =========================================================
    // 4. 인원 카운터 업데이트 (이 함수는 이제 updateSummary를 안전하게 호출할 수 있음)
    // =========================================================
    function updatePerson(type, delta) {
        let currentCount = personCounts[type];
        let newTotal = totalPersons + delta;

        if (newTotal > MAX_SEATS || newTotal < 0 || currentCount + delta < 0) {
            return;
        }

        personCounts[type] += delta;
        totalPersons = newTotal;
        
        // personQueue 업데이트
        if (delta > 0) {
            personQueue.push(type); // 인원 추가 시 큐에 타입 추가
        } else {
            // 인원 감소 시 큐에서 해당 타입의 가장 최근 항목 제거
            const index = personQueue.lastIndexOf(type); 
            if (index > -1) {
                personQueue.splice(index, 1);
            }
        }

        const counter = document.querySelector(`.person-counter-item[data-type="${type}"]`);
        counter.querySelector('.count-value').textContent = personCounts[type];
        
        updateCounterButtons();
        
        // 인원수가 변경되면 기존 좌석 선택을 초기화하고 다시 선택하도록 유도
        if (selectedSeats.length !== totalPersons) {
            clearSeatSelection(false); 
        } else {
            updateSummary(); // 인원 변경 후에도 선택된 좌석 수가 동일하다면 합계 업데이트
        }
    }
    
    // =========================================================
    // 5. 이벤트 리스너 연결
    // =========================================================

    // 인원 카운터 이벤트 리스너 연결
    personCounters.forEach(counter => {
        const type = counter.dataset.type;
        // '+', '-' 버튼에 이벤트 리스너를 명확하게 연결
        counter.querySelector('.plus-btn').addEventListener('click', () => updatePerson(type, 1));
        counter.querySelector('.minus-btn').addEventListener('click', () => updatePerson(type, -1));
    });

    // 좌석 클릭 이벤트
    document.querySelectorAll('.seat-item.available').forEach(seat => {
        seat.addEventListener('click', function() {
            if (totalPersons === 0) {
                alert('좌석을 선택하기 전에 먼저 인원을 선택해 주세요.');
                return;
            }

            const seatId = this.dataset.seatId;
            const index = selectedSeats.indexOf(seatId);

            if (this.classList.contains('selected')) {
                // 선택 해제
                this.classList.remove('selected');
                if (index > -1) {
                    selectedSeats.splice(index, 1);
                }
            } else {
                // 선택
                if (selectedSeats.length < totalPersons) {
                    this.classList.add('selected');
                    selectedSeats.push(seatId);
                } else {
                    alert(`선택된 인원(${totalPersons}명) 만큼만 좌석을 선택할 수 있습니다.`);
                }
            }
            updateSummary();
        });
    });

    // === 결제 버튼 이벤트 핸들러 ===
    checkoutBtn.addEventListener('click', function() {
        if (checkoutBtn.disabled) return;
        
        const totalPrice = parseInt(totalPriceDisplay.textContent.replace(/[^\d]/g, ''));
        
        const personSummary = Object.keys(personCounts)
            .filter(type => personCounts[type] > 0)
            .map(type => {
                let label = '';
                if (type === 'adult') label = '성인';
                else if (type === 'teen') label = '청소년';
                else if (type === 'senior') label = '경로';
                else if (type === 'disabled') label = '우대';
                
                return `${label} ${personCounts[type]}명`;
            })
            .join(', ');

        const personCountsJSON = JSON.stringify(personCounts);

        const queryParams = new URLSearchParams({
            showtime_no: <?= (int)$showtime_no ?>,
            seats: selectedSeats.join(','),
            total_price: totalPrice,
            person_counts: personCountsJSON,

            movie_title: movieTitle,
            age_rating: ageRating,

            start_time: startTimeRaw,          // ✔ RAW DATETIME
            start_time_display: startDateTime, // ✔ 화면용 DISPLAY 시간
            end_time: endTime,
            theater_name: theaterName,
            person_summary: personSummary,
            icon_path: iconPath,
            movie_no: movieNo
        });

        location.href = 'booking_complete.php?' + queryParams.toString();
    });


    // 초기 버튼 상태 업데이트
    updateCounterButtons();
});
</script>