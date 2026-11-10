CREATE DATABASE IF NOT EXISTS balay CHARACTER SET utf8mb4;
USE balay;

DROP TABLE IF EXISTS listing_images;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  city VARCHAR(60) NOT NULL,
  type ENUM('Beach','Mountain','City') NOT NULL,
  price INT NOT NULL,
  max_guests INT NOT NULL,
  rating DECIMAL(2,1) NOT NULL DEFAULT 0,
  reviews INT NOT NULL DEFAULT 0,
  host VARCHAR(60) NOT NULL,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  amenities VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  color1 CHAR(7) NOT NULL,
  color2 CHAR(7) NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE listing_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  filename VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  guest_name VARCHAR(80) NOT NULL,
  guest_email VARCHAR(120) NOT NULL,
  check_in DATE NOT NULL,
  check_out DATE NOT NULL,
  guests INT NOT NULL,
  total INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (name,email,password_hash,role) VALUES
  ('Balay Admin','admin@balay.test','$2y$10$qHSwQWsW7C/0zgMtRvugYONVJT4Rnfu82piqFkWeTeH5mi9ACjAUO','admin'),
  ('Sample Staff','staff@balay.test','$2y$10$K/k/IC4PUqZpXNL0KnSkZeX934pO4bk5aXXmWwVJTznuuellnaoQe','staff');

INSERT INTO listings (title,city,type,price,max_guests,rating,reviews,host,is_new,amenities,description,color1,color2) VALUES
('Bamboo beach house','Dauin','Beach',3200,6,4.9,128,'Maria',1,'Sea view,Kitchen,Wi-Fi,Snorkel gear','Steps from the sand with a shaded deck. Dive shops and cafés are a short walk away.','#1F8A8A','#F2A93B'),
('Garden room near Rizal Blvd','Dumaguete','City',1400,2,4.7,86,'Jun',0,'Air-con,Wi-Fi,Breakfast','Quiet room in a family home, five minutes from the boulevard and the university.','#3E8E5A','#BFE3C6'),
('Hillside cabin with valley view','Valencia','Mountain',2600,4,4.9,64,'Elena',0,'Valley view,Hot shower,Fire pit,Parking','Cool mornings, waterfalls nearby and a deck made for coffee.','#2B5D6B','#9ED0C2'),
('Island villa with pool','Siquijor','Beach',6800,8,4.8,41,'Rico',1,'Pool,Sea view,Kitchen,Air-con','Private villa for groups, with a pool facing the sunset and a caretaker on site.','#F2A93B','#E4572E'),
('Heritage house studio','Bacong','City',1900,3,4.6,52,'Tessa',0,'Wi-Fi,Kitchenette,Air-con','A restored ancestral home studio with capiz windows, near the old church.','#8C5E3C','#F0D9A8'),
('Treehouse by the river','Valencia','Mountain',3900,2,5.0,37,'Paolo',0,'River access,Hammock,Breakfast','Sleep among the branches with the river below. Best for couples.','#1B4D3E','#7FC8A9'),
('Family apartment, city centre','Dumaguete','City',2200,6,4.5,73,'Liza',0,'2 bedrooms,Kitchen,Wi-Fi,Parking','Roomy apartment close to markets, restaurants and the ferry terminal.','#4A6FA5','#C9D8EE'),
('Dive lodge by the reef','Dauin','Beach',2100,4,4.7,95,'Ben',0,'Gear storage,Fan room,Breakfast','Simple, friendly lodge a few steps from the house reef.','#0E6E8C','#8FD6E6');

-- Default admin: admin@balay.test / Admin123!  (change this password after first login)

UPDATE listings SET created_by = (SELECT id FROM users WHERE email='admin@balay.test');
