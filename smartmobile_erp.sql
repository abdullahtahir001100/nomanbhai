-- ==========================================================
-- Smart Mobile ERP - Clean Production Database Schema
-- Database Name: `smartmobile_erp`
-- Charset: utf8mb4_unicode_ci
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for `categories`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'cashier',
  `phone` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `products`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `category_id` INT DEFAULT NULL,
  `category_name` VARCHAR(100) DEFAULT 'General',
  `cost_price` DECIMAL(12,2) DEFAULT 0.00,
  `wholesale_price` DECIMAL(12,2) DEFAULT 0.00,
  `retail_price` DECIMAL(12,2) DEFAULT 0.00,
  `image` VARCHAR(255) DEFAULT NULL,
  `stock_qty` INT DEFAULT 0,
  `alert_qty` INT DEFAULT 10,
  `high_stock_qty` INT DEFAULT 50,
  `status` ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`sku`),
  INDEX (`category_name`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `clients` (Wholesale Customers & Khata)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_name` VARCHAR(150) NOT NULL,
  `owner_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(50) NOT NULL UNIQUE,
  `city_area` VARCHAR(150) DEFAULT NULL,
  `credit_limit` DECIMAL(12,2) DEFAULT 100000.00,
  `current_balance` DECIMAL(12,2) DEFAULT 0.00,
  `secret_pin` VARCHAR(50) DEFAULT '1234',
  `status` VARCHAR(50) DEFAULT 'clear',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`phone`),
  INDEX (`shop_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `client_ledgers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `transaction_type` ENUM('sale', 'payment', 'return', 'adjustment') NOT NULL,
  `debit` DECIMAL(12,2) DEFAULT 0.00,
  `credit` DECIMAL(12,2) DEFAULT 0.00,
  `balance_after` DECIMAL(12,2) DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `reference_no` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`client_id`),
  CONSTRAINT `fk_client_ledgers_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `vendors` (Suppliers)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `company` VARCHAR(150) DEFAULT NULL,
  `balance` DECIMAL(12,2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `vendor_ledgers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vendor_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `transaction_type` ENUM('purchase', 'payment', 'return', 'adjustment') NOT NULL,
  `debit` DECIMAL(12,2) DEFAULT 0.00,
  `credit` DECIMAL(12,2) DEFAULT 0.00,
  `balance_after` DECIMAL(12,2) DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `reference_no` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`vendor_id`),
  CONSTRAINT `fk_vendor_ledgers_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sales`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(100) DEFAULT 'Walk-in Retail Customer',
  `customer_phone` VARCHAR(50) DEFAULT NULL,
  `customer_type` ENUM('retail', 'wholesale') DEFAULT 'retail',
  `client_id` INT DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `subtotal` DECIMAL(12,2) DEFAULT 0.00,
  `discount` DECIMAL(12,2) DEFAULT 0.00,
  `total_payable` DECIMAL(12,2) DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
  `change_returned` DECIMAL(12,2) DEFAULT 0.00,
  `bank_account` VARCHAR(100) DEFAULT NULL,
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'completed',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`invoice_no`),
  INDEX (`customer_phone`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `sale_items`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`sale_id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `stock_adjustments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `adjustment_type` ENUM('in', 'out', 'set', 'sale') NOT NULL,
  `quantity` INT NOT NULL,
  `previous_qty` INT DEFAULT 0,
  `new_qty` INT DEFAULT 0,
  `remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `repairs` (Mobile Repair Lab Tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `repairs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(50) NOT NULL,
  `device_model` VARCHAR(100) NOT NULL,
  `fault_issue` TEXT NOT NULL,
  `pattern_lock` VARCHAR(100) DEFAULT NULL,
  `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
  `advance_paid` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('received', 'in_progress', 'ready', 'delivered', 'cancelled') DEFAULT 'received',
  `technician_notes` TEXT DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`token_no`),
  INDEX (`customer_phone`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `daily_closings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_closings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `closing_date` DATE NOT NULL UNIQUE,
  `opening_cash` DECIMAL(12,2) DEFAULT 0.00,
  `total_cash_sales` DECIMAL(12,2) DEFAULT 0.00,
  `total_bank_sales` DECIMAL(12,2) DEFAULT 0.00,
  `total_credit_sales` DECIMAL(12,2) DEFAULT 0.00,
  `total_repair_cash` DECIMAL(12,2) DEFAULT 0.00,
  `total_expenses` DECIMAL(12,2) DEFAULT 0.00,
  `system_cash` DECIMAL(12,2) DEFAULT 0.00,
  `physical_cash` DECIMAL(12,2) DEFAULT 0.00,
  `cash_difference` DECIMAL(12,2) DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('balanced', 'excess', 'short') DEFAULT 'balanced',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `expenses`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'General',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `remarks` TEXT DEFAULT NULL,
  `expense_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Essential Initial Seed Data (Admin Login & Categories Only)
-- ==========================================================

-- Default Users:
-- Admin: username 'admin', password '1234'
-- Cashier/Staff: username 'staff', password '1234'
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `phone`)
VALUES 
(1, 'admin', '1234', 'Administrator', 'admin', '0300-1122334'),
(2, 'staff', '1234', 'Counter Operator', 'cashier', '0321-4455667')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Default Standard Mobile Shop Categories:
INSERT INTO `categories` (`id`, `name`, `description`)
VALUES 
(1, 'Panels', 'Mobile OLED, IPS Displays & Touch Screens'),
(2, 'Batteries', 'Original & High-Capacity Mobile Batteries'),
(3, 'Chargers', 'Fast Chargers, Adapters & Power Bricks'),
(4, 'Accessories', 'Earbuds, Data Cables, Covers & Protectors')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
