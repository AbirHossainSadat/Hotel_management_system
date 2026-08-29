<?php
session_start();

// Guest Session Validation
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'Guest') {
    header("Location: login.php");
    exit;
}

@mysqli_report(MYSQLI_REPORT_OFF);
require_once 'db.php';

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Fetch Guest Info Safely
$guest = null;
if (isset($conn) && $conn) {
    try {
        $user_query = @mysqli_query($conn, "SELECT g.* FROM users u JOIN guests g ON u.guest_id = g.guest_id WHERE u.user_id = '$user_id'");
        if ($user_query && mysqli_num_rows($user_query) > 0) {
            $guest = mysqli_fetch_assoc($user_query);
        }
    } catch (Throwable $e) {
        $guest = null;
    }
}

if (!$guest) {
    $guest = array(
        'guest_id' => $user_id,
        'first_name' => $_SESSION['username'],
        'last_name' => '(Guest)',
        'email' => $_SESSION['username'] . '@ahmanihotel.com',
        'phone' => '+1 (800) 555-0199',
        'id_card_type' => 'Passport',
        'id_card_number' => 'P-100234',
        'address' => '100 Luxury Avenue, Suite 400'
    );
}

$guest_id = $guest['guest_id'];
$msg_cancel = "";
$msg_order = "";
$msg_review = "";
$active_tab = 'tab_profile';

// Fetch Existing Review if any
$existing_review = null;
if (isset($conn) && $conn) {
    $rev_check = mysqli_query($conn, "SELECT * FROM reviews WHERE user_id = '$user_id'");
    if ($rev_check && mysqli_num_rows($rev_check) > 0) {
        $existing_review = mysqli_fetch_assoc($rev_check);
    }
}

if (isset($_POST['place_service_order'])) {
    $active_tab = 'tab_service_order';
} elseif (isset($_POST['search_location_btn']) || isset($_POST['find_route'])) {
    $active_tab = 'tab_route';
} elseif (isset($_POST['cancel_booking'])) {
    $active_tab = 'tab_history';
} elseif (isset($_POST['submit_review'])) {
    $active_tab = 'tab_review';
}

// -------------------------------------------------------------
// Service Order Logic (DB Handling)
// -------------------------------------------------------------
if (isset($_POST['place_service_order']) && isset($conn) && $conn) {
    try {
        $booking_id = intval($_POST['booking_id']);
        $service_id = intval($_POST['service_id']);
        $quantity   = intval($_POST['quantity']);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Get Service Price
        $service_query = @mysqli_query($conn, "SELECT price FROM services WHERE service_id = '$service_id' AND status = 'Available'");
        if ($service_query && $s_row = mysqli_fetch_assoc($service_query)) {
            $unit_price = floatval($s_row['price']);
            $total_cost = $unit_price * $quantity;

            // Insert into service_orders table
            $order_sql = "INSERT INTO service_orders (guest_id, booking_id, service_id, quantity, total_cost, order_status) 
                          VALUES ('$guest_id', '$booking_id', '$service_id', '$quantity', '$total_cost', 'Pending')";

            if (@mysqli_query($conn, $order_sql)) {
                $msg_order = "<p style='color:#10B981; font-weight:bold; margin-bottom:12px;'>Service order placed successfully!</p>";
            } else {
                $msg_order = "<p style='color:#EF4444; font-weight:bold; margin-bottom:12px;'>Failed to place order.</p>";
            }
        } else {
            $msg_order = "<p style='color:#EF4444; font-weight:bold; margin-bottom:12px;'>Invalid or Unavailable Service selected!</p>";
        }
    } catch (Throwable $e) {
        $msg_order = "<p style='color:#EF4444; font-weight:bold; margin-bottom:12px;'>Service order processing error.</p>";
    }
}

// -------------------------------------------------------------
// Booking Cancellation Logic (Retained in backend if needed)
// -------------------------------------------------------------
if (isset($_POST['cancel_booking']) && isset($conn) && $conn) {
    try {
        $cancel_booking_id = intval($_POST['booking_id']);

        $b_check = @mysqli_query($conn, "SELECT room_id FROM bookings WHERE booking_id = '$cancel_booking_id' AND guest_id = '$guest_id' AND booking_status = 'Confirmed'");
        if ($b_check && $b_row = mysqli_fetch_assoc($b_check)) {
            $c_room_id = $b_row['room_id'];

            @mysqli_query($conn, "UPDATE bookings SET booking_status = 'Cancelled' WHERE booking_id = '$cancel_booking_id'");
            @mysqli_query($conn, "UPDATE rooms SET status = 'Available' WHERE room_id = '$c_room_id'");

            $msg_cancel = "Booking #$cancel_booking_id cancelled successfully.";
        }
    } catch (Throwable $e) {
        $msg_cancel = "";
    }
}

// -------------------------------------------------------------
// Levenshtein / Edit Distance Search Algorithm Logic
// -------------------------------------------------------------
function findClosestLocations($conn, $searchTerm) {
    if (!$conn || empty(trim($searchTerm))) return array();

    $searchTerm = strtolower(trim($searchTerm));
    $all_locations = array();
    try {
        $res = @mysqli_query($conn, "SELECT location_id, location_name FROM locations");
        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $loc_name = strtolower($row['location_name']);
                
                // Custom Levenshtein / Edit Distance Logic
                $distance = levenshtein($searchTerm, $loc_name);
                
                // Exact or partial substring boost logic
                if (strpos($loc_name, $searchTerm) !== false) {
                    $distance -= 2; 
                }

                $all_locations[] = array(
                    'location_id' => $row['location_id'],
                    'location_name' => $row['location_name'],
                    'distance' => $distance
                );
            }
        }
    } catch (Throwable $e) {
        return array();
    }

    // Sort array by closest edit distance
    usort($all_locations, function($a, $b) {
        return $a['distance'] - $b['distance'];
    });

    // Return top 3 closest matches
    return array_slice($all_locations, 0, 3);
}

$search_suggestions = array();
$search_term = "";
if (isset($_POST['search_location_btn'])) {
    $search_term = $_POST['search_term'];
    $conn_ptr = isset($conn) ? $conn : null;
    $search_suggestions = findClosestLocations($conn_ptr, $search_term);
}

// -------------------------------------------------------------
// Fixed & Optimized Dijkstra's Algorithm Function
// -------------------------------------------------------------
function dijkstra($graph, $start, $target) {
    $distances = array();
    $previous = array();
    $visited = array();
    $nodes = new SplPriorityQueue();

    foreach ($graph as $node => $edges) {
        $distances[$node] = INF;
        $previous[$node] = null;
        $visited[$node] = false;
    }

    $distances[$start] = 0;
    $nodes->insert($start, -0.0);

    while (!$nodes->isEmpty()) {
        $smallest = $nodes->extract();

        if ($visited[$smallest]) {
            continue;
        }

        if ($smallest === $target) {
            $path = array();
            while (isset($previous[$smallest])) {
                $path[] = $smallest;
                $smallest = $previous[$smallest];
            }
            $path[] = $start;
            return array('distance' => $distances[$target], 'path' => array_reverse($path));
        }

        if ($distances[$smallest] === INF) {
            break;
        }

        $visited[$smallest] = true;

        if (isset($graph[$smallest])) {
            foreach ($graph[$smallest] as $neighbor => $cost) {
                if ($visited[$neighbor]) continue;

                $alt = $distances[$smallest] + $cost;
                if ($alt < $distances[$neighbor]) {
                    $distances[$neighbor] = $alt;
                    $previous[$neighbor] = $smallest;
                    $nodes->insert($neighbor, -$alt); 
                }
            }
        }
    }

    return array('distance' => INF, 'path' => array());
}

// Route Calculation Handler
$route_result = null;
$location_names = array();

if (isset($_POST['find_route']) && isset($conn) && $conn) {
    try {
        $start_node = intval($_POST['start_location']);
        $end_node = intval($_POST['end_location']);

        $graph = array();
        
        $loc_res = @mysqli_query($conn, "SELECT location_id, location_name FROM locations");
        if ($loc_res && mysqli_num_rows($loc_res) > 0) {
            while ($loc = mysqli_fetch_assoc($loc_res)) {
                $graph[$loc['location_id']] = array();
                $location_names[$loc['location_id']] = $loc['location_name'];
            }
        }

        $dist_res = @mysqli_query($conn, "SELECT * FROM location_distances");
        if ($dist_res && mysqli_num_rows($dist_res) > 0) {
            while ($d = mysqli_fetch_assoc($dist_res)) {
                $u = $d['from_location_id'];
                $v = $d['to_location_id'];
                $w = floatval($d['distance_km']);

                $graph[$u][$v] = $w;
                $graph[$v][$u] = $w;
            }
        }

        if ($start_node == $end_node) {
            $route_error = "Start and destination locations must be different!";
        } elseif (isset($graph[$start_node]) && isset($graph[$end_node])) {
            $route_result = dijkstra($graph, $start_node, $end_node);
        } else {
            $route_error = "Selected locations are not available in the network.";
        }
    } catch (Throwable $e) {
        $route_result = null;
    }
}

// -------------------------------------------------------------
// Guest Review Submission Logic
// -------------------------------------------------------------
if (isset($_POST['submit_review']) && isset($conn) && $conn) {
    $stars = intval($_POST['stars']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // Check if review already exists
    $check_review = mysqli_query($conn, "SELECT review_id FROM reviews WHERE user_id = '$user_id'");
    if ($check_review && mysqli_num_rows($check_review) > 0) {
        $row = mysqli_fetch_assoc($check_review);
        $review_id = $row['review_id'];
        $sql = "UPDATE reviews SET stars = '$stars', comment = '$comment', created_at = NOW() WHERE review_id = '$review_id'";
        if (mysqli_query($conn, $sql)) {
            $msg_review = "<p style='color:#10B981; font-weight:bold; margin-bottom:12px;'>Review updated successfully!</p>";
        } else {
            $msg_review = "<p style='color:#EF4444; font-weight:bold; margin-bottom:12px;'>Failed to update review.</p>";
        }
    } else {
        $sql = "INSERT INTO reviews (user_id, stars, comment, created_at) VALUES ('$user_id', '$stars', '$comment', NOW())";
        if (mysqli_query($conn, $sql)) {
            $msg_review = "<p style='color:#10B981; font-weight:bold; margin-bottom:12px;'>Review submitted successfully!</p>";
        } else {
            $msg_review = "<p style='color:#EF4444; font-weight:bold; margin-bottom:12px;'>Failed to submit review.</p>";
        }
    }

    // Refresh existing review record
    $rev_check = mysqli_query($conn, "SELECT * FROM reviews WHERE user_id = '$user_id'");
    if ($rev_check && mysqli_num_rows($rev_check) > 0) {
        $existing_review = mysqli_fetch_assoc($rev_check);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Dashboard - AHMANI HOTEL</title>
    <style>
        :root {
            --bg-color: #0A192F;
            --card-bg: rgba(17, 34, 64, 0.9);
            --accent-gold: #D4AF37;
            --accent-hover: #C5A880;
            --text-light: #F8F9FA;
            --text-muted: #CBD5E1;
            --border-color: rgba(212, 175, 55, 0.3);
            --gold-glow: rgba(212, 175, 55, 0.25);
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

        .tab_btn.active, .tab_btn:hover { background: var(--accent-gold); color: #000; }

        .tab_panel {
            display: none; background: var(--card-bg); border: 1px solid var(--border-color);
            padding: 25px; border-radius: 8px;
        }

        .tab_panel.active { display: block; }

        .section_title { color: var(--accent-gold); margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }

        .guest_form { max-width: 600px; display: flex; flex-direction: column; gap: 12px; }

        .guest_form select, .guest_form input[type="number"] {
            width: 100%; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color);
            color: var(--text-light); border-radius: 6px; outline: none; transition: 0.3s;
        }

        .guest_form select:focus, .guest_form input[type="number"]:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 10px var(--gold-glow);
        }

        .adv_btn {
            background: var(--accent-gold); color: #000; padding: 12px; border: none;
            border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.3s;
        }

        .adv_btn:hover { background: var(--accent-hover); }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid var(--border-color); padding: 12px; text-align: left; }
        th { background: rgba(212, 175, 55, 0.15); color: var(--accent-gold); }

        .profile-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;
        }
        .profile-card {
            background: var(--bg-color); border: 1px solid var(--border-color);
            padding: 15px; border-radius: 6px;
        }
        .profile-card span { color: var(--accent-gold); font-weight: bold; }

        .route-card {
            background: rgba(212, 175, 55, 0.1); border: 1px solid var(--accent-gold);
            padding: 15px; border-radius: 6px; margin-top: 20px;
        }
        .node-badge {
            background: var(--accent-gold); color: #000; padding: 4px 10px;
            border-radius: 12px; font-weight: bold; margin: 0 4px; display: inline-block;
        }

        .search-box-wrapper {
            background: rgba(10, 25, 47, 0.75);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            max-width: 600px;
            transition: border-color 0.3s ease;
        }

        .search-box-wrapper:hover {
            border-color: var(--accent-gold);
        }

        .search-box-title {
            color: var(--accent-gold);
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .search-input-group {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            background: #061121;
            border: 1px solid var(--border-color);
            color: var(--text-light);
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-input::placeholder {
            color: rgba(203, 213, 225, 0.5);
        }

        .search-input:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 12px var(--gold-glow);
            background: #0A192F;
        }

        .search-btn {
            background: linear-gradient(135deg, var(--accent-gold), #B38F24);
            color: #000;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .search-btn:hover {
            background: linear-gradient(135deg, var(--accent-hover), #D4AF37);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px var(--gold-glow);
        }

        .suggestion-container {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed var(--border-color);
        }

        .suggestion-chips-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .suggestion-chip {
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
        }

        .suggestion-chip:hover {
            background: rgba(212, 175, 55, 0.2);
            border-color: var(--accent-gold);
            transform: translateY(-2px);
        }

        .score-tag {
            color: var(--accent-gold);
            font-size: 11px;
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 2px;
        }
    </style>
</head>
<body>

<div class="topbar">
    <h3> Welcome Guest: <?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></h3>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Tab Navigation -->
<div class="tab_nav">
    <button class="tab_btn <?php echo ($active_tab === 'tab_profile') ? 'active' : ''; ?>" data-target="tab_profile">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>My Profile</span>
    </button>
    <button class="tab_btn <?php echo ($active_tab === 'tab_service_order') ? 'active' : ''; ?>" data-target="tab_service_order">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Order Service</span>
    </button>
    <button class="tab_btn <?php echo ($active_tab === 'tab_route') ? 'active' : ''; ?>" data-target="tab_route">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span>Find Shortest Route</span>
    </button>
    <button class="tab_btn <?php echo ($active_tab === 'tab_history') ? 'active' : ''; ?>" data-target="tab_history">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span>Booking History</span>
    </button>
    <button class="tab_btn <?php echo ($active_tab === 'tab_review') ? 'active' : ''; ?>" data-target="tab_review">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <span>Give Review</span>
    </button>
</div>

<!-- TAB 1: MY PROFILE -->
<div class="tab_panel <?php echo ($active_tab === 'tab_profile') ? 'active' : ''; ?>" id="tab_profile">
    <h2 class="section_title">MY PROFILE INFORMATION</h2>
    <div class="profile-grid">
        <div class="profile-card"><span>Guest ID:</span> #<?php echo $guest['guest_id']; ?></div>
        <div class="profile-card"><span>Full Name:</span> <?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></div>
        <div class="profile-card"><span>Email:</span> <?php echo htmlspecialchars($guest['email']); ?></div>
        <div class="profile-card"><span>Phone:</span> <?php echo htmlspecialchars($guest['phone']); ?></div>
        <div class="profile-card"><span>Identity Type:</span> <?php echo htmlspecialchars($guest['id_card_type']); ?></div>
        <div class="profile-card"><span>Identity No:</span> <?php echo htmlspecialchars($guest['id_card_number']); ?></div>
        <div class="profile-card" style="grid-column: 1 / -1;"><span>Address:</span> <?php echo htmlspecialchars($guest['address']); ?></div>
    </div>
</div>

<!-- TAB 2: ORDER SERVICE -->
<div class="tab_panel <?php echo ($active_tab === 'tab_service_order') ? 'active' : ''; ?>" id="tab_service_order">
    <h2 class="section_title">REQUEST HOTEL SERVICE</h2>
    <?php if ($msg_order) { echo $msg_order; } ?>

    <form class="guest_form" action="guest_dashboard.php" method="POST">
        <label style="color:var(--text-muted)">Select Your Active Room Booking:</label>
        <select name="booking_id" required>
            <option value="">-- Choose Active Booking --</option>
            <?php
            $b_active = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT b.booking_id, r.room_number FROM bookings b JOIN rooms r ON b.room_id = r.room_id WHERE b.guest_id = '$guest_id' AND b.booking_status IN ('Confirmed', 'CheckedIn')") : false;
            if ($b_active && mysqli_num_rows($b_active) > 0) {
                while ($b = mysqli_fetch_assoc($b_active)) {
                    echo "<option value='".$b['booking_id']."'>Booking #".$b['booking_id']." (Room ".$b['room_number'].")</option>";
                }
            } else {
                echo "<option value='' disabled>No Active Room Booking Found</option>";
            }
            ?>
        </select>

        <label style="color:var(--text-muted); margin-top:10px;">Select Service:</label>
        <select name="service_id" required>
            <option value="">-- Choose Service --</option>
            <?php
            $s_res = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT service_id, service_name, price FROM services WHERE status = 'Available'") : false;
            if ($s_res && mysqli_num_rows($s_res) > 0) {
                while ($s = mysqli_fetch_assoc($s_res)) {
                    echo "<option value='".$s['service_id']."'>".htmlspecialchars($s['service_name'])." ($".number_format($s['price'], 2).")</option>";
                }
            }
            ?>
        </select>

        <label style="color:var(--text-muted); margin-top:10px;">Quantity:</label>
        <input type="number" name="quantity" value="1" min="1" max="10" required>

        <button type="submit" name="place_service_order" class="adv_btn">Submit Order Request</button>
    </form>

    <h3 style="color:var(--accent-gold); margin-top:30px;">Your Ordered Services History</h3>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Booking ID</th>
                <th>Service Name</th>
                <th>Quantity</th>
                <th>Total Cost</th>
                <th>Status</th>
                <th>Order Time</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $ord_q = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT so.*, s.service_name 
                                          FROM service_orders so 
                                          JOIN services s ON so.service_id = s.service_id 
                                          WHERE so.guest_id = '$guest_id' 
                                          ORDER BY so.order_id DESC") : false;
            if ($ord_q && mysqli_num_rows($ord_q) > 0) {
                while ($o = mysqli_fetch_assoc($ord_q)) {
                    $st_col = ($o['order_status'] == 'Completed') ? '#10B981' : (($o['order_status'] == 'Cancelled') ? '#EF4444' : '#D4AF37');
                    echo "<tr>
                            <td>#".$o['order_id']."</td>
                            <td>#".$o['booking_id']."</td>
                            <td>".htmlspecialchars($o['service_name'])."</td>
                            <td>".$o['quantity']."</td>
                            <td>$".number_format($o['total_cost'], 2)."</td>
                            <td><span style='color:$st_col; font-weight:bold;'>".$o['order_status']."</span></td>
                            <td>".$o['order_date']."</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No service orders placed yet.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 3: FIND SHORTEST ROUTE (DIJKSTRA & EDIT DISTANCE SEARCH) -->
<div class="tab_panel <?php echo ($active_tab === 'tab_route') ? 'active' : ''; ?>" id="tab_route">
    <h2 class="section_title">SHORTEST ROUTE FINDER</h2>

    <div class="search-box-wrapper">
        <div class="search-box-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Search Place Name
        </div>
        <form action="guest_dashboard.php" method="POST" class="search-input-group">
            <input type="text" name="search_term" class="search-input" placeholder="Type place name (e.g. Airprt, Btanical)..." value="<?php echo htmlspecialchars($search_term); ?>" required>
            <button type="submit" name="search_location_btn" class="search-btn">Search Match</button>
        </form>

        <?php if (!empty($search_suggestions)): ?>
            <div class="suggestion-container">
                <p style="font-size: 13px; color: var(--text-muted);">Closest Matches Found:</p>
                <div class="suggestion-chips-wrapper">
                    <?php foreach ($search_suggestions as $match): ?>
                        <span class="suggestion-chip">
                            <strong><?php echo htmlspecialchars($match['location_name']); ?></strong> 
                            <span class="score-tag">Dist: <?php echo $match['distance']; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif(isset($_POST['search_location_btn'])): ?>
            <p style="color:#EF4444; font-size:13px; margin-top:12px;">No close matches found!</p>
        <?php endif; ?>
    </div>

    <?php if (isset($route_error)) { echo "<p style='color:#EF4444; margin-bottom: 12px;'>$route_error</p>"; } ?>

    <form class="guest_form" action="guest_dashboard.php" method="POST">
        <label style="color:var(--text-muted)">Select Starting Location:</label>
        <select name="start_location" required>
            <option value="">-- Choose Start Node --</option>
            <?php
            $loc_q1 = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT * FROM locations ORDER BY location_name ASC") : false;
            if ($loc_q1 && mysqli_num_rows($loc_q1) > 0) {
                while ($l = mysqli_fetch_assoc($loc_q1)) {
                    $selected = (isset($_POST['start_location']) && $_POST['start_location'] == $l['location_id']) ? 'selected' : '';
                    echo "<option value='".$l['location_id']."' $selected>".htmlspecialchars($l['location_name'])."</option>";
                }
            }
            ?>
        </select>

        <label style="color:var(--text-muted)">Select Destination Location:</label>
        <select name="end_location" required>
            <option value="">-- Choose Destination Node --</option>
            <?php
            $loc_q2 = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT * FROM locations ORDER BY location_name ASC") : false;
            if ($loc_q2 && mysqli_num_rows($loc_q2) > 0) {
                while ($l = mysqli_fetch_assoc($loc_q2)) {
                    $selected = (isset($_POST['end_location']) && $_POST['end_location'] == $l['location_id']) ? 'selected' : '';
                    
                    if(empty($selected) && !empty($search_suggestions) && $search_suggestions[0]['location_id'] == $l['location_id']) {
                        $selected = 'selected';
                    }

                    echo "<option value='".$l['location_id']."' $selected>".htmlspecialchars($l['location_name'])."</option>";
                }
            }
            ?>
        </select>

        <button type="submit" name="find_route" class="adv_btn">Find Optimal Route</button>
    </form>

    <?php if ($route_result): ?>
        <div class="route-card">
            <?php if ($route_result['distance'] === INF || empty($route_result['path'])): ?>
                <h4 style="color:#EF4444;">No path found between selected locations.</h4>
            <?php else: ?>
                <h3 style="color:var(--accent-gold); margin-bottom:10px;">Optimal Path Result:</h3>
                <p style="font-size: 16px; margin-bottom: 8px;">
                    <strong>Path Nodes:</strong>
                    <?php
                    $names = array();
                    foreach ($route_result['path'] as $node_id) {
                        $loc_name = isset($location_names[$node_id]) ? $location_names[$node_id] : "Unknown";
                        $names[] = "<span class='node-badge'>" . htmlspecialchars($loc_name) . "</span>";
                    }
                    $arrow_svg = " <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='var(--accent-gold)' stroke-width='2' style='vertical-align:-2px; margin:0 4px;'><path d='M5 12h14M12 5l7 7-7 7'/></svg> ";
                    echo implode($arrow_svg, $names);
                    ?>
                </p>
                <p style="font-size: 16px;">
                    <strong>Total Distance:</strong> 
                    <span style="color:var(--accent-gold); font-weight:bold; font-size:18px;">
                        <?php echo $route_result['distance']; ?> KM
                    </span>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- TAB 4: BOOKING HISTORY -->
<div class="tab_panel <?php echo ($active_tab === 'tab_history') ? 'active' : ''; ?>" id="tab_history">
    <h2 class="section_title">MY BOOKINGS HISTORY</h2>
    <?php if($msg_cancel){ echo "<p style='color:var(--accent-gold); margin-bottom: 12px;'>$msg_cancel</p>"; } ?>

    <table>
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Room No</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Guests</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $h_res = (isset($conn) && $conn) ? @mysqli_query($conn, "SELECT b.booking_id, r.room_number, b.check_in_date, b.check_out_date, b.total_guests, b.booking_status 
                      FROM bookings b 
                      JOIN rooms r ON b.room_id = r.room_id 
                      WHERE b.guest_id = '$guest_id' 
                      ORDER BY b.booking_id DESC") : false;

            if ($h_res && mysqli_num_rows($h_res) > 0) {
                while($h = mysqli_fetch_assoc($h_res)) {
                    $color = ($h['booking_status'] == 'Confirmed' || $h['booking_status'] == 'CheckedIn') ? '#10B981' : '#EF4444';
                    echo "<tr>
                            <td>#".$h['booking_id']."</td>
                            <td>Room ".$h['room_number']."</td>
                            <td>".$h['check_in_date']."</td>
                            <td>".$h['check_out_date']."</td>
                            <td>".$h['total_guests']." Person(s)</td>
                            <td><span style='color:$color; font-weight:bold;'>".$h['booking_status']."</span></td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No bookings found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- TAB 5: GIVE REVIEW -->
<div class="tab_panel <?php echo ($active_tab === 'tab_review') ? 'active' : ''; ?>" id="tab_review">
    <h2 class="section_title">SUBMIT GUEST EXPERIENCE</h2>
    <?php if ($msg_review) { echo $msg_review; } ?>

    <form class="guest_form" action="guest_dashboard.php" method="POST">
        <label style="color:var(--text-muted)">Star Rating (1-5):</label>
        <select name="stars" required>
            <?php
            $current_stars = $existing_review ? intval($existing_review['stars']) : 5;
            for ($i = 5; $i >= 1; $i--) {
                $sel = ($current_stars === $i) ? 'selected' : '';
                echo "<option value='$i' $sel>" . str_repeat('★', $i) . " ($i Star" . ($i > 1 ? 's' : '') . ")</option>";
            }
            ?>
        </select>

        <label style="color:var(--text-muted); margin-top:10px;">Review Comment:</label>
        <textarea name="comment" rows="6" style="width:100%; padding:12px; background:var(--bg-color); border:1px solid var(--border-color); color:var(--text-light); border-radius:6px; outline:none; transition:0.3s; font-family:inherit; resize:vertical;" placeholder="Share your experience at our hotel..." required><?php echo $existing_review ? htmlspecialchars($existing_review['comment']) : ''; ?></textarea>

        <button type="submit" name="submit_review" class="adv_btn">
            <?php echo $existing_review ? 'Update My Review' : 'Submit Review'; ?>
        </button>
    </form>
</div>

<script>
document.querySelectorAll('.tab_btn').forEach(function(btn){
    btn.addEventListener('click', function(e){
        e.preventDefault();
        var targetId = this.getAttribute('data-target');
        document.querySelectorAll('.tab_btn').forEach(function(b){ b.classList.remove('active'); });
        this.classList.add('active');
        document.querySelectorAll('.tab_panel').forEach(function(p){ p.classList.remove('active'); });
        var panel = document.getElementById(targetId);
        if (panel) {
            panel.classList.add('active');
        }
    });
});
</script>

</body>
</html>