<?php
function isLoggedIn() {
  return isset($_SESSION['user_id']);
}

function isAdmin() {
  return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
  if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to access this page";
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
  }
}

function requireAdmin() {
  requireLogin();
  if (!isAdmin()) {
    $_SESSION['error'] = "You don't have permission to access this page";
    header("Location: index.php");
    exit();
  }
}

function getUserById($db, $userId) {
  $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  return $stmt->fetch();
}

function getCurrentUser($db) {
  if (!isLoggedIn()) return null;
  return getUserById($db, $_SESSION['user_id']);
}
