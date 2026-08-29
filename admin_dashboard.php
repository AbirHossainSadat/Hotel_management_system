<?php
session_start();

// Admin Session Validation
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php"); 
    exit;
}

require_once 'db.php';

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_type'];

// Feedback Messages
$msg_user = "";
$msg_room_type = "";
$msg_room = "";
$msg_location = "";
$msg_distance = "";
$msg_hall = "";
$msg_service = "";

// -------------------------------------------------------------
// 1. Add New Staff Account Logic
// -------------------------------------------------------------
if (isset($_POST['add_user'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $staff_role = mysqli_real_escape_string($conn, $_POST['staff_role']);
    $salary = floatval($_POST['salary']);
    
    $user_name = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = mysqli_real_escape_string($conn, $_POST['password']);
    $account_type = 'Staff';

    $check_user = mysqli_query($conn, "SELECT 1 FROM users WHERE username = '$user_name'");
    if (mysqli_num_rows($check_user) > 0) {
        $msg_user = "Error: Username '$user_name' already exists!";
    } else {
        $sql_staff = "INSERT INTO staff (first_name, last_name, role, phone, email, salary, hire_date) 
                      VALUES ('$first_name', '$last_name', '$staff_role', '$phone', '$email', '$salary', CURDATE())";
        
        if (mysqli_query($conn, $sql_staff)) {
            $new_staff_id = mysqli_insert_id($conn);
            
            $sql_user = "INSERT INTO users (staff_id, username, email, password, user_type) 
                        VALUES ('$new_staff_id', '$user_name', '$email', '$pass', '$account_type')";
            mysqli_query($conn, $sql_user);
            
            $msg_user = "Staff account created successfully!";
        } else {
            $msg_user = "Failed to add staff record.";
        }
    }
}

// -------------------------------------------------------------
// 2. Add Room Type Logic
// -------------------------------------------------------------
if (isset($_POST['add_room_type'])) {
    $type_name = mysqli_real_escape_string($conn, $_POST['type_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $base_price = floatval($_POST['base_price']);
    $max_capacity = intval($_POST['max_capacity']);

    $check_type = mysqli_query($conn, "SELECT 1 FROM room_types WHERE type_name = '$type_name'");
    if (mysqli_num_rows($check_type) > 0) {
        $msg_room_type = "Error: Room type '$type_name' already exists!";
    } else {
        $sql = "INSERT INTO room_types (type_name, description, base_price, max_capacity) 
                VALUES ('$type_name', '$description', '$base_price', '$max_capacity')";
        if (mysqli_query($conn, $sql)) {
            $msg_room_type = "Room Type '$type_name' created successfully!";
        } else {
            $msg_room_type = "Failed to add room type.";
        }
    }
}

// -------------------------------------------------------------
// 3. Add Hotel Room Logic
// -------------------------------------------------------------
if (isset($_POST['add_room'])) {
    $room_number = mysqli_real_escape_string($conn, $_POST['room_number']);
    $room_type_id = intval($_POST['room_type_id']);
    $floor_number = intval($_POST['floor_number']);

    $check_room = mysqli_query($conn, "SELECT 1 FROM rooms WHERE room_number = '$room_number'");
    if (mysqli_num_rows($check_room) > 0) {
        $msg_room = "Error: Room Number '$room_number' already exists!";
    } else {
        $sql = "INSERT INTO rooms (room_number, room_type_id, floor_number, status) 
                VALUES ('$room_number', '$room_type_id', '$floor_number', 'Available')";
        if (mysqli_query($conn, $sql)) {
            $msg_room = "Room '$room_number' added successfully!";
        } else {
            $msg_room = "Failed to add room.";
        }
    }
}

// -------------------------------------------------------------
// 4. Add Location Logic (For Dijkstra Graph Node)
// -------------------------------------------------------------
if (isset($_POST['add_location'])) {
    $location_name = mysqli_real_escape_string($conn, $_POST['location_name']);

    $check_loc = mysqli_query($conn, "SELECT 1 FROM locations WHERE location_name = '$location_name'");
    if (mysqli_num_rows($check_loc) > 0) {
        $msg_location = "Error: Location '$location_name' already exists!";
    } else {
        $sql = "INSERT INTO locations (location_name) VALUES ('$location_name')";
        if (mysqli_query($conn, $sql)) {
            $msg_location = "Location '$location_name' added successfully!";
        } else {
            $msg_location = "Failed to add location.";
        }
    }
}

// -------------------------------------------------------------
// 5. Add Location Distance Logic (For Dijkstra Edge Weight)
// -------------------------------------------------------------
if (isset($_POST['add_distance'])) {
    $from_loc = intval($_POST['from_location_id']);
    $to_loc = intval($_POST['to_location_id']);
    $distance = floatval($_POST['distance_km']);

    if ($from_loc == $to_loc) {
        $msg_distance = "Error: Source and Destination location cannot be the same!";
    } else {
        $sql = "INSERT INTO location_distances (from_location_id, to_location_id, distance_km) 
                VALUES ('$from_loc', '$to_loc', '$distance')";
        if (mysqli_query($conn, $sql)) {
            $msg_distance = "Distance connection saved successfully!";
        } else {
            $msg_distance = "Failed to save distance record.";
        }
    }
}

// -------------------------------------------------------------
// 6. Add Hall Logic (For Activity Selection Problem)
// -------------------------------------------------------------
if (isset($_POST['add_hall'])) {
    $hall_name = mysqli_real_escape_string($conn, $_POST['hall_name']);
    $capacity = intval($_POST['capacity']);

    $sql = "INSERT INTO halls (hall_name, capacity) VALUES ('$hall_name', '$capacity')";
    if (mysqli_query($conn, $sql)) {
        $msg_hall = "Hall '$hall_name' created successfully!";
    } else {
        $msg_hall = "Failed to add hall.";
    }
}

// -------------------------------------------------------------
// 7. Add Service Logic
// -------------------------------------------------------------
if (isset($_POST['add_service'])) {
    $service_name = mysqli_real_escape_string($conn, trim($_POST['service_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $price = floatval($_POST['price']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if (!empty($service_name) && $price >= 0) {
        $sql_service = "INSERT INTO services (service_name, description, price, status) 
                        VALUES ('$service_name', '$description', '$price', '$status')";
        if (mysqli_query($conn, $sql_service)) {
            $msg_service = "Service '$service_name' added successfully!";
        } else {
            $msg_service = "Failed to add service record.";
        }
    } else {
        $msg_service = "Error: Please enter a valid service name and price.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Admin Dashboard</title>
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

        .admin_form { max-width: 600px; display: flex; flex-direction: column; gap: 12px; }

        .admin_form input, .admin_form select, .admin_form textarea {
            width: 100%; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color);
            color: var(--text-light); border-radius: 6px; outline: none;
        }

        .adv_btn {
            background: var(--accent-gold); color: #000; padding: 12px; border: none;
            border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.3s;
        }

        .adv_btn:hover { background: var(--accent-hover); }

        .adv_msg { color: var(--accent-gold); font-weight: 600; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid var(--border-color); padding: 12px; text-align: left; }
        th { background: rgba(212, 175, 55, 0.15); color: var(--accent-gold); }
    </style>
</head>
<body>

<div class="topbar">
    <h3> Welcome <?php echo ucwords($role) . ": " . ucwords($username) . " [ ID = $user_id ]"; ?></h3>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Tab Navigation Sidebar with SVG Icons -->
<div class="tab_nav">
    <button class="tab_btn active" data-target="tab_user">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <span>Add Staff</span>
    </button>
    
    <button class="tab_btn" data-target="tab_room_type">
        <svg viewBox="0 0 24 24"><path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/></svg>
        <span>Room Categories</span>
    </button>
    
    <button class="tab_btn" data-target="tab_room">
        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM11 7h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z"/></svg>
        <span>Add Rooms</span>
    </button>

    <button class="tab_btn" data-target="tab_service">
        <svg viewBox="0 0 24 24"><path d="M20 7h-5V4c0-1.1-.9-2-2-2h-2c-1.1 0-2 .9-2 2v3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM11 4h2v3h-2V4zm9 16H4V9h16v11z"/></svg>
        <span>Add Service</span>
    </button>

    <button class="tab_btn" data-target="tab_service_analytics">
        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
        <span>Service Analytics</span>
    </button>

    <button class="tab_btn" data-target="tab_location">
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        <span>Add Location</span>
    </button>

    <button class="tab_btn" data-target="tab_distance">
        <svg viewBox="0 0 24 24"><path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5l3.5 3.5-3.5 3.5z"/></svg>
        <span>Location Distance</span>
    </button>

    <button class="tab_btn" data-target="tab_hall">
        <svg viewBox="0 0 24 24"><path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm14-12v7h3v-7h-3zm-5-7L2 6v2h19V6l-10-5z"/></svg>
        <span>Add Hall</span>
    </button>
</div>

<!-- TAB 1: ADD STAFF -->
<div class="tab_panel active" id="tab_user">
    <h2 class="section_title">ADD NEW STAFF MEMBER</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_user){ echo "<p class='adv_msg'>$msg_user</p>"; } ?>

        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="text" name="phone" placeholder="Phone Number" required>

        <label style="color: var(--text-muted);">Staff Job Role:</label>
        <select name="staff_role" required>
            <option value="">-- Select Job Role --</option>
            <option value="Manager">Manager</option>
            <option value="Receptionist">Receptionist</option>
            <option value="Housekeeping">Housekeeping</option>
            <option value="Chef">Chef</option>
        </select>

        <input type="number" step="0.01" name="salary" placeholder="Salary ($)" required>
        <hr style="border-color: var(--border-color); margin: 10px 0;">

        <input type="text" name="username" placeholder="Login Username" required>
        <input type="password" name="password" placeholder="Login Password" required>

        <button type="submit" name="add_user" class="adv_btn">Create Staff Account</button>
    </form>
</div>

<!-- TAB 2: ADD ROOM CATEGORY -->
<div class="tab_panel" id="tab_room_type">
    <h2 class="section_title">ADD ROOM CATEGORY</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_room_type){ echo "<p class='adv_msg'>$msg_room_type</p>"; } ?>

        <input type="text" name="type_name" placeholder="Category Name (e.g. Deluxe, Suite)" required>
        <textarea name="description" rows="3" placeholder="Category Description & Amenities"></textarea>
        <input type="number" step="0.01" name="base_price" placeholder="Base Price Per Night ($)" required>
        <input type="number" name="max_capacity" placeholder="Max Guest Capacity" required>

        <button type="submit" name="add_room_type" class="adv_btn">Create Category</button>
    </form>
</div>

<!-- TAB 3: ADD HOTEL ROOM -->
<div class="tab_panel" id="tab_room">
    <h2 class="section_title">ADD HOTEL ROOM</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_room){ echo "<p class='adv_msg'>$msg_room</p>"; } ?>

        <input type="text" name="room_number" placeholder="Room Number (e.g. 101, A-202)" required>

        <label style="color: var(--text-muted);">Select Room Category:</label>
        <select name="room_type_id" required>
            <option value="">-- Select Category --</option>
            <?php
            $rt_res = mysqli_query($conn, "SELECT room_type_id, type_name, base_price FROM room_types");
            while($rt_row = mysqli_fetch_assoc($rt_res)){
                echo "<option value='".$rt_row['room_type_id']."'>".$rt_row['type_name']." (\$$rt_row[base_price]/night)</option>";
            }
            ?>
        </select>

        <input type="number" name="floor_number" placeholder="Floor Number" required>

        <button type="submit" name="add_room" class="adv_btn">Add Room</button>
    </form>
</div>

<!-- TAB 4: ADD SERVICE -->
<div class="tab_panel" id="tab_service">
    <h2 class="section_title">ADD NEW SERVICE</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_service){ echo "<p class='adv_msg'>$msg_service</p>"; } ?>

        <input type="text" name="service_name" placeholder="Service Name (e.g. Laundry, Spa, Airport Shuttle)" required>
        <textarea name="description" rows="3" placeholder="Service Description"></textarea>
        <input type="number" step="0.01" name="price" placeholder="Price ($)" required>
        
        <label style="color: var(--text-muted);">Status:</label>
        <select name="status" required>
            <option value="Available">Available</option>
            <option value="Unavailable">Unavailable</option>
        </select>

        <button type="submit" name="add_service" class="adv_btn">Add Service</button>
    </form>
</div>

<!-- TAB 5: SERVICE INCOME ANALYTICS (VIEW RESULT) -->
<div class="tab_panel" id="tab_service_analytics">
    <h2 class="section_title">AVERAGE INCOME PER SERVICE ANALYTICS</h2>
    <table>
        <thead>
            <tr>
                <th>Service ID</th>
                <th>Service Name</th>
                <th>Total Orders</th>
                <th>Total Income</th>
                <th>Avg Income / Order</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $view_q = mysqli_query($conn, "SELECT * FROM average_income_per_service");
            if ($view_q && mysqli_num_rows($view_q) > 0) {
                while ($row = mysqli_fetch_assoc($view_q)) {
                    echo "<tr>
                            <td>#".$row['service_id']."</td>
                            <td>".htmlspecialchars($row['service_name'])."</td>
                            <td>".$row['total_orders']."</td>
                            <td>$".number_format($row['total_income'], 2)."</td>
                            <td><span style='color:var(--accent-gold); font-weight:bold;'>$".number_format($row['avg_income_per_order'], 2)."</span></td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No analytics data found in database view.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 6: ADD LOCATION (For Dijkstra Node) -->
<div class="tab_panel" id="tab_location">
    <h2 class="section_title">ADD LOCATION</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_location){ echo "<p class='adv_msg'>$msg_location</p>"; } ?>

        <input type="text" name="location_name" placeholder="Location Name (e.g. Hotel Gate, Airport, Beach)" required>

        <button type="submit" name="add_location" class="adv_btn">Add Location</button>
    </form>
</div>

<!-- TAB 7: ADD LOCATION DISTANCE (For Dijkstra Edge Weight) -->
<div class="tab_panel" id="tab_distance">
    <h2 class="section_title">ADD LOCATION DISTANCE</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_distance){ echo "<p class='adv_msg'>$msg_distance</p>"; } ?>

        <label style="color: var(--text-muted);">From Location (Source):</label>
        <select name="from_location_id" required>
            <option value="">-- Select Source --</option>
            <?php
            $loc_res1 = mysqli_query($conn, "SELECT location_id, location_name FROM locations");
            while($l1 = mysqli_fetch_assoc($loc_res1)){
                echo "<option value='".$l1['location_id']."'>".$l1['location_name']."</option>";
            }
            ?>
        </select>

        <label style="color: var(--text-muted);">To Location (Destination):</label>
        <select name="to_location_id" required>
            <option value="">-- Select Destination --</option>
            <?php
            $loc_res2 = mysqli_query($conn, "SELECT location_id, location_name FROM locations");
            while($l2 = mysqli_fetch_assoc($loc_res2)){
                echo "<option value='".$l2['location_id']."'>".$l2['location_name']."</option>";
            }
            ?>
        </select>

        <input type="number" step="0.01" name="distance_km" placeholder="Distance in KM (e.g. 5.50)" required>

        <button type="submit" name="add_distance" class="adv_btn">Save Distance Edge</button>
    </form>
</div>

<!-- TAB 8: ADD HALL (For Activity Selection) -->
<div class="tab_panel" id="tab_hall">
    <h2 class="section_title">ADD BANQUET / CONFERENCE HALL</h2>
    <form class="admin_form" action="admin_dashboard.php" method="POST">
        <?php if($msg_hall){ echo "<p class='adv_msg'>$msg_hall</p>"; } ?>

        <input type="text" name="hall_name" placeholder="Hall Name (e.g. Grand Ballroom)" required>
        <input type="number" name="capacity" placeholder="Guest Capacity (e.g. 200)" required>

        <button type="submit" name="add_hall" class="adv_btn">Add Hall</button>
    </form>
</div>

<script>
document.querySelectorAll('.tab_btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.tab_btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.tab_panel').forEach(function(p){ p.classList.remove('active'); });
        document.getElementById(btn.getAttribute('data-target')).classList.add('active');
    });
});
</script>

</body>
</html>