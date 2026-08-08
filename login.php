<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body> 
    
    <!--Login form!-->
        <form method='POST' class="login_container" >
            <span id="login_title"></span>EcoAgri<br>
            <label>User:</label><input type="text" name="username" required>
            <label>Pass:</label><input type="password" name="password" required>
            <button type="button" command="show-modal" commandfor="register-diag">Register</button><button type="submit">Login</button>
        </form>

        <!--Register Form in a dialog container-->
    <dialog id="register-diag">
        <form method="POST" >
            <label>Username: </label><input type='text' name='rusername' required>
            <label>Password: </label><input type='password' name='rpassword' required>
            <select id="role" name="role">
                <option value="manager">Inventory Manager</option>
                <option value="staff">Production Staff</option>
                <option value="admin">Admin</option>
            </select>

            <input type="submit" value="Register"><button type='button' command="close" commandfor="register-diag">Cancel</button>
        </form>
    </dialog>
<button type="button" command="show-modal" commandfor="message-diag">Test</button>
    <!--Dialog feedback-->
    <dialog id="message-diag">
        <p id="message">Nothing to see here..</p>
        <button type='button' command="close" commandfor="message-diag">Cancel</button>
    </dialog>
    <script>
    const message = document.getElementById('message');
    const feedbackdiag = document.getElementById('message-diag');
    </script>
</body>
</html>

<?php
# PHP register
if ((isset($_POST['rusername'])) && (isset($_POST['rpassword'])) && $_SERVER['REQUEST_METHOD'] == "POST") {
    # init fetch    
    require_once "php_backend/db.php";
    $username = trim($_POST['rusername']);
    $password = trim($_POST['rpassword']);
    $role = trim($_POST['role']);


    # Check if role is valid
    if (!in_array($role, ['admin', 'staff', 'manager'])) {
        echo "<script>message.textContent = 'Role is invalid'; feedbackdiag.showModal();</script>";
        $stmt = null;
         
        exit;
    }

    #Check if admin role:
    if($role=="admin") {
        $stmt = $pdo->prepare("SELECT admin FROM accounts WHERE admin = :adminbool");
        $stmt->execute(['adminbool' => true]);
        $result = $stmt->fetch();
        if($result) #if true.
        {
            echo "<script>message.textContent = 'Only one admin is allowed!'; feedbackdiag.showModal();</script>";
            $is_admin = null;
            $stmt = null;
             
            exit;
        }
        $is_admin = true;
    }

    
    # Check if user exists already
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user  = :username");
    $stmt->execute([':username' => $username]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "<script>message.textContent = 'Username already exists!'; feedbackdiag.showModal();</script>";
        $stmt = null;
         
        exit;
    }

    # Hashing password
    $hashedpassword = password_hash($password, PASSWORD_ARGON2ID);

    # Register user with exception
    try {
        $stmt = $pdo->prepare("INSERT INTO accounts (role, user, pass, admin) VALUES (:roles, :username, :hashedpassword, :administ)");
    } catch (Throwable $e) {
        echo "<script>message.textContent = '" . 'Something went wrong: ' . $e->getMessage() . "'; feedbackdiag.showModal();</script>";
        $stmt = null;
         
        exit;
    }
    $stmt->bindValue(':roles', $role);
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':hashedpassword', $hashedpassword);
    $stmt->bindValue(':administ', $is_admin);
    if ($stmt->execute()) {
        echo "<script>message.textContent = 'Registered successfully!'; feedbackdiag.showModal();</script>";
        sleep(1000);
        header("Location:  login.php");
        $stmt = null;
         
        exit;
    }
}

# PHP Login
if ((isset($_POST['username'])) && (isset($_POST['password'])) && $_SERVER['REQUEST_METHOD'] == "POST") {
    // Use #PDO, htmlspecialchars, trim?, 
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    require_once "php_backend/db.php";

    # Check if user exists first
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user = :username");
    $stmt->execute([':username' => $user]);
    $count = $stmt->fetchColumn();
    if ($count !=1) {
        echo "<script>message.textContent = 'Username not found!'; feedbackdiag.showModal();</script>";
         
        exit;
    }

    # Fetch the password
    $stmt = $pdo->prepare("SELECT id,role,pass FROM accounts WHERE user = :username");
    $stmt->bindValue(':username', $user);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    # Verify the password
    if(password_verify($pass, $row['pass'])) {
        session_start(); 
        session_regenerate_id(true);
        # Use the username, id, role in session.
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $user;
        $_SESSION['user_role'] = $row['role'];
        header("Location: index.php");
         
        exit;
    } else {
        echo "<script>message.textContent = 'Password is incorrect!'; feedbackdiag.showModal();</script>";
    }
}
 
$stmt = null;
exit;
?>