-- Foodie Corner database setup for MySQL / XAMPP
CREATE DATABASE IF NOT EXISTS food_ordering_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE food_ordering_db;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  category VARCHAR(80) NOT NULL,
  image VARCHAR(255) NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name VARCHAR(150) NOT NULL,
  phone VARCHAR(25) NOT NULL,
  address TEXT NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('Pending','Preparing','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_phone (phone),
  KEY idx_orders_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  subtotal DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order_id (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Initial menu items. The application reads and manages these records from MySQL.
INSERT INTO products (id, name, price, category, image, is_available) VALUES
(1, 'Classic Burger', 129.00, 'Burgers', 'images/burger.jpg', 1),
(2, 'Cheese Pizza', 249.00, 'Pizza', 'images/pizza.jpg', 1),
(3, 'White Sauce Pasta', 199.00, 'Pasta', 'images/pasta.jpg', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), category=VALUES(category), image=VALUES(image);

-- Preserve the sample order that was previously stored in storage/orders.json.
-- It is imported once as MySQL order #1 when setting up a fresh database.
INSERT INTO orders (id, customer_name, phone, address, total_amount, status, created_at) VALUES
(1, 'Roshan Vakodikar', '9879043887', 'sgege', 249.00, 'Pending', '2026-10-21 18:47:35')
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
SELECT 1, 2, 'Cheese Pizza', 249.00, 1, 249.00
WHERE EXISTS (SELECT 1 FROM orders WHERE id=1)
  AND NOT EXISTS (SELECT 1 FROM order_items WHERE order_id=1 AND product_name='Cheese Pizza');
