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
        header("Location: login.php?error=" . urlencode("Role is invalid"));
        exit;
    }

    #Check if admin role:
    if($role=="admin") {
        $stmt = $pdo->prepare("SELECT admin FROM accounts WHERE admin = :adminbool");
        $stmt->execute(['adminbool' => true]);
        $result = $stmt->fetch();
        if($result) #if true.
        {
            header("Location: login.php?error=" . urlencode("Only one admin is allowed!"));
            exit;
        }
        $is_admin = true;
    }

    
    # Check if user exists already
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user  = :username");
    $stmt->execute([':username' => $username]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        header("Location: login.php?error=" . urlencode("Username already exists!"));
        exit;
    }

    # Hashing password
    $hashedpassword = password_hash($password, PASSWORD_ARGON2ID);

    # Register user with exception
    try {
        $stmt = $pdo->prepare("INSERT INTO accounts (role, user, pass, admin) VALUES (:roles, :username, :hashedpassword, :administ)");
    } catch (Throwable $e) {
        header("Location: login.php?error=" . urlencode("Something went wrong: " . $e->getMessage()));
        exit;
    }
    $stmt->bindValue(':roles', $role);
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':hashedpassword', $hashedpassword);
    $stmt->bindValue(':administ', $is_admin);
    if ($stmt->execute()) {
        $stmt = null;
        header("Location: login.php?registered=1");
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
        header("Location: login.php?error=" . urlencode("Username not found!"));
        exit;
    }

    # Fetch the password
    $stmt = $pdo->prepare("SELECT id,role,pass FROM accounts WHERE user = :username");
    $stmt->bindValue(':username', $user);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $password = $row['pass'] ?? '';

    # Verify the password
    if(password_verify($pass, $password)) {
        session_start();
        session_regenerate_id(true);
        # Use the username, id, role in session.
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $user;
        $_SESSION['user_role'] = $row['role'];
        header("Location: index.php");         
        exit;
    } else {
        header("Location: login.php?error=" . urlencode("Password is incorrect!"));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermicast ERP - Login</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body"> 
    <div class="login-page"> <!--Login page START-->
        <!--Login Card Container-->
        <div class="login-card">
            <!--Card Header-->
            <div class="login-card-header">
                <div class="header-logo">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <h1>Vermicast ERP</h1>
                <p>Prototype System</p>
            </div>

            <!--Card Body-->
            <div class="login-card-body">
                <form method="POST" class="login-form">
                    <div class="input-group">
                        <label for="username"><i class="fa-solid fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter username" autocomplete="off" required>
                    </div>

                    <div class="input-group">
                        <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="off" autocorrect="off" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-right-to-bracket"></i> Login
                        </button>
                    </div>

                    <div class="account-register-prompt">
                        <span>Doesn't have an account?</span>
                        <button type="button" class="btn-link" id="open-register-btn" command="show-modal" commandfor="register-diag">Create an account</button>
                    </div>
                </form>
            </div>

            <!--Card Footer-->
            <div class="login-card-footer">
                <div class="demo-title">Register to get started</div>
                <!-- For info desc
                <div class="demo-cred">Admin: admin / password123</div>
                <div class="demo-cred">Staff: staff1 / password123</div>
                <div class="demo-cred">Manager: manager1 / password123</div>-->
            </div>
        </div>

        <!--Register Form in a dialog container-->
        <dialog id="register-diag">
            <div class="dialog-header">
                <div style="font-size: 28px; margin-bottom: 6px;"><i class="fa-solid fa-leaf"></i></div>
                <h2>Register Account</h2>
                <p>Vermicast ERP System</p>
            </div>
            <div class="dialog-body">
                <form method="POST">
                    <div class="input-group">
                        <label for="rusername"><i class="fa-solid fa-user"></i> Username</label>
                        <input type="text" id="rusername" name="rusername" placeholder="Create Username" autocomplete="on" required>
                    </div>
                    <div class="input-group">
                        <label for="rpassword"><i class="fa-solid fa-lock"></i> Password</label>
                        <input type="password" id="rpassword" name="rpassword" placeholder="Create Password" autocomplete="off" autocorrect="off" required>
                    </div>
                    <div class="input-group">
                        <label for="role"><i class="fa-solid fa-user-tag"></i> Role</label>
                        <select id="role" name="role">
                            <option value="manager">Inventory Manager</option>
                            <option value="staff">Production Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="dialog-actions">
                        <button type="button" class="btn-secondary" id="close-register-btn" command="close" commandfor="register-diag">Cancel</button>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-user-plus"></i> Register</button>
                    </div>
                </form>
            </div>
        </dialog>

        <!--Dialog feedback-->
        <dialog id="message-diag">
            <div class="dialog-header">
                <h2>Notice</h2>
            </div>
            
            <div class="dialog-body">
                <p id="message">Nothing to see here..</p>
                <div class="dialog-actions" style="justify-content: center;">
                    <button type="button" class="btn-primary" id="close-msg-btn" command="close" commandfor="message-diag">OK</button>
                </div>
            </div>
        </dialog>
    </div> <!--Login page END-->
    <script>
    const message = document.getElementById('message');
    const feedbackdiag = document.getElementById('message-diag');
    const registerdiag = document.getElementById('register-diag');
    const openRegisterBtn = document.getElementById('open-register-btn');
    const closeRegisterBtn = document.getElementById('close-register-btn');
    const closeMsgBtn = document.getElementById('close-msg-btn');

    if (openRegisterBtn && registerdiag) {
        openRegisterBtn.addEventListener('click', () => registerdiag.showModal());
    }
    if (closeRegisterBtn && registerdiag) {
        closeRegisterBtn.addEventListener('click', () => registerdiag.close());
    }
    if (closeMsgBtn && feedbackdiag) {
        closeMsgBtn.addEventListener('click', () => feedbackdiag.close());
    }

    // Show "Registered successfully!" after the redirect from register
    if (new URLSearchParams(window.location.search).has('registered')) {
        message.textContent = 'Registered successfully! You can now login.';
        feedbackdiag.showModal();
    }

    // Show login/register errors passed back via ?error=...
    const urlError = new URLSearchParams(window.location.search).get('error');
    if (urlError) {
        message.textContent = urlError;
        feedbackdiag.showModal();
    }
    </script>
</body>
</html>