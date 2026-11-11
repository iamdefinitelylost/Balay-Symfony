-- Run ONCE on your existing balay database (phpMyAdmin: select "balay" > Import).
-- Adds: map pins, guest reviews of stays, favorites, password reset, and host reviews of guests.
-- Run this BEFORE copying the new code, because the code expects these columns and tables.
USE balay;

ALTER TABLE listings
  ADD COLUMN latitude DECIMAL(9,6) NULL,
  ADD COLUMN longitude DECIMAL(9,6) NULL;

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_review_per_guest (listing_id, user_id),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS favorites (
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- The owner of a stay reviews the guest who stayed there (one review per booking).
CREATE TABLE IF NOT EXISTS guest_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  listing_id INT NOT NULL,
  guest_id INT NOT NULL,
  reviewer_id INT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_review_per_booking (booking_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- approximate map pins for the sample stays (edit any stay in Records to change its pin)
UPDATE listings SET latitude=9.1905, longitude=123.264 WHERE title='Bamboo beach house';
UPDATE listings SET latitude=9.306, longitude=123.3055 WHERE title='Garden room near Rizal Blvd';
UPDATE listings SET latitude=9.279, longitude=123.209 WHERE title='Hillside cabin with valley view';
UPDATE listings SET latitude=9.199, longitude=123.511 WHERE title='Island villa with pool';
UPDATE listings SET latitude=9.249, longitude=123.293 WHERE title='Heritage house studio';
UPDATE listings SET latitude=9.286, longitude=123.215 WHERE title='Treehouse by the river';
UPDATE listings SET latitude=9.3085, longitude=123.3085 WHERE title='Family apartment, city centre';
UPDATE listings SET latitude=9.195, longitude=123.2605 WHERE title='Dive lodge by the reef';

-- OPTIONAL: the sample stays show made-up ratings (e.g. 4.9 from 128 reviews). The first real review
-- replaces them with the real average. To start every stay from zero instead, run:
-- UPDATE listings SET rating=0, reviews=0;
