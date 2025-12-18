
CREATE TABLE `category` (
  `category_id` INT (10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uk_category_name` (`category_name`)
);

CREATE TABLE `customer` (
  `customer_id` INT (10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
);

CREATE TABLE `user_account` (
  `user_id` INT (10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uk_username` (`username`)
);

CREATE TABLE `fruit` (
  `fruit_id` INT (10) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `fruit_name` VARCHAR(100) NOT NULL,
  `unit_type` VARCHAR(10) NOT NULL DEFAULT 'piece', 
  `category_id` INT DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL DEFAULT 'images/default.png',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uk_fruit_name` (`fruit_name`),
  KEY `ix_fruit_category` (`category_id`),
  CONSTRAINT `fk_fruit_category` FOREIGN KEY (`category_id`) REFERENCES `category`(`category_id`) ON UPDATE CASCADE ON DELETE SET NULL
);

-- Price history per unit
CREATE TABLE `fruit_price` (
  `price_id` BIGINT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `fruit_id` INT NOT NULL,
  `unit` VARCHAR(10) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `effective_from` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `effective_to` DATETIME DEFAULT NULL,
  KEY `ix_price_fruit_unit` (`fruit_id`,`unit`,`effective_from`),
  CONSTRAINT `fk_price_fruit` FOREIGN KEY (`fruit_id`) REFERENCES `fruit`(`fruit_id`) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE `sale` (
  `sale_id` BIGINT PRIMARY KEY  NOT NULL AUTO_INCREMENT,
  `sale_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `custom_customer_name` VARCHAR(150) NOT NULL DEFAULT '',
  KEY `ix_sale_customer` (`customer_id`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`) REFERENCES `user_account`(`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE `sale_item` (
  `sale_item_id` BIGINT PRIMARY KEY  NOT NULL AUTO_INCREMENT,
  `sale_id` BIGINT NOT NULL,
  `fruit_id` INT NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `unit` VARCHAR(10) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  KEY `ix_item_sale` (`sale_id`),
  KEY `ix_item_fruit` (`fruit_id`),
  CONSTRAINT `fk_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sale`(`sale_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_item_fruit` FOREIGN KEY (`fruit_id`) REFERENCES `fruit`(`fruit_id`) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE `inventory_txn` (
  `txn_id` BIGINT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `fruit_id` INT NOT NULL,
  `unit` VARCHAR(10) NOT NULL,
  `quantity` DECIMAL(10,3) NOT NULL,
  `reference_type` VARCHAR(20) DEFAULT NULL, 
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_inv_fruit` (`fruit_id`),
  CONSTRAINT `fk_inv_fruit` FOREIGN KEY (`fruit_id`) REFERENCES `fruit`(`fruit_id`) ON UPDATE CASCADE ON DELETE RESTRICT
);
