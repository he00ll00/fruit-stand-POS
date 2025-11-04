
CREATE DATABASE IF NOT EXISTS `fruit_pos`;
USE `fruit_pos`;

DROP TABLE IF EXISTS `sale_item`;
DROP TABLE IF EXISTS `sale`;
DROP TABLE IF EXISTS `fruit`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `customer`;

CREATE TABLE `category` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB;

CREATE TABLE `customer` (
  `customer_id` INT NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
  `contact_number` VARCHAR(30) DEFAULT NULL,
  `customer_type` VARCHAR(50) NOT NULL DEFAULT 'retail',
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB;

CREATE TABLE `fruit` (
  `fruit_id` INT NOT NULL AUTO_INCREMENT,
  `fruit_name` VARCHAR(100) NOT NULL,
  `price_per_kg` DECIMAL(10,2) NOT NULL,
  `stock_qty` INT NOT NULL DEFAULT 0,
  `category_id` INT DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL DEFAULT 'images/default.png',
  PRIMARY KEY (`fruit_id`)
) ENGINE=InnoDB;

CREATE TABLE `sale` (
  `sale_id` INT NOT NULL AUTO_INCREMENT,
  `sale_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `customer_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL DEFAULT 1,
  `custom_customer_name` VARCHAR(150) NOT NULL DEFAULT '',
  `payment_type` VARCHAR(20) NOT NULL DEFAULT 'cash',
  PRIMARY KEY (`sale_id`)
) ENGINE=InnoDB;

CREATE TABLE `sale_item` (
  `sale_item_id` INT NOT NULL AUTO_INCREMENT,
  `sale_id` INT NOT NULL,
  `fruit_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`sale_item_id`)
) ENGINE=InnoDB;

