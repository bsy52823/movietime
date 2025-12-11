-- 데이터 베이스 생성 및 사용
DROP DATABASE IF EXISTS movietime;
CREATE DATABASE movietime CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE movietime;

-- 1. users (회원 정보) 테이블
CREATE TABLE users (
    user_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '사용자 고유 번호 (PK)',
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '사용자 표시 이름',
    user_id VARCHAR(30) NOT NULL UNIQUE COMMENT '로그인 아이디',
    password VARCHAR(255) NOT NULL COMMENT '비밀번호 (해시 저장)',
    email VARCHAR(100) UNIQUE COMMENT '이메일 주소',
    register_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '가입일'
) ENGINE=InnoDB COMMENT '회원 정보 테이블';

-- 2. movies (영화 정보) 테이블
CREATE TABLE movies (
    movie_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '영화 고유 번호 (PK)',
    title VARCHAR(255) NOT NULL COMMENT '영화 제목',
    poster VARCHAR(255) COMMENT '포스터 이미지 파일명',
    release_date DATE COMMENT '개봉일',
    running_time INT NOT NULL COMMENT '영화 러닝타임 (분 단위)',
    age_rating VARCHAR(10) DEFAULT 'all' COMMENT '연령 등급 (all, 12, 15, 19)',
    reserve_rate DECIMAL(4, 1) DEFAULT 0.0 COMMENT '임시 예매율 (%)',
    synopsis TEXT COMMENT '영화 줄거리',
    trailer_url VARCHAR(255) COMMENT '예고편 유튜브 URL'
) ENGINE=InnoDB COMMENT '영화 정보 테이블';

-- 3. regions (지역 정보) 테이블
CREATE TABLE regions (
    region_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '지역 고유 번호 (PK)',
    name VARCHAR(50) NOT NULL UNIQUE COMMENT '지역 이름 (예: 서울, 경기/인천)'
) ENGINE=InnoDB COMMENT '지역 정보 테이블';

-- 4. cinemas (영화관 지점 정보) 테이블
CREATE TABLE cinemas (
    cinema_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '영화관 지점 고유 번호 (PK)',
    region_no INT NOT NULL COMMENT '지역 ID (FK)',
    name VARCHAR(50) NOT NULL COMMENT '영화관 지점 이름 (예: 강남, 수원)',
    
    UNIQUE KEY cinema_unique (region_no, name),
    FOREIGN KEY (region_no) REFERENCES regions(region_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '영화관 지점 정보 테이블';

-- 5. theaters (상영관 정보) 테이블
CREATE TABLE theaters (
    theater_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '상영관 고유 번호 (PK)',
    cinema_no INT NOT NULL COMMENT '영화관 지점 ID (FK)',
    name VARCHAR(50) NOT NULL COMMENT '상영관 이름 (예: 1관, 2관)',
    total_seats INT NOT NULL DEFAULT 50 COMMENT '총 좌석 수',
    
    UNIQUE KEY theater_unique (cinema_no, name),
    FOREIGN KEY (cinema_no) REFERENCES cinemas(cinema_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '상영관 정보 테이블';

-- 6. showtimes (상영 시간표) 테이블
CREATE TABLE showtimes (
    showtime_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '상영 시간표 고유 번호 (PK)',
    movie_no INT NOT NULL COMMENT '상영 영화 ID',
    theater_no INT NOT NULL COMMENT '상영관 ID',
    start_time DATETIME NOT NULL COMMENT '상영 시작 시간',

    FOREIGN KEY (movie_no) REFERENCES movies(movie_no) ON DELETE CASCADE,
    FOREIGN KEY (theater_no) REFERENCES theaters(theater_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '상영 시간표 테이블';

-- 7. bookings (예매 정보) 테이블 수정
CREATE TABLE bookings (
    book_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '예매 고유 번호 (PK)',
    user_no INT NOT NULL COMMENT '예매 사용자 ID',
    showtime_no INT NOT NULL COMMENT '상영 시간표 ID',
    -- seats VARCHAR(50) NOT NULL COMMENT '예매 좌석 정보', -- **이 컬럼을 삭제해야 합니다.**
    total_price INT NOT NULL COMMENT '총 예매 가격',
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '예매 시각',

    FOREIGN KEY (user_no) REFERENCES users(user_no) ON DELETE CASCADE,
    FOREIGN KEY (showtime_no) REFERENCES showtimes(showtime_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '영화 예매 정보 테이블';

-- 7-1. booked_seats (예매 좌석 상세 정보) 테이블
CREATE TABLE booked_seats (
    book_seat_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '예매 좌석 고유 ID',
    book_no INT NOT NULL COMMENT '예매 ID (FK)',
    seat_code VARCHAR(10) NOT NULL COMMENT '좌석 코드 (예: A5, B1)',
    ticket_type VARCHAR(20) NOT NULL COMMENT '티켓 종류 (adult, teen, senior, disabled)',
    price INT NOT NULL COMMENT '좌석 가격',
    
    UNIQUE KEY booking_seat_unique (book_no, seat_code),
    
    FOREIGN KEY (book_no) REFERENCES bookings(book_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '예매 좌석 상세 정보 테이블';

-- 8. likes (좋아요 정보) 테이블
CREATE TABLE likes (
    like_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '좋아요 고유 ID',
    user_no INT NOT NULL COMMENT '사용자 ID (FK)',
    movie_no INT NOT NULL COMMENT '영화 ID (FK)',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '좋아요 생성 시간',

    UNIQUE KEY user_movie_unique (user_no, movie_no),

    FOREIGN KEY (user_no) REFERENCES users(user_no) ON DELETE CASCADE,
    FOREIGN KEY (movie_no) REFERENCES movies(movie_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '영화 좋아요 정보 테이블';

-- 9. reviews (평점 및 리뷰) 테이블
CREATE TABLE reviews (
    review_no INT AUTO_INCREMENT PRIMARY KEY COMMENT '리뷰 고유 ID',
    user_no INT NOT NULL COMMENT '작성 사용자 ID (FK)',
    movie_no INT NOT NULL COMMENT '영화 ID (FK)',
    rating TINYINT NOT NULL COMMENT '평점 (1점~5점)',
    content TEXT COMMENT '리뷰 내용',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '작성 시각', 

    UNIQUE KEY user_movie_unique (user_no, movie_no),

    FOREIGN KEY (user_no) REFERENCES users(user_no) ON DELETE CASCADE,
    FOREIGN KEY (movie_no) REFERENCES movies(movie_no) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT '평점 및 리뷰 테이블';

-- 샘플 데이터 삽입

INSERT INTO users (username, user_id, password, email) VALUES 
('테스트사용자', 'testuser', 'testpassword', 'test@example.com');
UPDATE users SET password = '$2y$10$Ew.VvWjQ4oZp.5q1v.g6JOpY.T8yR2u/xM7kO6z9j7H0d3L0m5g3o' WHERE user_id = 'testuser';

-- regions (지역) 샘플 데이터 삽입
INSERT INTO regions (name) VALUES
('서울'), ('경기/인천'), ('충청/대전'); 

-- cinemas (영화관 지점) 샘플 데이터 삽입 (하드코딩된 $locations 데이터)
INSERT INTO cinemas (region_no, name) VALUES
(1, '강남'), (1, '노원'), (1, '건대입구'), (1, '김포공항'),
(2, '수원'), (2, '인천'), (2, '판교'),
(3, '대전'), (3, '청주');

-- theaters (상영관) 샘플 데이터 삽입
-- 강남 (cinema_no=1)
INSERT INTO theaters (cinema_no, name, total_seats) VALUES 
(1, '1관', 115),
(1, '2관', 135),
(1, '3관', 185);
-- 수원 (cinema_no=5)
INSERT INTO theaters (cinema_no, name, total_seats) VALUES
(5, '4관', 155),
(5, '5관', 110);

-- movies 테이블에 8개 영화 데이터 삽입
INSERT INTO movies (title, poster, release_date, running_time, age_rating, reserve_rate, synopsis, trailer_url) VALUES
(
    '주토피아 2', 
    'movie1.jpg',
    '2025-11-26', 
    108, 
    'all', 
    29.6,
    '더 화려해진 세계, 더 넓어진 주토피아!...\n주토피아가 다시 흔들리기 시작했다!', 
    'https://www.youtube.com/watch?v=LyMbgsFGQ6I'
),
(
    '아바타: 불과 재', 
    'movie2.jpg', 
    '2025-12-17', 
    197, 
    '12', 
    45.6,
    '월드 와이드 흥행 불멸의 1위 <아바타> 시리즈의 세 번째 이야기!...', 
    'https://www.youtube.com/watch?v=11jbQ6FMI4c'
),
(
    '위키드: 포 굿', 
    'movie3.jpg', 
    '2025-11-19', 
    137, 
    'all', 
    1.3,
    '영화 <위키드: 포 굿>은\n사람들의 시선이 더는 두렵지 않은 사악한 마녀 ‘엘파바’와...', 
    'https://www.youtube.com/watch?v=hjjISKnEK1s'
),
(
    '프레디의 피자가게 2', 
    'movie4.jpg', 
    '2025-12-03', 
    104, 
    '15', 
    0.8,
    '다시 밤이 되었습니다\n마스코트들은 이제 밖으로 나가주세요\n...', 
    'https://www.youtube.com/watch?v=mCDF_C_VO1Q'
),
(
    '샤이닝', 
    'movie5.jpg', 
    '2025-12-10', 
    119, 
    '19', 
    1.5,
    '겨울 동안 호텔을 관리하며 느긋하게 소설을 쓸 수 있는 기회를 잡은 ‘잭’은\n...', 
    'https://www.youtube.com/watch?v=k7gAdQVTP9o'
),
(
    '나우 유 씨 미 3', 
    'movie6.jpg', 
    '2025-11-12', 
    110, 
    '12',
    0.4,
    '나쁜 놈들 잡는 마술사기단 호스맨이\n더러운 돈의 출처인 하트 다이아몬드를\n...', 
    'https://www.youtube.com/watch?v=xPAZZrURdos'
),
(
    '스위트캐슬 대모험', 
    'movie7.jpg', 
    '2025-12-11', 
    67, 
    'all',
    2.1,
    '\"이번 크리스마스가 위험해!\"\n\n마녀 ''버니''의 마법으로 인형이 되어버린 산타 할아버지!...', 
    'https://www.youtube.com/watch?v=m7g42TRbP-k'
),
(
    '파과: 인터내셔널 컷', 
    'movie8.jpg', 
    '2025-12-10', 
    125, 
    '19',
    0.2,
    '지킬 게 생긴 킬러 VS 잃을 게 없는 킬러\n40여 년간 감정 없이 바퀴벌레 같은 인간들을 방역해온\n60대 킬러 ‘조각’(이혜영).', 
    'https://www.youtube.com/watch?v=en72qvufSIE'
);

-- showtimes (상영 시간표) 샘플 데이터 삽입
-- 강남 (cinema_no=1)의 1, 2, 3관 (theater_no=1, 2, 3) 사용
-- 아바타: 불과 재 (movie_no=2)
INSERT INTO showtimes (movie_no, theater_no, start_time) VALUES
(2, 1, '2025-12-12 10:00:00'), -- 강남 1관 
(2, 2, '2025-12-12 14:00:00'), -- 강남 2관
(2, 3, '2025-12-12 18:00:00'), -- 강남 3관
(2, 1, '2025-12-12 22:00:00'), -- 강남 1관
(2, 2, '2025-12-13 11:30:00'), -- 강남 2관
(2, 3, '2025-12-13 16:30:00'); -- 강남 3관

-- 위키드: 포 굿 (movie_no=35)
INSERT INTO showtimes (movie_no, theater_no, start_time) VALUES
(3, 1, '2025-12-12 13:30:00'), -- 강남 1관
(3, 3, '2025-12-12 21:30:00'), -- 강남 3관
(3, 4, '2025-12-12 19:00:00'), -- 수원 4관
(3, 1, '2025-12-12 16:00:00'), -- 강남 1관
(3, 2, '2025-12-12 11:00:00'), -- 강남 2관
(3, 5, '2025-12-13 11:00:00'); -- 수원 5관

-- likes (좋아요) 샘플 데이터 삽입
INSERT INTO likes (user_no, movie_no) VALUES
(1, 1), 
(1, 2); 

-- reviews (리뷰) 샘플 데이터 삽입
INSERT INTO reviews (user_no, movie_no, rating, content) VALUES
(1, 1, 5, '기대한 만큼 재미있었어요! 닉과 주디의 케미가 최고입니다.'),
(1, 2, 4, '영상미는 압권이지만 러닝타임이 너무 길어요.'),
(1, 3, 4, '화려한 비주얼과 음악이 좋았지만, 이야기가 좀 늘어지는 느낌.'),
(1, 4, 3, '전편보다 공포감이 덜했어요. 캐릭터 디자인은 흥미로움.'),
(1, 5, 2, '고전이라 기대했는데, 제 취향은 아니었습니다.'),
(1, 6, 5, '역시 호스맨! 시원한 마술쇼와 반전이 최고입니다.'),
(1, 7, 4, '어린이 영화로 딱 좋아요. 아기자기하고 귀엽습니다.'),
(1, 8, 4, '주연 배우의 연기가 돋보이는 작품. 묵직한 분위기가 인상적입니다.');