USE `kefu`;

INSERT INTO `admin_users` (`username`, `password_hash`, `nickname`) VALUES
('hxzc33', MD5('123456'), '系统管理员')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `nickname` = VALUES(`nickname`);

INSERT INTO `system_configs` (`config_key`, `config_value`) VALUES
('site_name', '33客服系统'),
('base_url', 'https://your-domain.com'),
('login_page', 'login.php')
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);

INSERT INTO `api_links` (`name`, `api_url`, `status`) VALUES
('默认接口', 'https://example.com/api', 1)
ON DUPLICATE KEY UPDATE `api_url` = VALUES(`api_url`), `status` = VALUES(`status`);
