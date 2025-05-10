<?php
require_once('connection.php');

// Drop existing tables if they exist
$db->exec('DROP TABLE IF EXISTS messages');
$db->exec('DROP TABLE IF EXISTS reviews');
$db->exec('DROP TABLE IF EXISTS orders');
$db->exec('DROP TABLE IF EXISTS service_images');
$db->exec('DROP TABLE IF EXISTS services');
$db->exec('DROP TABLE IF EXISTS categories');
$db->exec('DROP TABLE IF EXISTS users');

// Create tables
$db->exec('
  CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    bio TEXT,
    profile_image TEXT,
    is_admin INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
');

$db->exec('
  CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT,
    parent_id INTEGER,
    FOREIGN KEY (parent_id) REFERENCES categories(id)
  )
');

$db->exec('
  CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    price REAL NOT NULL,
    delivery_time INTEGER NOT NULL,
    freelancer_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    is_featured INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
  )
');

$db->exec('
  CREATE TABLE service_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    image_path TEXT NOT NULL,
    is_primary INTEGER DEFAULT 0,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
  )
');

$db->exec('
  CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    price REAL NOT NULL,
    requirements TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
  )
');

$db->exec('
  CREATE TABLE reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER UNIQUE NOT NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  )
');

$db->exec('
  CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    order_id INTEGER,
    content TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  )
');

// Insert default admin user (password: admin123)
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$db->exec("
  INSERT INTO users (username, password, email, name, is_admin) 
  VALUES ('admin', '$adminPassword', 'admin@freelance.com', 'Administrator', 1)
");

// Insert some default categories
$categories = [
  ['Web Development', 'Services related to website development'],
  ['Graphic Design', 'Services related to visual content creation'],
  ['Writing & Translation', 'Content writing and language translation services'],
  ['Digital Marketing', 'Services to promote businesses online'],
  ['Video & Animation', 'Video creation and animation services']
];

$stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
foreach ($categories as $category) {
  $stmt->execute($category);
}

echo "Database initialized successfully!";
