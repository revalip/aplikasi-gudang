-- Creates (or resets) the superadmin account. Run it in the WMS database (phpMyAdmin / aaPanel -> Database -> SQL).
-- Login email : superadmin@mjg.com
-- Password    : the temporary password you were given. CHANGE IT after the first login (User Management -> Users -> Edit).
-- Safe to run again: if the email already exists it is promoted to superadmin and its password is reset.
-- Needs the "users" table to exist (php artisan migrate). Other users and all data are untouched.

INSERT INTO users (name, email, password, role, permissions, created_at, updated_at)
VALUES ('Superadmin', 'superadmin@mjg.com', '$2y$12$FxlKskV5Ko40c/iS.E1/jOeqKoraCBoG4M8zWzc4/VwJfQA1dCnqi', 'superadmin', '[]', NOW(), NOW())
ON DUPLICATE KEY UPDATE role = 'superadmin', permissions = '[]', password = VALUES(password), updated_at = NOW();
