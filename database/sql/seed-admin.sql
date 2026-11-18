-- First admin account for YCC Hub (only needed if you did NOT use the web installer).
-- Login: ADMIN01 / ChangeMe123  -> change the password right after your first login (My profile).
-- Edit the name/email/phone below before importing.
INSERT INTO `users`
  (`matric_no`, `full_name`, `email`, `phone_number`, `password`, `role`, `account_status`, `reviewed_at`, `pdpa_consent_at`, `activity_status`, `created_at`, `updated_at`)
VALUES
  ('ADMIN01', 'YCC Admin', 'ycc.admin@example.com', '0130000000', '$2y$12$eClitrMYud0onuJqFPw5/e3ecYc6.uKAvA82tl8FuA2HlwX6Sgw3y', 'admin', 'approved', NOW(), NOW(), 'new', NOW(), NOW());

-- Demo data (sample excos, members, programs with relative dates) is easiest to load
-- through the web installer's "Add demo data" option, or locally with: php artisan db:seed
