<?php
session_start();

// Staff Session Validation
if (!isset($_SESSION['username']) || ($_SESSION['user_type'] !== 'Staff' && $_SESSION['user_type'] !== 'Admin')) {
    header("Location: login.php"); 
    exit;
}

require_once 'db.php';

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_type'];

// Staff ID & Job Role
$staff_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : NULL;
$staff_job_role = "";

if ($staff_id) {
    $s_res = mysqli_query($conn, "SELECT role FROM staff WHERE staff_id = '$staff_id'");
    if ($s_row = mysqli_fetch_assoc($s_res)) {
        $staff_job_role = $s_row['role'];
    }
}

$can_manage_bookings = ($role === 'Admin' || $staff_job_role === 'Manager' || $staff_job_role === 'Receptionist');

$msg_checkin = "";
$msg_checkout = "";
$msg_hall_booking = "";
$msg_order_status = "";

// PHP Variables to retain Check Existence inputs & results
$chk_fn = $_POST['first_name'] ?? '';
$chk_ln = $_POST['last_name'] ?? '';
$chk_em = $_POST['email'] ?? '';
$chk_ph = $_POST['phone'] ?? '';
$chk_id_type = $_POST['id_card_type'] ?? '';
$chk_id_num = $_POST['id_card_number'] ?? '';
$chk_address = $_POST['address'] ?? '';

$guest_check_performed = false;
$guest_exists = false;
$matched_guest_id = null;
$check_msg = "";

// -------------------------------------------------------------
// Update Service Order Status Logic
// -------------------------------------------------------------
if (isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['order_status']);

    if (!empty($order_id) && !empty($new_status)) {
        $update_sql = "UPDATE service_orders SET order_status = '$new_status' WHERE order_id = '$order_id'";
        if (mysqli_query($conn, $update_sql)) {
            $msg_order_status = "<span style='color:#10B981;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#10B981' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><polyline points='20 6 9 17 4 12'/></svg> Order #$order_id status updated to '$new_status'!</span>";
        } else {
            $msg_order_status = "<span style='color:#EF4444;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#EF4444' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Failed to update status: " . mysqli_error($conn) . "</span>";
        }
    }
}

// -------------------------------------------------------------
// 0. Linear Search Based Guest Existence Check Logic
// -------------------------------------------------------------
if (isset($_POST['btn_check_guest_exist'])) {
    $guest_check_performed = true;

    $fn = strtolower(trim($chk_fn));
    $ln = strtolower(trim($chk_ln));
    $em = strtolower(trim($chk_em));
    $ph = strtolower(trim($chk_ph));
    $id_type = strtolower(trim($chk_id_type));
    $id_num = strtolower(trim($chk_id_num));

    if (!empty($fn) && !empty($ln) && !empty($em) && !empty($ph) && !empty($id_type) && !empty($id_num)) {
        
        $target_key = $fn . '|' . $ln . '|' . $em . '|' . $ph . '|' . $id_type . '|' . $id_num;
        $query = mysqli_query($conn, "SELECT guest_id, first_name, last_name, email, phone, id_card_type, id_card_number FROM guests");

        $guest_exists = false;
        $matched_guest_id = null;

        while ($guest = mysqli_fetch_assoc($query)) {
            $current_key = strtolower($guest['first_name']) . '|' . 
                          strtolower($guest['last_name']) . '|' . 
                          strtolower($guest['email']) . '|' . 
                          strtolower($guest['phone']) . '|' . 
                          strtolower($guest['id_card_type']) . '|' . 
                          strtolower($guest['id_card_number']);

            if ($current_key === $target_key) {
                $guest_exists = true;
                $matched_guest_id = $guest['guest_id'];
                break;
            }
        }

        if ($guest_exists) {
            $check_msg = "<span style='color:#10B981;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#10B981' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><polyline points='20 6 9 17 4 12'/></svg> Exact Guest Match Found via Linear Search (Guest #{$matched_guest_id})! Username & Password not required.</span>";
        } else {
            $check_msg = "<span style='color:#D4AF37;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#D4AF37' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><path d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Guest not found in records. Please create a new Guest Account below.</span>";
        }

    } else {
        $check_msg = "<span style='color:#EF4444;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#EF4444' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Please fill in ALL personal & ID fields before checking!</span>";
    }
}

// -------------------------------------------------------------
// 1. Walk-in Guest Check-In Logic
// -------------------------------------------------------------
if (isset($_POST['walkin_checkin'])) {
    if (!$can_manage_bookings) {
        $msg_checkin = "Error: Access denied! Only Managers and Receptionists can add guests.";
    } else {
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $id_card_type = mysqli_real_escape_string($conn, $_POST['id_card_type']);
        $id_card_number = mysqli_real_escape_string($conn, $_POST['id_card_number']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);

        $room_id = intval($_POST['room_id']);
        
        // System Automatically Sets Current Date for Check-In
        $check_in = date('Y-m-d');
        $check_out = mysqli_real_escape_string($conn, $_POST['check_out']);
        $total_guests = intval($_POST['total_guests']);

        $guest_username = isset($_POST['guest_username']) ? mysqli_real_escape_string($conn, $_POST['guest_username']) : '';
        $guest_password = isset($_POST['guest_password']) ? mysqli_real_escape_string($conn, $_POST['guest_password']) : '';

        if (!empty($guest_username)) {
            $check_user = mysqli_query($conn, "SELECT 1 FROM users WHERE username = '$guest_username'");
            if (mysqli_num_rows($check_user) > 0) {
                $msg_checkin = "Error: Username '$guest_username' already exists! Please enter a unique username.";
            } else {
                $sql_guest = "INSERT INTO guests (first_name, last_name, email, phone, id_card_type, id_card_number, address) 
                              VALUES ('$first_name', '$last_name', '$email', '$phone', '$id_card_type', '$id_card_number', '$address')";
                
                if (mysqli_query($conn, $sql_guest)) {
                    $guest_id = mysqli_insert_id($conn);
                    $created_by = $staff_id ? "'$staff_id'" : "NULL";
                    
                    $sql_user = "INSERT INTO users (guest_id, username, email, password, user_type) 
                                VALUES ('$guest_id', '$guest_username', '$email', '$guest_password', 'Guest')";
                    mysqli_query($conn, $sql_user);

                    $sql_booking = "INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, total_guests, booking_status, created_by_staff_id, created_at) 
                                    VALUES ('$guest_id', '$room_id', '$check_in', '$check_out', '$total_guests', 'Confirmed', $created_by, NOW())";
                    
                    if (mysqli_query($conn, $sql_booking)) {
                        mysqli_query($conn, "UPDATE rooms SET status = 'Occupied' WHERE room_id = '$room_id'");
                        $msg_checkin = "Check-in successful & Guest Login Account created for $first_name $last_name!";
                    } else {
                        $msg_checkin = "Failed to process booking: " . mysqli_error($conn);
                    }
                } else {
                    $msg_checkin = "Failed to register guest details: " . mysqli_error($conn);
                }
            }
        } else {
            $chk_g = mysqli_query($conn, "SELECT guest_id FROM guests WHERE LOWER(first_name) = LOWER('$first_name') AND LOWER(last_name) = LOWER('$last_name') AND LOWER(email) = LOWER('$email') AND LOWER(phone) = LOWER('$phone') AND LOWER(id_card_type) = LOWER('$id_card_type') AND LOWER(id_card_number) = LOWER('$id_card_number')");
            if ($g_row = mysqli_fetch_assoc($chk_g)) {
                $guest_id = $g_row['guest_id'];
            } else {
                mysqli_query($conn, "INSERT INTO guests (first_name, last_name, email, phone, id_card_type, id_card_number, address) VALUES ('$first_name', '$last_name', '$email', '$phone', '$id_card_type', '$id_card_number', '$address')");
                $guest_id = mysqli_insert_id($conn);
            }

            $created_by = $staff_id ? "'$staff_id'" : "NULL";
            $sql_booking = "INSERT INTO bookings (guest_id, room_id, check_in_date, check_out_date, total_guests, booking_status, created_by_staff_id, created_at) 
                            VALUES ('$guest_id', '$room_id', '$check_in', '$check_out', '$total_guests', 'Confirmed', $created_by, NOW())";
            
            if (mysqli_query($conn, $sql_booking)) {
                mysqli_query($conn, "UPDATE rooms SET status = 'Occupied' WHERE room_id = '$room_id'");
                $msg_checkin = "Check-in successful for $first_name $last_name!";
            } else {
                $msg_checkin = "Failed to process booking: " . mysqli_error($conn);
            }
        }
    }
}

// -------------------------------------------------------------
// 2. Guest Check-Out Logic & Service Fee Calculation
// -------------------------------------------------------------
if (isset($_POST['process_checkout'])) {
    if (!$can_manage_bookings) {
        $msg_checkout = "<span style='color:#EF4444;'>Error: Access denied! Only Managers and Receptionists can check out guests.</span>";
    } else {
        $booking_id = intval($_POST['booking_id']);

        if (!empty($booking_id)) {
            $b_chk = mysqli_query($conn, "SELECT room_id FROM bookings WHERE booking_id = '$booking_id' AND booking_status IN ('Confirmed', 'CheckedIn')");
            if ($b_row = mysqli_fetch_assoc($b_chk)) {
                $room_id = $b_row['room_id'];

                // Update Booking Status
                mysqli_query($conn, "UPDATE bookings SET booking_status = 'CheckedOut' WHERE booking_id = '$booking_id'");
                
                // Update Room Status to Available
                mysqli_query($conn, "UPDATE rooms SET status = 'Available' WHERE room_id = '$room_id'");

                // Calculate fee for COMPLETED service orders ONLY
                $service_sum_res = mysqli_query($conn, "SELECT SUM(total_cost) AS total_service_fee FROM service_orders WHERE booking_id = '$booking_id' AND order_status = 'Completed'");
                $s_row = mysqli_fetch_assoc($service_sum_res);
                $total_service_fee = $s_row['total_service_fee'] ? number_format($s_row['total_service_fee'], 2) : "0.00";

                $msg_checkout = "<span style='color:#10B981;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#10B981' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><polyline points='20 6 9 17 4 12'/></svg> Check-out successful for Booking #{$booking_id}! Total Completed Service Fee: \${$total_service_fee}</span>";
            } else {
                $msg_checkout = "<span style='color:#EF4444;'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#EF4444' stroke-width='2' style='vertical-align:-2px; margin-right:4px;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Invalid booking or guest already checked out!</span>";
            }
        }
    }
}

// -------------------------------------------------------------
// 3. Hall Event Booking Logic
// -------------------------------------------------------------
if (isset($_POST['add_hall_booking'])) {
    if (!$can_manage_bookings) {
        $msg_hall_booking = "Error: Access denied! Only Managers and Receptionists can book halls.";
    } else {
        $hall_id = intval($_POST['hall_id']);
        $event_title = mysqli_real_escape_string($conn, $_POST['event_title']);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);

        if (strtotime($start_time) >= strtotime($end_time)) {
            $msg_hall_booking = "Error: End time must be after start time!";
        } else {
            $sql_hb = "INSERT INTO hall_bookings (hall_id, event_title, start_time, end_time, status) 
                       VALUES ('$hall_id', '$event_title', '$start_time', '$end_time', 'Pending')";
            
            if (mysqli_query($conn, $sql_hb)) {
                $msg_hall_booking = "Hall event '$event_title' booked successfully!";
            } else {
                $msg_hall_booking = "Failed to add hall booking: " . mysqli_error($conn);
            }
        }
    }
}

// -------------------------------------------------------------
// 4. Guest Search Logic (Multi-criteria Linear Search)
// -------------------------------------------------------------
$search_results = [];
$search_performed = false;
$search_fn = ""; $search_ln = ""; $search_em = ""; $search_ph = "";

if (isset($_POST['search_guest'])) {
    $search_performed = true;
    
    $search_fn = trim($_POST['search_first_name']);
    $search_ln = trim($_POST['search_last_name']);
    $search_em = trim($_POST['search_email']);
    $search_ph = trim($_POST['search_phone']);

    if (!empty($search_fn) || !empty($search_ln) || !empty($search_em) || !empty($search_ph)) {
        $all_guests_query = mysqli_query($conn, "SELECT * FROM guests");
        
        $s_fn = strtolower($search_fn);
        $s_ln = strtolower($search_ln);
        $s_em = strtolower($search_em);
        $s_ph = strtolower($search_ph);

        while ($guest = mysqli_fetch_assoc($all_guests_query)) {
            $fname = strtolower($guest['first_name']);
            $lname = strtolower($guest['last_name']);
            $email = strtolower($guest['email']);
            $phone = strtolower($guest['phone']);

            $match_fn = (!empty($s_fn) && strpos($fname, $s_fn) !== false);
            $match_ln = (!empty($s_ln) && strpos($lname, $s_ln) !== false);
            $match_em = (!empty($s_em) && strpos($email, $s_em) !== false);
            $match_ph = (!empty($s_ph) && strpos($phone, $s_ph) !== false);

            if ($match_fn || $match_ln || $match_em || $match_ph) {
                $search_results[] = $guest;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - AHMANI HOTEL</title>
    <style>
        :root {
            --bg-color: #0A192F;
            --card-bg: rgba(17, 34, 64, 0.9);
            --accent-gold: #D4AF37;
            --accent-hover: #C5A880;
            --text-light: #F8F9FA;
            --text-muted: #CBD5E1;
            --border-color: rgba(212, 175, 55, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(10, 25, 47, 0.85)), url('hotel_bg.jpg') center/cover fixed;
            color: var(--text-light);
            min-height: 100vh;
            padding: 20px;
        }

        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--card-bg); border: 1px solid var(--border-color);
            padding: 15px 25px; border-radius: 8px; margin-bottom: 20px;
        }

        .topbar h3 { color: var(--accent-gold); font-size: 18px; }

        .logout-btn {
            background-color: var(--accent-gold); color: #000; padding: 8px 16px;
            text-decoration: none; font-weight: 600; border-radius: 5px; transition: 0.3s;
        }

        .logout-btn:hover { background-color: var(--accent-hover); }

        .tab_nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }

        .tab_btn {
            background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-light);
            padding: 12px 18px; border-radius: 6px; cursor: pointer; display: flex; align-items: center;
            gap: 8px; font-size: 14px; transition: 0.3s;
        }

        .tab_btn svg { width: 18px; height: 18px; fill: currentColor; }

        .tab_btn.active, .tab_btn:hover { background: var(--accent-gold); color: #000; }

        .tab_panel {
            display: none; background: var(--card-bg); border: 1px solid var(--border-color);
            padding: 25px; border-radius: 8px;
        }

        .tab_panel.active { display: block; }

        .section_title { color: var(--accent-gold); margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }

        .staff_form { max-width: 600px; display: flex; flex-direction: column; gap: 12px; }

        .staff_form input, .staff_form select, .staff_form textarea {
            width: 100%; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color);
            color: var(--text-light); border-radius: 6px; outline: none;
        }

        .adv_btn {
            background: var(--accent-gold); color: #000; padding: 12px; border: none;
            border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.3s;
        }

        .adv_btn:hover { background: var(--accent-hover); }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid var(--border-color); padding: 12px; text-align: left; }
        th { background: rgba(212, 175, 55, 0.15); color: var(--accent-gold); }
        .status-avail { color: #10B981; font-weight: bold; }
        .status-occ { color: #EF4444; font-weight: bold; }
        .restricted-msg { color: #EF4444; font-size: 15px; font-weight: 500; }

        .info-box {
            background: rgba(212, 175, 55, 0.1);
            border: 1px dashed var(--accent-gold);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            color: var(--accent-gold);
            display: none;
        }

        .select-status {
            background: var(--bg-color);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            padding: 6px 10px;
            border-radius: 4px;
            outline: none;
        }
    </style>
</head>
<body>

<div class="topbar">
    <h3> Welcome Staff: <?php echo ucwords($username) . ($staff_job_role ? " [$staff_job_role]" : "") . " [ ID = $user_id ]"; ?></h3>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Tab Navigation Buttons -->
<div class="tab_nav">
    <button class="tab_btn active" data-target="tab_checkin">
        <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <span>Guest Check-In</span>
    </button>
    <button class="tab_btn" data-target="tab_guest_checkout">
        <svg viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .89-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
        <span>Guest Check-Out</span>
    </button>
    <button class="tab_btn <?php echo $search_performed ? 'active' : ''; ?>" data-target="tab_guest_search">
        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 11.99 14 9.5 14z"/></svg>
        <span>Guest Search</span>
    </button>
    <button class="tab_btn" data-target="tab_checkout">
        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
        <span>Active Bookings</span>
    </button>
    <button class="tab_btn" data-target="tab_orders">
        <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
        <span>Pending Service Orders</span>
    </button>
    <button class="tab_btn" data-target="tab_rooms">
        <svg viewBox="0 0 24 24"><path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/></svg>
        <span>Room Status</span>
    </button>
    <button class="tab_btn" data-target="tab_hall_booking">
        <svg viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2zm-7 5h5v5h-5z"/></svg>
        <span>Hall Booking</span>
    </button>
</div>

<!-- TAB 1: WALK-IN CHECK-IN -->
<div class="tab_panel <?php echo !$search_performed ? 'active' : ''; ?>" id="tab_checkin">
    <h2 class="section_title">WALK-IN GUEST CHECK-IN</h2>
    <?php if($msg_checkin){ echo "<p style='color:var(--accent-gold); margin-bottom: 12px;'>$msg_checkin</p>"; } ?>

    <?php if ($can_manage_bookings): ?>
        <form class="staff_form" action="staff_dashboard.php" method="POST">
            <!-- Personal Info -->
            <input type="text" name="first_name" placeholder="Guest First Name" value="<?php echo htmlspecialchars($chk_fn); ?>" required>
            <input type="text" name="last_name" placeholder="Guest Last Name" value="<?php echo htmlspecialchars($chk_ln); ?>" required>
            <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($chk_em); ?>" required>
            <input type="text" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($chk_ph); ?>" required>

            <!-- Verification & Address Info -->
            <label style="color:var(--text-muted); font-weight: bold;">ID Card Type & Details:</label>
            <select name="id_card_type" required>
                <option value="">-- Select ID Type --</option>
                <option value="NID" <?php if($chk_id_type === 'NID') echo 'selected'; ?>>NID</option>
                <option value="Passport" <?php if($chk_id_type === 'Passport') echo 'selected'; ?>>Passport</option>
                <option value="Driving License" <?php if($chk_id_type === 'Driving License') echo 'selected'; ?>>Driving License</option>
            </select>
            <input type="text" name="id_card_number" placeholder="ID Card Number" value="<?php echo htmlspecialchars($chk_id_num); ?>" required>
            <textarea name="address" rows="2" placeholder="Guest Address"><?php echo htmlspecialchars($chk_address); ?></textarea>

            <button type="submit" name="btn_check_guest_exist" class="adv_btn" style="background:#10B981; color:#fff; margin-top:5px;">Check Guest Existence</button>
            
            <?php if (!empty($check_msg)): ?>
                <div style="font-size:14px; font-weight:bold; padding:8px 0;"><?php echo $check_msg; ?></div>
            <?php endif; ?>

            <?php if ($guest_check_performed && !$guest_exists): ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <label style="color:var(--accent-gold); font-weight: bold; margin-top: 5px;">Enter Login Credentials:</label>
                    <input type="text" name="guest_username" placeholder="Guest Login Username" required>
                    <input type="password" name="guest_password" placeholder="Guest Login Password" required>
                </div>
            <?php endif; ?>

            <label style="color:var(--text-muted); font-weight: bold; margin-top: 5px;">Select Available Room:</label>
            <select name="room_id" id="room_select" onchange="updateRoomCapacityInfo()" required>
                <option value="" data-capacity="0" data-price="0">-- Available Rooms --</option>
                <?php
                $r_sql = "SELECT r.room_id, r.room_number, rt.type_name, rt.base_price, rt.max_capacity 
                          FROM rooms r JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                          WHERE r.status = 'Available'";
                $r_res = mysqli_query($conn, $r_sql);
                while($r = mysqli_fetch_assoc($r_res)){
                    echo "<option value='".$r['room_id']."' data-capacity='".$r['max_capacity']."' data-price='".$r['base_price']."'>Room ".$r['room_number']." - ".$r['type_name']." ($".$r['base_price']."/night)</option>";
                }
                ?>
            </select>

            <div id="room_info_box" class="info-box">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> <strong>Selected Room Details:</strong> Max Capacity: <span id="capacity_val">0</span> Guest(s) | Price: $<span id="price_val">0</span>/night
            </div>

            <input type="number" name="total_guests" id="total_guests" placeholder="Total Guests Entering (e.g. 1, 2)" min="1" required>

            <label style="color:var(--text-muted)">Check-Out Date:</label>
            <input type="date" name="check_out" required>

            <button type="submit" name="walkin_checkin" class="adv_btn">Complete Check-In & Create Account</button>
        </form>
    <?php else: ?>
        <p class="restricted-msg"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Access Restricted: Only Managers and Receptionists are allowed to add new guest check-ins.</p>
    <?php endif; ?>
</div>

<!-- TAB: GUEST CHECK-OUT MENU -->
<div class="tab_panel" id="tab_guest_checkout">
    <h2 class="section_title">GUEST CHECK-OUT & BILLING SERVICES</h2>
    <?php if($msg_checkout){ echo "<p style='margin-bottom: 12px;'>$msg_checkout</p>"; } ?>

    <?php if ($can_manage_bookings): ?>
        <form class="staff_form" action="staff_dashboard.php" method="POST">
            <label style="color:var(--text-muted); font-weight: bold;">Select Active Guest Booking for Check-Out:</label>
            <select name="booking_id" required>
                <option value="">-- Select Active Booking --</option>
                <?php
                $active_b_sql = "SELECT b.booking_id, g.first_name, g.last_name, r.room_number,
                                 (SELECT IFNULL(SUM(so.total_cost), 0) 
                                  FROM service_orders so 
                                  WHERE so.booking_id = b.booking_id AND so.order_status = 'Completed') AS completed_service_total
                                 FROM bookings b
                                 JOIN guests g ON b.guest_id = g.guest_id
                                 JOIN rooms r ON b.room_id = r.room_id
                                 WHERE b.booking_status IN ('Confirmed', 'CheckedIn')";
                $active_b_res = mysqli_query($conn, $active_b_sql);
                
                while($ab = mysqli_fetch_assoc($active_b_res)){
                    echo "<option value='".$ab['booking_id']."'>
                            Booking #".$ab['booking_id']." - Guest: ".$ab['first_name']." ".$ab['last_name']." (Room ".$ab['room_number'].") | Completed Service Total: $".$ab['completed_service_total']."
                          </option>";
                }
                ?>
            </select>

            <button type="submit" name="process_checkout" class="adv_btn" style="background:#EF4444; color:#fff;" onclick="return confirm('Are you sure you want to check out this guest?');">Process Check-Out & Finalize Bill</button>
        </form>
    <?php else: ?>
        <p class="restricted-msg"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Access Restricted: Only Managers and Receptionists are allowed to process guest check-outs.</p>
    <?php endif; ?>
</div>

<!-- TAB 2: GUEST SEARCH -->
<div class="tab_panel <?php echo $search_performed ? 'active' : ''; ?>" id="tab_guest_search">
    <h2 class="section_title">GUEST LOOKUP</h2>
    <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 14px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M9 18h6m-5 3h4m-7-9.5A6 6 0 1 1 19 11.5c0 2.2-1.2 3.8-2 5H7c-.8-1.2-2-2.8-2-5z"/></svg> <i>Fill in any one or more boxes below to search. If any box matches, results will be displayed.</i>
    </p>

    <form class="staff_form" action="staff_dashboard.php" method="POST">
        <input type="text" name="search_first_name" placeholder="First Name" value="<?php echo htmlspecialchars($search_fn); ?>">
        <input type="text" name="search_last_name" placeholder="Last Name" value="<?php echo htmlspecialchars($search_ln); ?>">
        <input type="email" name="search_email" placeholder="Email Address" value="<?php echo htmlspecialchars($search_em); ?>">
        <input type="text" name="search_phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($search_ph); ?>">
        
        <button type="submit" name="search_guest" class="adv_btn">Search Guest Profile</button>
    </form>

    <?php if ($search_performed): ?>
        <div style="margin-top: 25px;">
            <h3 style="color: var(--accent-gold); font-size: 16px;">Search Results:</h3>
            <?php if (count($search_results) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Guest ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>ID Card Type</th>
                            <th>ID Card Number</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $g): ?>
                            <tr>
                                <td>#<?php echo $g['guest_id']; ?></td>
                                <td><?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($g['email']); ?></td>
                                <td><?php echo htmlspecialchars($g['phone']); ?></td>
                                <td><?php echo htmlspecialchars($g['id_card_type']); ?></td>
                                <td><?php echo htmlspecialchars($g['id_card_number']); ?></td>
                                <td><?php echo htmlspecialchars($g['address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #EF4444; margin-top: 10px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> No matching guest records found in the database for the given criteria!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- TAB 3: ACTIVE BOOKINGS -->
<div class="tab_panel" id="tab_checkout">
    <h2 class="section_title">ACTIVE BOOKINGS</h2>
    <table>
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Guest Name</th>
                <th>Room No</th>
                <th>Total Guests</th>
                <th>Check-In</th>
                <th>Check-Out</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $b_sql = "SELECT b.booking_id, g.first_name, g.last_name, r.room_number, b.total_guests, b.check_in_date, b.check_out_date, b.booking_status 
                      FROM bookings b 
                      JOIN guests g ON b.guest_id = g.guest_id 
                      JOIN rooms r ON b.room_id = r.room_id 
                      WHERE b.booking_status IN ('Confirmed', 'CheckedIn')";
            $b_res = mysqli_query($conn, $b_sql);
            if(mysqli_num_rows($b_res) > 0){
                while($b = mysqli_fetch_assoc($b_res)){
                    echo "<tr>
                            <td>#".$b['booking_id']."</td>
                            <td>".$b['first_name']." ".$b['last_name']."</td>
                            <td>".$b['room_number']."</td>
                            <td>".$b['total_guests']." Person(s)</td>
                            <td>".$b['check_in_date']."</td>
                            <td>".$b['check_out_date']."</td>
                            <td><span style='color:#10B981;'>".$b['booking_status']."</span></td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No active bookings found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 4: PENDING SERVICE ORDERS (VIEW BASED) -->
<div class="tab_panel" id="tab_orders">
    <h2 class="section_title">PENDING SERVICE ORDERS</h2>
    <?php if($msg_order_status){ echo "<p style='margin-bottom:12px;'>$msg_order_status</p>"; } ?>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Guest Name</th>
                <th>Phone</th>
                <th>Room No</th>
                <th>Service Name</th>
                <th>Quantity</th>
                <th>Total Cost</th>
                <th>Order Date</th>
                <th>Order Status</th>
                <th>Update Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // View: pending_service_orders
            $p_orders_sql = "SELECT * FROM pending_service_orders ORDER BY order_id DESC";
            $p_orders_res = mysqli_query($conn, $p_orders_sql);

            if($p_orders_res && mysqli_num_rows($p_orders_res) > 0){
                while($po = mysqli_fetch_assoc($p_orders_res)){
                    $room_display = !empty($po['room_number']) ? "Room " . $po['room_number'] : "N/A";
                    echo "<tr>
                            <td>#".$po['order_id']."</td>
                            <td>".htmlspecialchars($po['guest_name'])."</td>
                            <td>".htmlspecialchars($po['guest_phone'])."</td>
                            <td>".$room_display."</td>
                            <td>".htmlspecialchars($po['service_name'])."</td>
                            <td>".$po['quantity']."</td>
                            <td>$".$po['total_cost']."</td>
                            <td>".$po['order_date']."</td>
                            <td><span style='color:#D4AF37; font-weight:bold;'>".$po['order_status']."</span></td>
                            <td>
                                <form action='staff_dashboard.php' method='POST' style='display:flex; gap:6px;'>
                                    <input type='hidden' name='order_id' value='".$po['order_id']."'>
                                    <select name='order_status' class='select-status' required>
                                        <option value='Pending' selected>Pending</option>
                                        <option value='In Progress'>In Progress</option>
                                        <option value='Completed'>Completed</option>
                                        <option value='Cancelled'>Cancelled</option>
                                    </select>
                                    <button type='submit' name='update_order_status' class='adv_btn' style='padding:6px 12px; margin:0;'>Save</button>
                                </form>
                            </td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='10'>No pending service orders found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 5: ROOM STATUS OVERVIEW -->
<div class="tab_panel" id="tab_rooms">
    <h2 class="section_title">ROOM STATUS OVERVIEW</h2>
    <table>
        <thead>
            <tr>
                <th>Room Number</th>
                <th>Category</th>
                <th>Floor</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rs_sql = "SELECT r.room_number, rt.type_name, r.floor_number, r.status 
                       FROM rooms r JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                       ORDER BY r.room_number ASC";
            $rs_res = mysqli_query($conn, $rs_sql);
            while($rs = mysqli_fetch_assoc($rs_res)){
                $status_class = ($rs['status'] == 'Available') ? 'status-avail' : 'status-occ';
                echo "<tr>
                        <td>Room ".$rs['room_number']."</td>
                        <td>".$rs['type_name']."</td>
                        <td>Floor ".$rs['floor_number']."</td>
                        <td class='".$status_class."'>".$rs['status']."</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 6: HALL EVENT BOOKING -->
<div class="tab_panel" id="tab_hall_booking">
    <h2 class="section_title">BOOK BANQUET / CONFERENCE HALL</h2>
    <?php if($msg_hall_booking){ echo "<p style='color:var(--accent-gold); margin-bottom: 12px;'>$msg_hall_booking</p>"; } ?>

    <?php if ($can_manage_bookings): ?>
        <form class="staff_form" action="staff_dashboard.php" method="POST">
            <label style="color:var(--text-muted)">Select Hall:</label>
            <select name="hall_id" required>
                <option value="">-- Choose Hall --</option>
                <?php
                $h_res = mysqli_query($conn, "SELECT hall_id, hall_name, capacity FROM halls");
                while($h = mysqli_fetch_assoc($h_res)){
                    echo "<option value='".$h['hall_id']."'>".$h['hall_name']." (Capacity: ".$h['capacity'].")</option>";
                }
                ?>
            </select>

            <input type="text" name="event_title" placeholder="Event Title / Name" required>

            <label style="color:var(--text-muted)">Start Date & Time:</label>
            <input type="datetime-local" name="start_time" required>

            <label style="color:var(--text-muted)">End Date & Time:</label>
            <input type="datetime-local" name="end_time" required>

            <button type="submit" name="add_hall_booking" class="adv_btn">Submit Hall Booking Request</button>
        </form>
    <?php else: ?>
        <p class="restricted-msg"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Access Restricted: Only Managers and Receptionists are allowed to submit hall booking requests.</p>
    <?php endif; ?>
</div>

<script>
// Tab Navigation Script
document.querySelectorAll('.tab_btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.tab_btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.tab_panel').forEach(function(p){ p.classList.remove('active'); });
        document.getElementById(btn.getAttribute('data-target')).classList.add('active');
    });
});

// Dynamic Max Capacity Display Script
function updateRoomCapacityInfo() {
    var select = document.getElementById('room_select');
    var selectedOption = select.options[select.selectedIndex];
    
    var capacity = selectedOption.getAttribute('data-capacity');
    var price = selectedOption.getAttribute('data-price');
    var infoBox = document.getElementById('room_info_box');

    if (select.value !== "") {
        document.getElementById('capacity_val').innerText = capacity;
        document.getElementById('price_val').innerText = price;
        infoBox.style.display = 'block';

        var guestInput = document.getElementById('total_guests');
        guestInput.max = capacity;
    } else {
        infoBox.style.display = 'none';
    }
}
</script>

</body>
</html>