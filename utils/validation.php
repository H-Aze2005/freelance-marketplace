<?php
function validateUsername($username) {
  if (strlen($username) < 3 || strlen($username) > 20) {
    return "Username must be between 3 and 20 characters";
  }
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    return "Username can only contain letters, numbers, and underscores";
  }
  return null;
}

function validatePassword($password) {
  if (strlen($password) < 8) {
    return "Password must be at least 8 characters long";
  }
  return null;
}

function validateEmail($email) {
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return "Invalid email format";
  }
  return null;
}

function validateName($name) {
  if (strlen($name) < 2 || strlen($name) > 50) {
    return "Name must be between 2 and 50 characters";
  }
  return null;
}

function sanitizeInput($input) {
  return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
