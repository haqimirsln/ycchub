-- YCC Hub database schema (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4)
-- Generated from the Laravel migrations in database/migrations.
-- Use this ONLY if you are not using the web installer (/install).
-- Import in phpMyAdmin: select your database > Import > choose this file.

SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;

create table `users` (`id` bigint unsigned not null auto_increment primary key, `matric_no` varchar(20) not null, `full_name` varchar(150) not null, `email` varchar(150) not null, `phone_number` varchar(20) not null, `faculty` varchar(100) null, `programme` varchar(150) null, `year_of_study` tinyint unsigned null, `password` varchar(255) not null, `role` enum('member', 'exco', 'admin') not null default 'member', `account_status` enum('pending', 'approved', 'rejected', 'suspended') not null default 'pending', `reviewed_by` bigint unsigned null, `reviewed_at` timestamp null, `review_note` varchar(255) null, `pdpa_consent_at` timestamp null, `activity_status` enum('new', 'active', 'at_risk', 'inactive') not null default 'new', `last_attended_on` date null, `total_attended` smallint unsigned not null default '0', `activity_checked_at` timestamp null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `users` add constraint `users_reviewed_by_foreign` foreign key (`reviewed_by`) references `users` (`id`) on delete set null;
alter table `users` add index `users_role_account_status_activity_status_index`(`role`, `account_status`, `activity_status`);
alter table `users` add unique `users_matric_no_unique`(`matric_no`);
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `cache` add index `cache_expiration_index`(`expiration`);
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`);
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` smallint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` varchar(255) not null, `queue` varchar(255) not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `failed_jobs` add index `failed_jobs_connection_queue_failed_at_index`(`connection`, `queue`, `failed_at`);
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);
create table `programs` (`id` bigint unsigned not null auto_increment primary key, `title` varchar(150) not null, `description` text not null, `category` varchar(40) not null default 'other', `venue` varchar(150) not null, `starts_at` datetime not null, `ends_at` datetime null, `registration_closes_at` datetime null, `max_capacity` int unsigned null, `poster_path` varchar(255) null, `status` enum('draft', 'published', 'cancelled') not null default 'published', `counts_for_activity` tinyint(1) not null default '1', `checkin_code` varchar(32) not null, `checkin_open` tinyint(1) not null default '0', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null, `deleted_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `programs` add constraint `programs_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `programs` add index `programs_status_starts_at_index`(`status`, `starts_at`);
alter table `programs` add unique `programs_checkin_code_unique`(`checkin_code`);
create table `program_registrations` (`id` bigint unsigned not null auto_increment primary key, `program_id` bigint unsigned not null, `user_id` bigint unsigned not null, `status` enum('registered', 'cancelled', 'attended', 'absent') not null default 'registered', `registered_at` timestamp not null default CURRENT_TIMESTAMP, `cancelled_at` timestamp null, `checked_in_at` timestamp null, `checked_in_by` bigint unsigned null, `checkin_method` enum('manual', 'qr') null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `program_registrations` add constraint `program_registrations_program_id_foreign` foreign key (`program_id`) references `programs` (`id`) on delete cascade;
alter table `program_registrations` add constraint `program_registrations_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `program_registrations` add constraint `program_registrations_checked_in_by_foreign` foreign key (`checked_in_by`) references `users` (`id`) on delete set null;
alter table `program_registrations` add unique `program_registrations_program_id_user_id_unique`(`program_id`, `user_id`);
alter table `program_registrations` add index `program_registrations_user_id_status_index`(`user_id`, `status`);
create table `settings` (`key` varchar(80) not null, `value` text null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
create table `activity_pauses` (`id` bigint unsigned not null auto_increment primary key, `label` varchar(100) not null, `starts_on` date not null, `ends_on` date not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
create table `instagram_posts` (`id` bigint unsigned not null auto_increment primary key, `source` enum('api', 'manual') not null default 'api', `ig_id` varchar(64) null, `media_type` varchar(20) null, `caption` text null, `permalink` varchar(255) not null, `image_path` varchar(255) null, `posted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `instagram_posts` add index `instagram_posts_source_posted_at_index`(`source`, `posted_at`);
alter table `instagram_posts` add unique `instagram_posts_ig_id_unique`(`ig_id`);
create table `export_logs` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned null, `export_type` varchar(50) not null, `filters` varchar(255) null, `row_count` int unsigned not null default '0', `ip_address` varchar(45) null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
alter table `export_logs` add constraint `export_logs_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;

-- Tell Laravel these migrations already ran.
create table if not exists `migrations` (`id` int unsigned not null auto_increment primary key, `migration` varchar(255) not null, `batch` int not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000000_create_users_table', 1);
insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000001_create_cache_table', 1);
insert into `migrations` (`migration`, `batch`) values ('0001_01_01_000002_create_jobs_table', 1);
insert into `migrations` (`migration`, `batch`) values ('2026_10_20_000100_create_programs_table', 1);
insert into `migrations` (`migration`, `batch`) values ('2026_10_20_000200_create_settings_and_feed_tables', 1);

SET FOREIGN_KEY_CHECKS = 1;
