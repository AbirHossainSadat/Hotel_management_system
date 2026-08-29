<?php
session_start();

require_once 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['username']);
$user_name = $is_logged_in ? $_SESSION['username'] : '';
$user_type = $is_logged_in ? $_SESSION['user_type'] : '';

$dashboard_link = "login.php";
if ($is_logged_in) {
	if ($user_type === 'Admin') {
		$dashboard_link = "admin_dashboard.php";
	} else if ($user_type === 'Staff') {
		$dashboard_link = "staff_dashboard.php";
	} else {
		$dashboard_link = "guest_dashboard.php";
	}
}

// -------------------------------------------------------------
// Levenshtein Search Algorithm
// -------------------------------------------------------------
function findClosestLocationsIndex($conn, $searchTerm)
{
	if (empty(trim($searchTerm)))
		return array();

	$searchTerm = strtolower(trim($searchTerm));
	$all_locations = array();

	$res = mysqli_query($conn, "SELECT location_id, location_name FROM locations");
	if ($res && mysqli_num_rows($res) > 0) {
		while ($row = mysqli_fetch_assoc($res)) {
			$loc_name = strtolower($row['location_name']);
			$distance = levenshtein($searchTerm, $loc_name);

			if (strpos($loc_name, $searchTerm) !== false) {
				$distance -= 2;
			}

			$all_locations[] = array(
				'location_id' => $row['location_id'],
				'location_name' => $row['location_name'],
				'distance' => $distance
			);
		}

		usort($all_locations, function ($a, $b) {
			return $a['distance'] - $b['distance'];
		});

		return array_slice($all_locations, 0, 3);
	}
	return array();
}

$search_suggestions = array();
$search_term = "";
if (isset($_POST['search_location_btn'])) {
	$search_term = $_POST['search_term'];
	$search_suggestions = findClosestLocationsIndex($conn, $search_term);
}

// -------------------------------------------------------------
// Dijkstra's Algorithm
// -------------------------------------------------------------
function dijkstraIndex($graph, $start, $target)
{
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

		if (isset($visited[$smallest]) && $visited[$smallest]) {
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

		if (!isset($distances[$smallest]) || $distances[$smallest] === INF) {
			break;
		}

		$visited[$smallest] = true;

		if (isset($graph[$smallest])) {
			foreach ($graph[$smallest] as $neighbor => $cost) {
				if (isset($visited[$neighbor]) && $visited[$neighbor])
					continue;

				$alt = $distances[$smallest] + $cost;
				if (!isset($distances[$neighbor]) || $alt < $distances[$neighbor]) {
					$distances[$neighbor] = $alt;
					$previous[$neighbor] = $smallest;
					$nodes->insert($neighbor, -$alt);
				}
			}
		}
	}

	return array('distance' => INF, 'path' => array());
}

$route_result = null;
$location_names = array();
$route_error = "";

if (isset($_POST['find_route'])) {
	$start_node = intval($_POST['start_location']);
	$end_node = intval($_POST['end_location']);

	$graph = array();

	$loc_res = mysqli_query($conn, "SELECT location_id, location_name FROM locations");
	if ($loc_res) {
		while ($loc = mysqli_fetch_assoc($loc_res)) {
			$graph[$loc['location_id']] = array();
			$location_names[$loc['location_id']] = $loc['location_name'];
		}
	}

	$dist_res = mysqli_query($conn, "SELECT * FROM location_distances");
	if ($dist_res) {
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
		$route_result = dijkstraIndex($graph, $start_node, $end_node);
	} else {
		$route_error = "Selected locations are not connected in the database graph.";
	}
}

// -------------------------------------------------------------
// Check Availability Dynamic Logic
// -------------------------------------------------------------
$availability_results = null;
$searched = false;
$check_in_val = '';
$check_out_val = '';
$selected_category = '';
$selected_guests = 2;

if (isset($_POST['check_availability_btn'])) {
	$searched = true;
	$check_in_val = $_POST['check_in_date'];
	$check_out_val = $_POST['check_out_date'];
	$selected_category = $_POST['room_category'];
	$selected_guests = intval($_POST['guest_count']);

	$sql = "SELECT r.room_id, r.room_number, rt.type_name, rt.base_price, rt.description, rt.max_capacity
			FROM rooms r
			JOIN room_types rt ON r.room_type_id = rt.room_type_id
			WHERE r.status = 'Available'
			AND rt.max_capacity >= $selected_guests";

	if ($selected_category !== "") {
		$sql .= " AND rt.type_name = '" . mysqli_real_escape_string($conn, $selected_category) . "'";
	}

	$sql .= " AND r.room_id NOT IN (
		SELECT b.room_id FROM bookings b
		WHERE b.booking_status IN ('Confirmed', 'CheckedIn')
		AND b.check_in_date < '" . mysqli_real_escape_string($conn, $check_out_val) . "'
		AND b.check_out_date > '" . mysqli_real_escape_string($conn, $check_in_val) . "'
	)";

	$availability_results = mysqli_query($conn, $sql);
}




//service er logo
function getServiceIconSvg($service_name)
{
	$name = strtolower($service_name);
	if (strpos($name, 'dine') !== false || strpos($name, 'food') !== false || strpos($name, 'breakfast') !== false || strpos($name, 'restaurant') !== false || strpos($name, 'dining') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/></svg>';
	} elseif (strpos($name, 'spa') !== false || strpos($name, 'massage') !== false || strpos($name, 'wellness') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
	} elseif (strpos($name, 'trans') !== false || strpos($name, 'shuttle') !== false || strpos($name, 'car') !== false || strpos($name, 'airport') !== false || strpos($name, 'cab') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
	} elseif (strpos($name, 'wifi') !== false || strpos($name, 'internet') !== false || strpos($name, 'net') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>';
	} elseif (strpos($name, 'gym') !== false || strpos($name, 'fit') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5h11M6.5 17.5h11M2 12h20M4 9v6M20 9v6"/></svg>';
	} elseif (strpos($name, 'laundry') !== false || strpos($name, 'clean') !== false || strpos($name, 'wash') !== false) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 00-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.47a1 1 0 00.99.84H6v10a2 2 0 002 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.47a2 2 0 00-1.34-2.23z"/></svg>';
	} else {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><line x1="12" y1="2" x2="12" y2="4"></line></svg>';
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description"
		content="Welcome to AHMANI HOTEL & RESORT - Unmatched luxury accommodation, fine dining, banquet halls, and intelligent hospitality navigation.">
	<meta name="keywords"
		content="Ahmani Hotel, Luxury Hotel, Suite Booking, Resort, Dijkstra Route Navigation, Hotel Management">
	<title>AHMANI HOTEL & RESORT - Luxury Hospitality & Smart Booking</title>

	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link
		href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Poppins:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap"
		rel="stylesheet">

	<link rel="stylesheet" href="index.css">

	
</head>

<body>

	<!-- ------------------------------------------------------------- -->
	<!-- Navigation Bar -->
	<!-- ------------------------------------------------------------- -->
	<nav class="navbar">
		<a href="index.php" class="navbar-brand">
			<img src="logo.svg" alt="AHMANI HOTEL Logo">
			<span class="brand-title">AHMANI HOTEL</span>
		</a>

		<!-- <button class="mobile-toggle" onclick="toggleMenu()">☰</button> -->

		<ul class="nav-menu" id="navMenu">
			<li><a href="#home" class="nav-link active">Home</a></li>
			<li><a href="#rooms" class="nav-link">Suites &amp; Rooms</a></li>
			<li><a href="#services" class="nav-link">Services</a></li>
			<li><a href="#halls" class="nav-link">Banquet Halls</a></li>
		</ul>

		<div class="nav-actions">
			<?php if ($is_logged_in): ?>
				<span class="user-welcome-tag"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
						stroke="var(--accent-gold)" stroke-width="2" style="vertical-align:-2px; margin-right:4px;">
						<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
						<circle cx="12" cy="7" r="4" />
					</svg><?php echo htmlspecialchars($user_name); ?></span>
				<a href="<?php echo $dashboard_link; ?>" class="btn-primary">Dashboard</a>
				<a href="logout.php" class="btn-outline">Logout</a>
			<?php else: ?>
				<a href="login.php" class="btn-outline">Sign In</a>
				<a href="login.php" class="btn-primary">Book Now</a>
			<?php endif; ?>
		</div>
	</nav>

	<!-- ------------------------------------------------------------- -->
	<!-- Hero Section -->
	<!-- ------------------------------------------------------------- -->
	<section class="hero-section" id="home">
		<div class="hero-badge"><span
				style="display:inline-block; width:7px; height:7px; background:var(--accent-gold); border-radius:50%; margin-right:8px; vertical-align:middle;"></span>Unmatched
			Luxury &amp; Algorithmic Hospitality</div>
		<h1 class="hero-title">Experience Timeless Elegance at <span>AHMANI HOTEL</span></h1>
		<p class="hero-subtitle">Discover world-class luxury suites, gourmet dining, and intelligent navigation powered
			by graph algorithms for an effortless stay.</p>

		<div class="hero-buttons">
			<a href="#rooms" class="btn-primary" style="padding: 14px 32px; font-size: 16px;">Explore Suites</a>
			<a href="<?php echo $dashboard_link; ?>" class="btn-outline"
				style="padding: 14px 32px; font-size: 16px;">Guest Portal</a>
		</div>

		<!-- Quick Availability Check Form -->
		<div class="hero-search-container">
			<form action="#availability-results" method="POST" class="hero-search-grid">
				<div class="search-group">
					<label>Check-In Date</label>
					<input type="date" name="check_in_date" required id="checkInDate"
						value="<?php echo htmlspecialchars($check_in_val); ?>">
				</div>
				<div class="search-group">
					<label>Check-Out Date</label>
					<input type="date" name="check_out_date" required id="checkOutDate"
						value="<?php echo htmlspecialchars($check_out_val); ?>">
				</div>
				<div class="search-group">
					<label>Room Category</label>
					<select name="room_category">
						<option value="">All Categories</option>
						<?php
						$sql = "SELECT type_name, base_price FROM room_types";
						$result = mysqli_query($conn, $sql);
						while ($row = mysqli_fetch_assoc($result)) {
							$selected = ($selected_category === $row['type_name']) ? 'selected' : '';
							?>
							<option value="<?php echo $row['type_name']; ?>" <?php echo $selected; ?>>
								<?php echo htmlspecialchars($row['type_name']); ?>
								($<?php echo number_format($row['base_price'], 2); ?>)
							</option>
						<?php } ?>
					</select>
				</div>
				<div class="search-group">
					<label>Guests</label>
					<select name="guest_count">
						<option value="1" <?php echo ($selected_guests === 1) ? 'selected' : ''; ?>>1 Guest</option>
						<option value="2" <?php echo ($selected_guests === 2) ? 'selected' : ''; ?>>2 Guests</option>
						<option value="3" <?php echo ($selected_guests === 3) ? 'selected' : ''; ?>>3 Guests</option>
						<option value="4" <?php echo ($selected_guests === 4) ? 'selected' : ''; ?>>4+ Guests</option>
					</select>
				</div>
				<button type="submit" name="check_availability_btn" class="btn-primary"
					style="width:100%; padding:13px;">Check Availability</button>
			</form>
		</div>
	</section>

	<?php if ($searched): ?>
		<!-- ------------------------------------------------------------- -->
		<!-- Search Results Section -->
		<!-- ------------------------------------------------------------- -->
		<section class="section" id="availability-results"
			style="padding-top: 40px; border-bottom: 1px solid var(--border-color);">
			<div class="section-header">
				<span class="section-tag">Search Results</span>
				<h2 class="main-title">Available Suites (<?php echo htmlspecialchars($check_in_val); ?> to
					<?php echo htmlspecialchars($check_out_val); ?>)
				</h2>
			</div>

			<div class="rooms-grid">
				<?php
				if ($availability_results && mysqli_num_rows($availability_results) > 0) {
					while ($row = mysqli_fetch_assoc($availability_results)) {
						?>
						<div class="room-card">
							<div class="room-img-container">
								<img src="hotel_bg.jpg" alt="<?php echo htmlspecialchars($row['type_name']); ?>" class="room-img">
								<span class="room-badge">Room <?php echo htmlspecialchars($row['room_number']); ?> Available</span>
							</div>
							<div class="room-body">
								<h3 class="room-name"><?php echo htmlspecialchars($row['type_name']); ?></h3>
								<div class="room-price">$<?php echo number_format($row['base_price'], 2); ?> <span>/ Night</span>
								</div>
								<p class="room-desc"><?php echo htmlspecialchars($row['description']); ?></p>
								<div class="room-amenities">
									<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
											<path
												d="M2 4v16M2 8h20v12M2 17h20M6 8V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M13 8V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2" />
										</svg> King Bed</div>
									<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
											<path
												d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01" />
										</svg> Fiber Wi-Fi</div>
									<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
											<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
											<circle cx="9" cy="7" r="4" />
											<path d="M23 21v-2a4 4 0 0 0-3-3.87" />
											<path d="M16 3.13a4 4 0 0 1 0 7.75" />
										</svg> Max Guests: <?php echo $row['max_capacity']; ?></div>
								</div>
								<a href="login.php" class="btn-primary" style="text-align:center; display:block;">Book Room
									<?php echo htmlspecialchars($row['room_number']); ?></a>
							</div>
						</div>
					<?php }
				} else {
					?>
					<div
						style="grid-column: 1 / -1; text-align: center; padding: 40px; background: rgba(255,255,255,0.02); border: 1px dashed var(--border-color); border-radius: 8px;">
						<p style="color: var(--text-muted); font-size: 16px; margin: 0;">No suites are currently available
							matching your selected date range or category.</p>
					</div>
				<?php } ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ------------------------------------------------------------- -->
	<!-- Key Statistics -->
	<!-- ------------------------------------------------------------- -->
	<section class="stats-section">
		<div class="stats-container">
			<div>
				<div class="stat-number">5 ★★★★★</div>
				<div class="stat-label">Luxury Hotel Rating</div>
			</div>
			<div>
				<div class="stat-number">120+</div>
				<div class="stat-label">Deluxe Rooms &amp; Suites</div>
			</div>
			<div>
				<div class="stat-number">99.8%</div>
				<div class="stat-label">Guest Satisfaction</div>
			</div>

		</div>
	</section>

	<!-- ------------------------------------------------------------- -->
	<!-- Featured Rooms Section -->
	<!-- ------------------------------------------------------------- -->
	<section class="section" id="rooms">
		<div class="section-header">
			<span class="section-tag">Luxury Accommodation</span>
			<h2 class="main-title">Featured Suites &amp; Rooms</h2>
		</div>

		<div class="rooms-grid">
			<?php
			$sql = "SELECT * FROM room_types";
			$result = mysqli_query($conn, $sql);
			while ($row = mysqli_fetch_assoc($result)) {
				?>
				<div class="room-card">
					<div class="room-img-container">
						<img src="hotel_bg.jpg" alt="<?php echo htmlspecialchars($row['type_name']); ?>" class="room-img">
						<span class="room-badge">Featured Category</span>
					</div>
					<div class="room-body">
						<h3 class="room-name"><?php echo htmlspecialchars($row['type_name']); ?></h3>
						<div class="room-price">$<?php echo number_format($row['base_price'], 2); ?> <span>/ Night</span>
						</div>
						<p class="room-desc"><?php echo htmlspecialchars($row['description']); ?></p>
						<div class="room-amenities">
							<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
									stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
									<path
										d="M2 4v16M2 8h20v12M2 17h20M6 8V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M13 8V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2" />
								</svg> King Bed</div>
							<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
									stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
									<path
										d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01" />
								</svg> Fiber Wi-Fi</div>
							<div class="amenity-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
									stroke="var(--accent-gold)" stroke-width="2" style="margin-right:4px;">
									<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
									<circle cx="9" cy="7" r="4" />
									<path d="M23 21v-2a4 4 0 0 0-3-3.87" />
									<path d="M16 3.13a4 4 0 0 1 0 7.75" />
								</svg> Capacity: <?php echo $row['max_capacity']; ?> Guests</div>
						</div>
						<a href="login.php" class="btn-outline" style="text-align:center;">Book Suite</a>
					</div>
				</div>
			<?php } ?>
		</div>
	</section>

	<!-- ------------------------------------------------------------- -->
	<!-- Exclusive Hotel Services -->
	<!-- ------------------------------------------------------------- -->
	<section class="section" id="services">
		<div class="section-header">
			<span class="section-tag">Unrivaled Hospitality</span>
			<h2 class="main-title">Services &amp; Amenities</h2>
		</div>

		<div class="services-grid">
			<?php
			$sql = "SELECT * FROM services WHERE status = 'Available'";
			$result = mysqli_query($conn, $sql);
			while ($row = mysqli_fetch_assoc($result)) {
				?>
				<div class="service-card">
					<div class="service-icon">
						<?php echo getServiceIconSvg($row['service_name']); ?>
					</div>
					<h3 class="service-title"><?php echo htmlspecialchars($row['service_name']); ?></h3>
					<div class="service-price">$<?php echo number_format($row['price'], 2); ?></div>
					<p class="service-desc"><?php echo htmlspecialchars($row['description']); ?></p>
				</div>
			<?php } ?>
		</div>
	</section>


	<!-- ------------------------------------------------------------- -->
	<!-- Banquet & Event Halls -->
	<!-- ------------------------------------------------------------- -->
	<section class="section" id="halls">
		<div class="section-header">
			<span class="section-tag">Grand Celebrations &amp; Conferences</span>
			<h2 class="main-title">Banquet &amp; Event Halls</h2>
		</div>

		<div class="halls-grid">
			<?php
			$sql = "SELECT * FROM halls";
			$result = mysqli_query($conn, $sql);
			while ($row = mysqli_fetch_assoc($result)) {
				?>
				<div class="hall-card">
					<h3 class="hall-name"><?php echo htmlspecialchars($row['hall_name']); ?></h3>
					<span class="hall-capacity">Capacity: Up to <?php echo $row['capacity']; ?> Guests</span>
					<p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Spacious luxury hall equipped
						with high-tech acoustic systems, lighting, and custom seating.</p>
					<a href="login.php" class="btn-outline" style="display:inline-block;">Reserve Hall</a>
				</div>
			<?php } ?>
		</div>
	</section>


	<!-- ------------------------------------------------------------- -->
	<!-- Testimonials -->
	<!-- ------------------------------------------------------------- -->
	<section class="section">
		<div class="section-header">
			<span class="section-tag">Guest Experiences</span>
			<h2 class="main-title">What Our Guests Say</h2>
		</div>

		<div class="testimonials-grid">
			<?php
			$sql = "SELECT 
				r.review_id, 
				r.stars, 
				r.comment, 
				r.created_at, 
				u.username,
				COALESCE(NULLIF(CONCAT(g.first_name, ' ', g.last_name), ' '), u.username) AS reviewer_name,
				u.user_type
			FROM reviews r
			JOIN users u ON r.user_id = u.user_id
			LEFT JOIN guests g ON u.guest_id = g.guest_id
			ORDER BY r.created_at DESC, r.review_id DESC
			LIMIT 5";

			$result = mysqli_query($conn, $sql);

			while ($row = mysqli_fetch_assoc($result)) {
				?>
				<div class="testimonial-card">
					<div class="stars"><?php echo str_repeat('★', intval($row['stars'])); ?></div>
					<p class="testimonial-text">"<?php echo htmlspecialchars($row['comment']); ?>"</p>
					<div class="author-info">
						<div class="author-avatar"><?php echo strtoupper(substr($row['reviewer_name'], 0, 1)); ?></div>
						<div>
							<div class="author-name"><?php echo htmlspecialchars($row['reviewer_name']); ?></div>
							<div class="author-title"><?php echo htmlspecialchars($row['user_type']); ?> Guest</div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
	</section>

	<!-- ------------------------------------------------------------- -->
	<!-- Footer -->
	<!-- ------------------------------------------------------------- -->
	<footer class="footer">
		<div class="footer-container">
			<div class="footer-col">
				<div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
					<img src="logo.svg" alt="Logo" style="width:36px; height:36px;">
					<span
						style="font-family:'Playfair Display', serif; color:var(--accent-gold); font-size:20px; font-weight:700;">AHMANI
						HOTEL</span>
				</div>
				<p style="color:var(--text-muted); font-size:13.5px; line-height:1.7;">Providing luxury accommodation,
					dining, and intelligent hospitality systems to redefine your hotel experience.</p>
			</div>

			<div class="footer-col">
				<h4>Quick Links</h4>
				<ul class="footer-links">
					<li><a href="#home">Home</a></li>
					<li><a href="#rooms">Suites &amp; Rooms</a></li>
					<li><a href="#services">Hotel Services</a></li>
					<li><a href="#navigation">Route Finder</a></li>
					<li><a href="#halls">Banquet Halls</a></li>
				</ul>
			</div>

			<div class="footer-col">
				<h4>Portals &amp; System</h4>
				<ul class="footer-links">
					<li><a href="login.php">Guest Portal</a></li>
					<li><a href="login.php">Staff Dashboard Login</a></li>
					<li><a href="login.php">Admin Control Login</a></li>
				</ul>
			</div>

			<div class="footer-col">
				<h4>Contact Concierge</h4>
				<p style="color:var(--text-muted); font-size:13.5px; margin-bottom:8px;"><svg width="14" height="14"
						viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2"
						style="vertical-align:-2px; margin-right:6px;">
						<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
						<circle cx="12" cy="10" r="3" />
					</svg>piku piku biku</p>
				<p style="color:var(--text-muted); font-size:13.5px; margin-bottom:8px;"><svg width="14" height="14"
						viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2"
						style="vertical-align:-2px; margin-right:6px;">
						<path
							d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
					</svg>ami pagol tumi chagol yayyy</p>
				<p style="color:var(--text-muted); font-size:13.5px;"><svg width="14" height="14" viewBox="0 0 24 24"
						fill="none" stroke="var(--accent-gold)" stroke-width="2"
						style="vertical-align:-2px; margin-right:6px;">
						<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
						<polyline points="22,6 12,13 2,6" />
					</svg>paglababa@ahmanihotel.com</p>
			</div>
		</div>

		<div class="copyright">
			Copyright &copy; 2026 AHMANI HOTEL &amp; RESORT. All Baper right rights reserved.
		</div>
	</footer>

	<!-- ------------------------------------------------------------- -->
	<!-- Booking Modal -->
	<!-- ------------------------------------------------------------- -->
	<div class="modal-overlay" id="bookingModal">
		<div class="modal-content">
			<button class="modal-close" onclick="closeBookingModal()">&times;</button>
			<h3
				style="font-family:'Playfair Display', serif; color:var(--accent-gold); font-size:24px; margin-bottom:10px;">
				Proceed to Guest Portal</h3>
			<p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">To confirm room reservations or
				request room service, please sign in to your guest account.</p>

			<a href="login.php" class="btn-primary"
				style="display:block; text-align:center; width:100%; padding:12px; margin-bottom:10px;">Sign In to Guest
				Account</a>
			<a href="login.php" class="btn-outline"
				style="display:block; text-align:center; width:100%; padding:12px;">Create New Guest Profile</a>
		</div>
	</div>

	<!-- ------------------------------------------------------------- -->
	<!-- JavaScript Logic -->
	<!-- ------------------------------------------------------------- -->
	<script>
		function toggleMenu() {
			document.getElementById('navMenu').classList.toggle('active');
		}

		function openBookingModal() {
			document.getElementById('bookingModal').classList.add('active');
		}

		function closeBookingModal() {
			document.getElementById('bookingModal').classList.remove('active');
		}

		// Close modal when clicking outside
		window.onclick = function (event) {
			var modal = document.getElementById('bookingModal');
			if (event.target === modal) {
				closeBookingModal();
			}
		};

		// Set default dates in search bar
		document.addEventListener("DOMContentLoaded", function () {
			var today = new Date();
			var tomorrow = new Date(today);
			tomorrow.setDate(tomorrow.getDate() + 1);

			var inEl = document.getElementById('checkInDate');
			var outEl = document.getElementById('checkOutDate');
			if (inEl && outEl && !inEl.value && !outEl.value) {
				inEl.value = today.toISOString().split('T')[0];
				outEl.value = tomorrow.toISOString().split('T')[0];
			}
		});
	</script>
</body>

</html>