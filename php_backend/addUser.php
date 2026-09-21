<?php 
require_once "db.php";

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['role'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    # Check if role is valid
    if (!in_array($role, ['admin', 'staff', 'manager'])) {
        header("Location: ../users.php?error=" . urlencode("Invalid role"));
        exit;
    }

    # Check if admin role already exists
    if ($role === "admin") {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE admin = 1");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            header("Location: ../users.php?error=" . urlencode("Only one admin is allowed"));
            exit;
        }
        $is_admin = true;
    } // no need else
 // Note: Role bool to null, Cause the admin role check is unique.
    

    # Check if user exists already
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetchColumn() > 0) {
        header("Location: ../users.php?error=" . urlencode("Username already exists"));
        exit;
    }

    # Hashing password
    if (empty($password)) {
        header("Location: ../users.php?error=" . urlencode("Password is required"));
        exit;
    }
    $hashedpassword = password_hash($password, PASSWORD_ARGON2ID);

    # Insert user
    $stmt = $pdo->prepare("INSERT INTO accounts (role, user, pass, admin) VALUES (:roles, :username, :hashedpassword, :administ)");
    $stmt->bindValue(':roles', $role);
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':hashedpassword', $hashedpassword);
    $stmt->bindValue(':administ', $is_admin);

    if ($stmt->execute()) {
        header("Location: ../users.php?success=" . urlencode("User added successfully"));
    } else {
        header("Location: ../users.php?error=" . urlencode("Failed to add user"));
    }
    exit;
}
?>