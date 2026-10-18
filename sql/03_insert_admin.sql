-- Insert admin user with hashed password (admin123!)
-- Password hash generated using PHP's password_hash('admin123!', PASSWORD_DEFAULT)
-- Plaintext password: admin123!
insert into user (
        user_login,
        user_password,
        user_mail,
        user_compte_id
    )
values (
        'admin',
        '$2y$10$d3aAFiVC47y3HJpMCSqtr.ArkeLTbSoKpitRz/qiS2WsXbpT5mlk6',
        'admin@admin.com',
        1
    );
-- admin password: admin123!
