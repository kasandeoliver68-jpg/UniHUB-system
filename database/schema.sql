CREATE DATABASE IF NOT EXISTS unihub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unihub;

DROP TABLE IF EXISTS mobile_payments;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS rsvps;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS universities;

CREATE TABLE universities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    domain VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    university_id INT NOT NULL,
    listing_key VARCHAR(64) NOT NULL,
    verified_at DATETIME NULL,
    otp_code_hash VARCHAR(255) NULL,
    otp_expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE RESTRICT
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    event_date DATETIME NOT NULL,
    location VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_events_hub_date (university_id, event_date)
);

CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    seller_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    price DECIMAL(12, 2) NOT NULL,
    category VARCHAR(80) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    image_paths JSON NULL,
    status ENUM('available', 'sold') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    CONSTRAINT fk_listings_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_listings_hub_status (university_id, status),
    FULLTEXT KEY ft_listings_search (title, description, category)
);

CREATE TABLE rsvps (
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, event_id),
    CONSTRAINT fk_rsvps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rsvps_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_listing (listing_id, created_at)
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_item (user_id, listing_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE mobile_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(40) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'processed', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO universities (name, domain) VALUES
('Mbarara University of Science and Technology', 'std.must.ac.ug'),
('Makerere University', 'students.mak.ac.ug');

INSERT INTO users (name, email, password_hash, role, university_id, listing_key, verified_at) VALUES
('UniHUB Admin', 'admin@std.must.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCkn9LLQ48NXRHP9.GS.', 'admin', 1, 'ADMIN-MUST-2026', NOW()),
('Campus Seller', 'seller@std.must.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCkn9LLQ48NXRHP9.GS.', 'member', 1, 'SELLER-MUST-2026', NOW());

INSERT INTO events (university_id, created_by, title, description, event_date, location) VALUES
(1, 1, 'Innovation Week Meetup', 'Student demos, startup talks, and networking for builders across campus.', DATE_ADD(NOW(), INTERVAL 6 DAY), 'Main Auditorium'),
(1, 1, 'Career Readiness Clinic', 'CV reviews, internship guidance, and peer mock interviews.', DATE_ADD(NOW(), INTERVAL 12 DAY), 'Library Seminar Room');

INSERT INTO listings (university_id, seller_id, title, description, price, category, quantity, image_paths) VALUES
(1, 2, 'Engineering Drawing Set', 'Clean drawing board, T-square, and compass set for first-year engineering work.', 85000, 'Stationery', 2, JSON_ARRAY()),
(1, 2, 'Used Scientific Calculator', 'Casio calculator in good condition with fresh batteries.', 45000, 'Electronics', 1, JSON_ARRAY());
