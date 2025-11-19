CREATE DATABASE `fruit_pos`;
USE `fruit_pos`;

CREATE TABLE `category` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB;

CREATE TABLE `customer` (
  `customer_id` INT NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB;

CREATE TABLE `fruit` (
  `fruit_id` INT NOT NULL AUTO_INCREMENT,
  `fruit_name` VARCHAR(100) NOT NULL,
  `price_per_piece` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_per_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` DECIMAL(10,3) NOT NULL,
  `unit_type` VARCHAR(10) NOT NULL DEFAULT 'piece',
  `category_id` INT DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL DEFAULT 'images/default.png',
  PRIMARY KEY (`fruit_id`)
) ENGINE=InnoDB;

CREATE TABLE `sale` (
  `sale_id` INT NOT NULL AUTO_INCREMENT,
  `sale_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `customer_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `custom_customer_name` VARCHAR(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`sale_id`)
) ENGINE=InnoDB;

CREATE TABLE `sale_item` (
  `sale_item_id` INT NOT NULL AUTO_INCREMENT,
  `sale_id` INT NOT NULL,
  `fruit_id` INT NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `unit` VARCHAR(10) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`sale_item_id`)
) ENGINE=InnoDB;
