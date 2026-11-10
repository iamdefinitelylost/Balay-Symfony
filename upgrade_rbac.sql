-- Run ONCE on an existing balay database (phpMyAdmin: select "balay" > Import).
-- Adds the staff role, account status (disable), record ownership, and a sample staff account.
USE balay;

ALTER TABLE users
  MODIFY role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active';

ALTER TABLE listings
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ADD CONSTRAINT fk_listings_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- existing stays now belong to the first admin account
UPDATE listings
  SET created_by = (SELECT id FROM (SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1) first_admin)
  WHERE created_by IS NULL;

-- sample staff login: staff@balay.test / Staff123!   (change or delete it later)
INSERT INTO users (name,email,password_hash,role)
  VALUES ('Sample Staff','staff@balay.test','$2y$10$K/k/IC4PUqZpXNL0KnSkZeX934pO4bk5aXXmWwVJTznuuellnaoQe','staff');
