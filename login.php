<?php
    session_start();
    // ১. আপনার হোটেলের ডাটাবেজ কানেকশন (db_name: hotel_db)
    require_once 'db.php';

    $error_msg = "";

    if(isset($_POST['submit_login'])){

        // SQL Injection প্রতিরোধ করার জন্য input escape করা
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $pass = mysqli_real_escape_string($conn, $_POST['pass']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);

        // পূর্বে তৈরি করা `users` টেবিলের কলাম অনুসারে SQL Query
        $sql = "SELECT * FROM users WHERE username = '$name' AND password = '$pass' AND user_type = '$role' AND status = 'Active'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0){
            $user_data = mysqli_fetch_assoc($result);

            // সেশন ডেটা সেট করা
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['user_type'] = $user_data['user_type'];
            $_SESSION['guest_id'] = $user_data['guest_id'];
            $_SESSION['staff_id'] = $user_data['staff_id'];

            // লাস্ট লগইন টাইম আপডেট
            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE user_id = '{$user_data['user_id']}'");

            // রোল অনুযায়ী রিডাইরেক্ট
            if($role === "Guest"){
                header("Location: guest_dashboard.php");
                exit();
            } else if($role === "Staff"){
                header("Location: staff_dashboard.php");
                exit();
            } else if($role === "Admin"){
                header("Location: admin_dashboard.php");
                exit();
            }
        } else {
            $error_msg = "Invalid username, password, or account role!";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AHMANI HOTEL - Login</title>
    
    <!-- Stylist Serif Google Font for Luxury Look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #0A192F;
            --card-bg: rgba(17, 34, 64, 0.85);
            --accent-gold: #D4AF37;
            --accent-hover: #C5A880;
            --text-light: #F8F9FA;
            --text-muted: #CBD5E1;
            --error-red: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.7), rgba(10, 25, 47, 0.7)), 
                        url('hotel_bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .auth-layout {
            background-color: var(--card-bg);
            border: 1px solid rgba(212, 175, 55, 0.3);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            text-align: center;
        }

        .logo-side {
            margin-bottom: 25px;
        }

        .logo-img {
            max-width: 90px;
            height: auto;
            margin-bottom: 10px;
        }

        /* Stylist Title Font Applied */
        .portal-title {
            font-family: 'Playfair Display', serif;
            color: var(--accent-gold);
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            text-align: left;
        }

        .form-group p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 6px;
        }

        .input-field, select {
            width: 100%;
            padding: 12px 15px;
            background-color: rgba(10, 25, 47, 0.8);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 6px;
            color: var(--text-light);
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-field:focus, select:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 8px rgba(212, 175, 55, 0.4);
        }

        select option {
            background-color: #0A192F;
            color: var(--text-light);
        }

        .btn {
            background-color: var(--accent-gold);
            color: #000000;
            font-weight: 600;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: var(--accent-hover);
        }

        .error-message {
            color: var(--error-red);
            font-size: 13px;
            margin-top: 10px;
            text-align: center;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--accent-gold);
            border: 1px solid var(--accent-gold);
            font-weight: 600;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 5px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            width: 100%;
        }

        .btn-outline:hover {
            background-color: rgba(212, 175, 55, 0.1);
            color: var(--accent-hover);
            border-color: var(--accent-hover);
        }
    </style>
</head>

<body>

    <div class="auth-layout">
        <div class="logo-side">
            <img src="logo.svg" alt="Hotel Logo" class="logo-img">
            <h2 class="portal-title">Welcome to AHMANI HOTEL</h2>
        </div>

        <form id="loginForm" class="login-form" action="login.php" method="POST">

            <div class="form-group">
                <p>Username</p>
                <input
                    type="text"
                    id="username"
                    class="input-field"
                    name="name"
                    placeholder="Enter username"
                    required>
            </div>

            <div class="form-group">
                <p>Password</p>
                <input
                    type="password"
                    id="password"
                    class="input-field"
                    name="pass"
                    placeholder="Enter password"
                    required>
            </div>

            <div class="form-group">
                <p>Select User Role</p>
                <select name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="Guest">Guest</option>
                    <option value="Staff">Staff</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <button
                type="submit"
                id="loginBtn"
                class="btn"
                name="submit_login">
                Login
            </button>

            <a href="index.php" class="btn-outline">
                Back to Home Page
            </a>

            <?php if(!empty($error_msg)): ?>
                <p class="error-message"><?php echo $error_msg; ?></p>
            <?php endif; ?>

        </form>
    </div>

</body>
</html>