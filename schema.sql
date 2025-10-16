CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','finance','support','content','customer') NOT NULL DEFAULT 'customer',
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    low_balance_since DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(191) NOT NULL UNIQUE,
    label VARCHAR(150) NULL,
    webhook_url TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    initial_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    description TEXT NULL,
    features TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS package_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    notes TEXT NULL,
    admin_note TEXT NULL,
    form_data TEXT NULL,
    payment_provider VARCHAR(100) NULL,
    payment_reference VARCHAR(150) NULL,
    payment_url TEXT NULL,
    status ENUM('pending','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    name VARCHAR(150) NOT NULL,
    icon VARCHAR(150) NULL,
    image VARCHAR(255) NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(60) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    parent_id INT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'custom',
    reference_key VARCHAR(191) DEFAULT NULL,
    title VARCHAR(191) NOT NULL,
    url VARCHAR(255) DEFAULT NULL,
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    position INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    settings LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_menu (menu_id),
    INDEX idx_parent (parent_id),
    INDEX idx_type (type),
    INDEX idx_reference (reference_key),
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_items_parent FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(150) NOT NULL UNIQUE,
    title VARCHAR(191) NOT NULL,
    content MEDIUMTEXT NULL,
    meta_title VARCHAR(191) NULL,
    meta_description TEXT NULL,
    meta_keywords TEXT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    author_name VARCHAR(150) NULL,
    featured_image VARCHAR(255) NULL,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(255) NULL,
    seo_keywords TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_blog_posts_published (is_published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(150) NULL,
    description TEXT NULL,
    image_url VARCHAR(255) NULL,
    short_description TEXT NULL,
    cost_price_try DECIMAL(12,2) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    views_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    api_token_id INT NULL,
    quantity INT NOT NULL DEFAULT 1,
    note TEXT NULL,
    admin_note TEXT NULL,
    price DECIMAL(12,2) NOT NULL,
    source VARCHAR(50) NULL,
    external_reference VARCHAR(191) NULL,
    external_metadata TEXT NULL,
    status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (api_token_id) REFERENCES api_tokens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    discount_type ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',
    min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_uses INT NULL,
    usage_per_user INT NULL,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_coupons_status (status),
    INDEX idx_coupons_schedule (starts_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupon_usages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    order_reference VARCHAR(150) NOT NULL,
    order_id INT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES product_orders(id) ON DELETE SET NULL,
    INDEX idx_coupon_usages_coupon (coupon_id),
    INDEX idx_coupon_usages_coupon_user (coupon_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    author_name VARCHAR(150) NOT NULL,
    author_email VARCHAR(150) NULL,
    content TEXT NOT NULL,
    rating TINYINT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(150) NOT NULL,
    account_holder VARCHAR(150) NOT NULL,
    iban VARCHAR(34) NOT NULL,
    branch VARCHAR(150) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_transfer_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NULL,
    user_id INT NULL,
    order_reference VARCHAR(150) NULL,
    amount DECIMAL(12,2) NOT NULL,
    transfer_datetime DATETIME NOT NULL,
    receipt_path VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    platform VARCHAR(100) NULL,
    browser VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS balance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS balance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(150) NOT NULL,
    payment_provider VARCHAR(100) NULL,
    payment_reference VARCHAR(150) NULL,
    payment_url TEXT NULL,
    reference VARCHAR(150) NULL,
    notes TEXT NULL,
    admin_note TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    processed_by INT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(150) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(150) NOT NULL,
    target_type VARCHAR(150) NULL,
    target_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_created_at (action, created_at),
    INDEX idx_user_created_at (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    scope ENUM('global','user') NOT NULL DEFAULT 'global',
    user_id INT DEFAULT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    publish_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expire_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scope (scope),
    INDEX idx_status (status),
    INDEX idx_publish_at (publish_at),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_user (notification_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, name, email, password_hash, role, balance, status, created_at)
VALUES (
    1,
    'Muhammet',
    'muhammet@example.com',
    '$2y$12$Yq0ismbkZMYrKL0soxdP1ubWjSVs1V.PjJGtSmQMfJ0PAfcGW1U92',
    'super_admin',
    0,
    'active',
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    status = VALUES(status);

-- ----------------------------------------------------------------------------------
-- Demo catalog seed data (categories, subcategories, and products)
-- ----------------------------------------------------------------------------------

-- Top-level categories
INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Windows', 'windows', '', 'Microsoft Windows license keys and bundles.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Windows');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Office', 'microsoft', '', 'Microsoft Office suites and collaboration tools.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Office');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'PUBG', 'pubg', '', 'PUBG Mobile UC and seasonal upgrades.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'PUBG');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Valorant', 'valorant', '', 'Valorant Point bundles and agent unlocks.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Valorant');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'WordPress', 'wordpress', '', 'WordPress themes, plugins, and hosting bundles.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'WordPress');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Instagram Hesap', 'instagram', '', 'Instagram creator and business accounts.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Instagram Hesap');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Twitter Hesap', 'twitter', '', 'Twitter verified and niche accounts.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Twitter Hesap');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Facebook Hesap', 'facebook', '', 'Facebook aged and business manager accounts.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Facebook Hesap');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'TikTok Hesap', 'tiktok', '', 'TikTok creator accounts with real engagement.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'TikTok Hesap');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Reddit Hesap', 'reddit', '', 'Reddit aged accounts for community management.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Reddit Hesap');

-- Subcategories
INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Windows Essentials', 'windows', '', 'Popular Windows activation products.'
FROM categories parent
WHERE parent.name = 'Windows'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Windows Essentials');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Office Suites', 'microsoft', '', 'Complete Microsoft Office offerings.'
FROM categories parent
WHERE parent.name = 'Office'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Office Suites');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'PUBG Credits', 'pubg', '', 'Direct UC top-ups and season upgrades.'
FROM categories parent
WHERE parent.name = 'PUBG'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'PUBG Credits');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Valorant Points', 'valorant', '', 'Valorant Points for weapon skins and agents.'
FROM categories parent
WHERE parent.name = 'Valorant'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Valorant Points');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'WordPress Solutions', 'wordpress', '', 'Themes, plugins, and managed services.'
FROM categories parent
WHERE parent.name = 'WordPress'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'WordPress Solutions');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Instagram Accounts', 'instagram', '', 'Ready-to-launch Instagram profiles.'
FROM categories parent
WHERE parent.name = 'Instagram Hesap'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Instagram Accounts');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Twitter Accounts', 'twitter', '', 'Active Twitter accounts with followers.'
FROM categories parent
WHERE parent.name = 'Twitter Hesap'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Twitter Accounts');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Facebook Accounts', 'facebook', '', 'Facebook accounts with business access.'
FROM categories parent
WHERE parent.name = 'Facebook Hesap'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Facebook Accounts');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'TikTok Accounts', 'tiktok', '', 'TikTok creator profiles ready for campaigns.'
FROM categories parent
WHERE parent.name = 'TikTok Hesap'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'TikTok Accounts');

INSERT INTO categories (parent_id, name, icon, image, description)
SELECT parent.id, 'Reddit Accounts', 'reddit', '', 'Reddit aged accounts for subreddit growth.'
FROM categories parent
WHERE parent.name = 'Reddit Hesap'
  AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = parent.id AND name = 'Reddit Accounts');

-- Windows products
INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 11 Pro Retail', 'WIN-11-PRO', 'Full retail license with lifetime activation.', 1200.00, 1499.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 11 Pro Retail');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 10 Pro OEM Key', 'WIN-10-PRO', 'OEM key for a single device, instant delivery.', 800.00, 999.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 10 Pro OEM Key');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 11 Home Digital', 'WIN-11-HOME', 'Digital activation for Windows 11 Home edition.', 650.00, 849.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 11 Home Digital');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows Server 2022 Datacenter', 'WIN-SRV-22', 'Datacenter license for enterprise workloads.', 4100.00, 4599.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows Server 2022 Datacenter');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 10 Enterprise LTSC', 'WIN-10-LTSC', 'Long-term servicing channel license.', 1850.00, 2149.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 10 Enterprise LTSC');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 8.1 Pro Legacy Pack', 'WIN-81-LEG', 'Legacy Windows 8.1 Pro keys for maintenance.', 450.00, 599.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 8.1 Pro Legacy Pack');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows Remote Desktop CAL Pack', 'WIN-RDS-CAL', 'Remote Desktop CAL pack for 10 users.', 950.00, 1199.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows Remote Desktop CAL Pack');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 365 Business Starter', 'WIN-365-START', 'Cloud PC subscription starter bundle.', 1100.00, 1399.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 365 Business Starter');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows 11 Education License', 'WIN-11-EDU', 'Discounted Windows license for schools.', 500.00, 699.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows 11 Education License');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Windows Pro Bulk 5-Pack', 'WIN-PRO-5PK', 'Five Windows Pro keys for IT rollout.', 2600.00, 3099.00, 'active'
FROM categories sub
WHERE sub.name = 'Windows Essentials'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Windows Pro Bulk 5-Pack');

-- Office products
INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 365 Family Annual', 'OFF365-FAM', 'Annual subscription for six family members.', 950.00, 1199.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 365 Family Annual');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 2021 Professional Plus', 'OFF21-PRO', 'Perpetual license for Office 2021 Pro Plus.', 1450.00, 1799.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 2021 Professional Plus');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 2019 Home and Student', 'OFF19-HS', 'One-time purchase for home and student use.', 650.00, 849.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 2019 Home and Student');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Visio Professional 2021', 'VISIO21-PRO', 'Diagramming tool for professional teams.', 950.00, 1199.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Visio Professional 2021');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Project Professional 2021', 'PROJECT21-PRO', 'Full project management desktop license.', 1650.00, 1999.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Project Professional 2021');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 365 Business Standard', 'OFF365-BS', 'Cloud Office apps with Teams collaboration.', 980.00, 1249.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 365 Business Standard');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 2016 Professional Plus', 'OFF16-PRO', 'Legacy Office 2016 suite for compatibility.', 720.00, 949.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 2016 Professional Plus');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 365 E3 Tenant', 'OFF365-E3', 'Enterprise-grade Office 365 tenant provisioning.', 2200.00, 2699.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 365 E3 Tenant');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office 365 Business Basic', 'OFF365-BB', 'Email and Office web apps for small teams.', 450.00, 649.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office 365 Business Basic');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'Office LTSC 2021 Enterprise', 'OFF21-LTSC', 'Long-term servicing Office deployment.', 1750.00, 2099.00, 'active'
FROM categories sub
WHERE sub.name = 'Office Suites'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Office LTSC 2021 Enterprise');

-- PUBG products
INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'PUBG Mobile 60 UC', 'PUBG-60UC', 'Instant delivery of 60 Unknown Cash.', 30.00, 49.00, 'active'
FROM categories sub
WHERE sub.name = 'PUBG Credits'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'PUBG Mobile 60 UC');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT sub.id, 'PUBG Mobile 300 UC', 'PUBG-300UC', 'Bundle of 300 Unknown Cash for PUBG Mobile.', 120.00, 169.00, 'active'
FROM categories sub
WHERE sub.name = 'PUBG Credits'
  AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'PUBG Mobile 300 UC');

-- Cart test data
INSERT INTO categories (parent_id, name, icon, image, description)
SELECT NULL, 'Cart Test Category', 'shopping_cart', '', 'Category for validating cart functionality.'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE parent_id IS NULL AND name = 'Cart Test Category');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT cat.id, 'Cart Test Product A', 'CART-TEST-A', 'Simulated product A for cart verification.', 100.00, 150.00, 'active'
FROM categories cat
WHERE cat.name = 'Cart Test Category'
  AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'CART-TEST-A');

INSERT INTO products (category_id, name, sku, description, cost_price_try, price, status)
SELECT cat.id, 'Cart Test Product B', 'CART-TEST-B', 'Simulated product B for cart verification.', 200.00, 260.00, 'active'
FROM categories cat
WHERE cat.name = 'Cart Test Category'
  AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'CART-TEST-B');
