<?php
session_start();
include 'php/db_connect.php';

// Define safe_close_stmt function
function safe_close_stmt($stmt) {
    if ($stmt) {
        $stmt->close();
    }
}

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    error_log("Redirecting to login: User not authenticated or not a driver.");
    header("Location: ../login.html");
    exit();
}

$driver_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
error_log("Driver ID: $driver_id, Username: $username");

// Handle form submission for accepting orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_with_details') {
    error_log("Form submitted for accepting order.");
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $vehicle = $_POST['vehicle'] ?? '';
    $address = $_POST['address'] ?? '';
    $booking_id = $_POST['booking_id'] ?? 0;

    error_log("Received data: Name=$name, Email=$email, Phone=$phone, Vehicle=$vehicle, Address=$address, Booking ID=$booking_id");

    try {
        // Check if driver profile exists
        $checkDriverSql = "SELECT id FROM drivers WHERE id = ?";
        $stmt = $conn->prepare($checkDriverSql);
        if ($stmt === false) {
            throw new Exception('Prepare failed for checking driver: ' . $conn->error);
        }
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $driverExists = $result->num_rows > 0;
        safe_close_stmt($stmt);

        // Insert or update driver profile
        if (!$driverExists) {
            $driverSql = "INSERT INTO drivers (id, name, email, username, phone_number, vehicle_details, address, status, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $conn->prepare($driverSql);
            if ($stmt === false) {
                throw new Exception('Prepare failed for inserting driver: ' . $conn->error);
            }
            $stmt->bind_param("issssss", $driver_id, $name, $email, $username, $phone, $vehicle, $address);
        } else {
            $driverSql = "UPDATE drivers SET name = ?, email = ?, phone_number = ?, vehicle_details = ?, address = ?
                          WHERE id = ?";
            $stmt = $conn->prepare($driverSql);
            if ($stmt === false) {
                throw new Exception('Prepare failed for updating driver: ' . $conn->error);
            }
            $stmt->bind_param("sssssi", $name, $email, $phone, $vehicle, $address, $driver_id);
        }

        $stmt->execute();
        error_log("Driver profile saved successfully.");
        safe_close_stmt($stmt);

        // Update booking status and assign driver
        $updateBookingSql = "UPDATE bookings SET status = 'Confirmed', assigned_driver_id = ? WHERE id = ?";
        $stmt = $conn->prepare($updateBookingSql);
        if ($stmt === false) {
            throw new Exception('Prepare failed for updating booking status: ' . $conn->error);
        }
        $stmt->bind_param("ii", $driver_id, $booking_id);
        $stmt->execute();
        error_log("Booking status updated successfully.");
        safe_close_stmt($stmt);

        // ✅ FIX: Get user ID from bookings table (correct column name is user_id)
        $getUserSql = "SELECT user_id FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($getUserSql);
        if ($stmt === false) {
            throw new Exception('Prepare failed for getting user ID: ' . $conn->error);
        }
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRow = $result->fetch_assoc();
        $userId = $userRow['user_id'];
        safe_close_stmt($stmt);

        // Prepare message with all driver details
        $messageDetails = [
            'driver_name' => $name,
            'driver_email' => $email,
            'driver_contact' => $phone,
            'driver_vehicle' => $vehicle,
            'driver_address' => $address,
            'booking_id' => $booking_id,
            'driver_id' => $driver_id
        ];

        $userMessage = json_encode($messageDetails);

        // Send a message to the user
        $insertMessageSql = "INSERT INTO messages (driver_id, user_id, message_text, is_read, created_at) 
                     VALUES (?, ?, ?, 0, NOW())";
        $stmt = $conn->prepare($insertMessageSql);
        if ($stmt === false) {
            throw new Exception('Prepare failed for inserting message: ' . $conn->error);
        }
        $stmt->bind_param("iis", $driver_id, $userId, $userMessage);
        $stmt->execute();
        error_log("Message sent to user successfully.");
        safe_close_stmt($stmt);

        // Redirect with success message
        header("Location: driver_dashboard.php?success=" . urlencode("Order accepted successfully!"));
        exit();
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        header("Location: driver_dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// FETCH 1: ASSIGNED ORDERS (Pending, no driver assigned)
$assignedOrders = [];
$assignedCount = 0;
$sqlAssigned = "SELECT
                    id AS booking_id,
                    tracking_id,
                    pickup,
                    destination,
                    sender_name,
                    sender_phone,
                    receiver_name,
                    receiver_phone,
                    total_price,
                    labour_count,
                    cargo_type,
                    vehicle,
                    distance_km,
                    status
                FROM bookings
                WHERE status = 'Pending'";
$stmtAssigned = $conn->prepare($sqlAssigned);
if ($stmtAssigned === false) {
    error_log('Assigned Orders SQL Prepare failed: ' . $conn->error);
}
if ($stmtAssigned) {
    $stmtAssigned->execute();
    $resultAssigned = $stmtAssigned->get_result();
    $assignedOrders = $resultAssigned->fetch_all(MYSQLI_ASSOC);
    $assignedCount = $resultAssigned->num_rows;
    error_log("Fetched $assignedCount assigned orders.");
    safe_close_stmt($stmtAssigned);
}

// FETCH 2: CONFIRMED ORDERS (In progress for this driver)
$confirmedOrders = [];
$confirmedCount = 0;
$sqlConfirmed = "SELECT
                    id AS booking_id,
                    tracking_id,
                    pickup,
                    destination,
                    total_price,
                    status,
                    vehicle,
                    distance_km
                FROM bookings
                WHERE assigned_driver_id = ? AND status IN ('Confirmed', 'Picked Up', 'In Transit')
                ORDER BY created_at DESC";
$stmtConfirmed = $conn->prepare($sqlConfirmed);
if ($stmtConfirmed === false) {
    error_log('Confirmed Orders SQL Prepare failed: ' . $conn->error);
}
if ($stmtConfirmed) {
    $stmtConfirmed->bind_param("i", $driver_id);
    $stmtConfirmed->execute();
    $resultConfirmed = $stmtConfirmed->get_result();
    $confirmedOrders = $resultConfirmed->fetch_all(MYSQLI_ASSOC);
    $confirmedCount = $resultConfirmed->num_rows;
    error_log("Fetched $confirmedCount confirmed orders.");
    safe_close_stmt($stmtConfirmed);
}

// FETCH 3: DRIVER PROFILE
$profileResult = [];
$sqlProfile = "SELECT name, email, phone_number, vehicle_details, address, username FROM drivers WHERE id = ?";
$stmtP = $conn->prepare($sqlProfile);
if ($stmtP === false) {
    error_log('Driver Profile SQL Prepare failed: ' . $conn->error);
} else {
    $stmtP->bind_param("i", $driver_id);
    $stmtP->execute();
    $profileResult = $stmtP->get_result()->fetch_assoc() ?? [];
    error_log("Fetched driver profile.");
    safe_close_stmt($stmtP);
}

// Check if all necessary profile fields are filled
$profile_complete = !empty($profileResult['name']) && !empty($profileResult['phone_number']) && !empty($profileResult['address']) && !empty($profileResult['vehicle_details']);
error_log("Profile complete status: " . ($profile_complete ? 'Yes' : 'No'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Driver Dashboard | Cargo Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script>
        function logAndAlert(message) {
            console.log(message);
            alert(message);
        }
    </script>
    <style>
    /* Add this to your existing CSS */
        .modal-lg {
            max-width: 800px;
        }
        .message-badge {
            position: relative;
            display: inline-flex;
        }
        .message-badge .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            padding: 5px 7px;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            font-size: 12px;
        }
        .message-dropdown {
            position: absolute;
            right: 0;
            top: 60px;
            background: white;
            width: 350px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }
        .message-dropdown.show {
            display: block;
        }
        .message-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .message-item:hover {
            background-color: #f8f9fa;
        }
        .message-item:last-child {
            border-bottom: none;
        }
        .message-modal {
            max-width: 600px;
        }
        .message-modal .modal-header {
            border-bottom: none;
        }
    
    
    :root {    --primary-color: #0d6efd;    --secondary-color: #f8f9fa;    --dark-color: #212529;    --sidebar-bg: #1f2937;    --sidebar-hover: #374151;}body {    margin: 0;    font-family: 'Inter', sans-serif;    background: var(--secondary-color);}/* Sidebar and other styles unchanged... */.sidebar {    position: fixed;    top: 0;    left: 0;    height: 100%;    width: 60px;    background: var(--sidebar-bg);    color: white;    padding-top: 60px;    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);    z-index: 1000;    overflow-x: visible;    transition: width 0.3s ease;    white-space: nowrap;}.sidebar:hover {    width: 250px;}.sidebar .logo {    padding: 15px 0;    text-align: center;    font-size: 1.5rem;    font-weight: 700;    color: #ffc107;    margin-bottom: 20px;    white-space: nowrap;    overflow: hidden;    text-overflow: ellipsis;}.sidebar a {    display: flex;    align-items: center;    color: white;    padding: 12px 15px;    text-decoration: none;    margin: 5px 10px;    border-radius: 8px;    transition: background-color 0.2s, padding 0.3s;    justify-content: center;    overflow: visible;}.sidebar a i {    font-size: 1.3rem;    min-width: 30px;    text-align: center;    flex-shrink: 0;}.sidebar a span {    margin-left: 10px;    opacity: 0;    transition: opacity 0.3s ease;    white-space: nowrap;    overflow: hidden;}.sidebar:hover a {    justify-content: flex-start;    padding-left: 20px;}.sidebar:hover a span {    opacity: 1;}.sidebar a:hover,.sidebar a.active {    background: var(--sidebar-hover);    color: white;}.main {    margin-left: 60px;    padding: 20px;    padding-top: 80px;    transition: margin-left 0.3s;}.sidebar:hover ~ .main {    margin-left: 250px;}.navbar {    position: fixed;    top: 0;    left: 60px;    right: 0;    height: 60px;    background: white;    display: flex;    align-items: center;    justify-content: space-between;    padding: 0 30px;    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);    z-index: 999;    transition: left 0.3s;}.sidebar:hover ~ .navbar {    left: 250px;}.navbar-right {    display: flex;    align-items: center;}#alertAdminBtn {    font-size: 1rem;    padding: 8px 15px;    border-radius: 8px;    font-weight: 600;    background-color: #dc3545;    color: white;    transition: background-color 0.3s;    box-shadow: 0 0 15px rgba(255, 40, 40, 0.7);}#alertAdminBtn:hover {    background-color: #c82333;    box-shadow: 0 0 20px rgba(255, 40, 40, 0.9);}.card {    background: white;    border: none;    border-radius: 12px;    padding: 25px;    margin-bottom: 25px;    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);}.metric-card {    text-align: center;    color: white;    padding: 20px;    border-radius: 12px;}.metric-card.bg-success {    background: #28a745;}.metric-card.bg-warning {    background: #ffc107;    color: var(--dark-color);}.action-btn {    padding: 6px 12px;    border: none;    border-radius: 6px;    cursor: pointer;    font-weight: 500;    transition: background-color 0.2s;    font-size: 0.9rem;    margin-right: 5px;    white-space: nowrap;    text-decoration: none;    display: inline-block;}.action-btn.disabled {    pointer-events: none;    opacity: 0.6;    cursor: not-allowed;}.accept-btn {    background: #28a745;    color: white;}.reject-btn {    background: #dc3545;    color: white;}.pickup-btn {    background: #007bff;    color: white;}.upload-btn {    background: #17a2b8;    color: white;}.table-responsive {    overflow-x: auto;}/* Modal for Alert Input */.modal-overlay {    position: fixed;    top: 0;    left: 0;    width: 100%;    height: 100%;    background: rgba(0,0,0,0.5);    display: none;    justify-content: center;    align-items: center;    z-index: 1100;}.modal-content {    background: white;    padding: 30px;    border-radius: 12px;    max-width: 400px;    width: 90%;    box-shadow: 0 5px 15px rgba(0,0,0,0.3);}/* Custom Notification Banner */#customNotification {    position: fixed;    top: 70px;    right: 20px;    z-index: 2000;    max-width: 350px;    transition: transform 0.3s ease-in-out;    transform: translateX(120%);}#customNotification.show {    transform: translateX(0);}/* Responsive tweaks */@media (max-width: 768px) {    .sidebar {        width: 60px !important;    }    .sidebar:hover {        width: 200px !important;    }    .main {        margin-left: 60px !important;        padding: 15px !important;    }    .sidebar:hover ~ .main {        margin-left: 200px !important;    }    .navbar {        left: 60px !important;        padding: 0 15px !important;    }    .sidebar:hover ~ .navbar {        left: 200px !important;    }    table {        font-size: 0.85rem;    }}</style>
</head>
<body>
<div class="modal-overlay" id="alertModal">
    <div class="modal-content">
        <h5 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Emergency Alert</h5>
        <p>Please briefly describe the urgent issue for the Admin:</p>
        <textarea id="alertReason" class="form-control mb-3" rows="3" required></textarea>
        <div class="d-flex justify-content-end">
            <button class="btn btn-secondary me-2" onclick="document.getElementById('alertModal').style.display='none'">Cancel</button>
            <button class="btn btn-danger" id="submitAlertBtn">Send Alert</button>
        </div>
    </div>
</div>
<div id="customNotification" class="alert alert-dismissible fade" role="alert" style="display: none;">
    <span id="notificationMessage"></span>
    <button type="button" class="btn-close" onclick="document.getElementById('customNotification').style.display='none'" aria-label="Close"></button>
</div>

    <div class="modal fade" id="driverDetailsModal" tabindex="-1" aria-labelledby="driverDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="driverDetailsModalLabel">Confirm Acceptance & Complete Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="driverDetailsForm" method="POST" action="driver_dashboard.php">
                    <div class="modal-body">
                        <p>Before accepting, please confirm or update your essential contact details.</p>
                        <input type="hidden" id="modalBookingId" name="booking_id" value="">
                        <input type="hidden" name="action" value="accept_with_details">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="driverName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="driverName" name="name"
                                       value="<?php echo htmlspecialchars($profileResult['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="driverEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="driverEmail" name="email"
                                       value="<?php echo htmlspecialchars($profileResult['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="driverPhone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="driverPhone" name="phone"
                                       value="<?php echo htmlspecialchars($profileResult['phone_number'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="driverVehicle" class="form-label">Vehicle Details</label>
                                <input type="text" class="form-control" id="driverVehicle" name="vehicle"
                                       value="<?php echo htmlspecialchars($profileResult['vehicle_details'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="driverAddress" class="form-label">Address</label>
                            <textarea class="form-control" id="driverAddress" name="address" rows="3" required><?php echo htmlspecialchars($profileResult['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Save and Accept Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<div class="sidebar">
    <div class="logo">Cargo Services</div>
    <a href="#dashboard" class="active" data-section="dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a href="#assignedOrders" data-section="assignedOrders"><i class="bi bi-clipboard-check"></i><span>New Assignments</span></a>
    <a href="#confirmedOrders" data-section="confirmedOrders"><i class="bi bi-truck-flatbed"></i><span>Active Shipments</span></a>
    <a href="php/logout.php"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
</div>
<div class="navbar">
    <div class="navbar-left">
        <span class="fw-bold fs-5">Welcome, <?php echo htmlspecialchars($profileResult['name'] ?? $username); ?></span>
    </div>
    <div class="navbar-right">
        <button id="alertAdminBtn" class="btn btn-danger" title="EMERGENCY ALERT TO ADMIN">
            <i class="bi bi-exclamation-triangle-fill"></i> Alert Admin
        </button>
    </div>
</div>
<div class="main">
    <!-- All your existing sections remain exactly the same -->
    <section class="dashboard-section" id="dashboard">
        <h1 class="mb-4">Driver Dashboard</h1>
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card bg-warning text-dark">
                    <h3><?php echo $assignedCount; ?></h3>
                    <p>New Orders to Accept</p>
                    <i class="bi bi-clipboard-check fs-1 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card bg-success">
                    <h3><?php echo $confirmedCount; ?></h3>
                    <p>Active Shipments</p>
                    <i class="bi bi-truck fs-1 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card" style="background-color: #17a2b8;">
                    <h3><?php echo htmlspecialchars($profileResult['vehicle_details'] ?? 'Pending'); ?></h3>
                    <p>Vehicle Type</p>
                    <i class="bi bi-truck-front fs-1 mt-2"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card" style="background-color: #6c757d;">
                    <h3>
                        <?php
                        $totalDistance = 0;
                        foreach ($confirmedOrders as $order) {
                            $totalDistance += floatval($order['distance_km']);
                        }
                        echo number_format($totalDistance, 2) . ' km';
                        ?>
                    </h3>
                    <p>Total Distance (Active)</p>
                    <i class="bi bi-signpost-split fs-1 mt-2"></i>
                </div>
            </div>
        </div>
        <div class="card mt-4">
            <h2><i class="bi bi-person-badge me-2"></i> Driver Status</h2>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($profileResult['username'] ?? 'N/A'); ?></p>
            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($profileResult['vehicle_details'] ?? 'Pending'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($profileResult['phone_number'] ?? 'Pending'); ?></p>
            <?php if (!$profile_complete): ?>
                <p class="text-danger">**Profile Incomplete** - You must fill out your **Name, Phone, Address, and Vehicle details** in the Profile tab to accept orders.</p>
            <?php endif; ?>
        </div>
        <div class="card mt-4">
            <h2><i class="bi bi-info-circle me-2"></i> Workflow Instructions</h2>
            <ol>
                <li>Check <strong>New Assignments</strong> tab for orders available to accept.</li>
                <li>Use **Accept** or **Decline** action buttons. Accepted orders move to **Active Shipments**.</li>
                <li>In **Active Shipments**, use **Pick Up** when you collect the cargo.</li>
                <li>Once delivered, navigate to the upload page and **Upload Proof** to complete the job.</li>
                <li>View completed jobs in your profile or reports.</li>
            </ol>
        </div>
    </section>

    <section class="dashboard-section" id="assignedOrders" style="display:none;">
        <!-- Your existing assigned orders section remains exactly the same -->
        <div class="card">
            <h2><i class="bi bi-clipboard-check me-2"></i> New Assignments (<?php echo $assignedCount; ?>)</h2>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tracking ID</th>
                            <th>Pickup Info</th>
                            <th>Destination Info</th>
                            <th>Cargo Type</th>
                            <th>Labour Count</th>
                            <th>Vehicle</th>
                            <th>Distance (km)</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($assignedCount > 0): ?>
                            <?php foreach($assignedOrders as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['tracking_id']); ?></td>
                                <td>
                                    <strong>Location:</strong> <?php echo htmlspecialchars($row['pickup']); ?><br>
                                    <strong>Name:</strong> <?php echo htmlspecialchars($row['sender_name']); ?><br>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($row['sender_phone']); ?>
                                </td>
                                <td>
                                    <strong>Location:</strong> <?php echo htmlspecialchars($row['destination']); ?><br>
                                    <strong>Name:</strong> <?php echo htmlspecialchars($row['receiver_name']); ?><br>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($row['receiver_phone']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['cargo_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['labour_count']); ?></td>
                                <td><?php echo htmlspecialchars($row['vehicle']); ?></td>
                                <td><?php echo htmlspecialchars($row['distance_km']); ?></td>
                                <td>$<?php echo number_format((float)$row['total_price'], 2); ?></td>
                                <td>
                                    <button type="button"
                                        class="action-btn accept-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#driverDetailsModal"
                                        data-booking-id="<?php echo htmlspecialchars($row['booking_id']); ?>"
                                        onclick="logAndAlert('Accept button clicked! Booking ID: <?php echo htmlspecialchars($row['booking_id']); ?>')"
                                        >
                                        Accept
                                    </button>
                                    <form method='post' action='php/driver_actions.php' class='d-inline'>
                                        <input type='hidden' name='booking_id' value='<?php echo htmlspecialchars($row['booking_id']); ?>'>
                                        <button type='submit' name='action' value='decline' class='action-btn reject-btn'>Decline</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='9' class='text-center'>No new orders available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
         <!-- Confirmed Orders section (already in your code, just make sure it's not hidden) -->
    <section class="dashboard-section" id="confirmedOrders" style="display:none;">
    <div class="card">
        <h2><i class="bi bi-truck-flatbed me-2"></i> Active Shipments (<?php echo $confirmedCount; ?>)</h2>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Tracking ID</th>
                        <th>Pickup</th>
                        <th>Destination</th>
                        <th>Vehicle</th>
                        <th>Distance (km)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($confirmedCount > 0): ?>
                        <?php foreach($confirmedOrders as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tracking_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['pickup']); ?></td>
                            <td><?php echo htmlspecialchars($row['destination']); ?></td>
                            <td><?php echo htmlspecialchars($row['vehicle']); ?></td>
                            <td><?php echo htmlspecialchars($row['distance_km']); ?></td>
                            <td>
                                <span class='badge bg-<?php
                                    if ($row['status'] == 'Confirmed') echo 'primary';
                                    elseif (in_array($row['status'], ['Picked Up', 'In Transit'])) echo 'success';
                                    elseif ($row['status'] == 'Delivered') echo 'dark';
                                    else echo 'secondary';
                                ?>'>
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Always show Upload Proof button for confirmed/active shipments -->
                                <a href="php/proof_upload_form.php?booking_id=<?php echo htmlspecialchars($row['booking_id']); ?>" 
                                   class='action-btn upload-btn' 
                                   title='Go to Proof Upload Page'>
                                   Upload Proof
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='7' class='text-center'>You have no confirmed orders currently in progress.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to show notifications
    function showNotification(message, isError = false) {
        const notificationBanner = document.getElementById('customNotification');
        const notificationMessage = document.getElementById('notificationMessage');

        if (notificationBanner && notificationMessage) {
            notificationMessage.textContent = message;
            notificationBanner.className = `alert alert-dismissible fade show ${isError ? 'alert-danger' : 'alert-success'}`;
            notificationBanner.style.display = 'block';
            setTimeout(() => {
                notificationBanner.className = notificationBanner.className.replace('show', '');
                setTimeout(() => notificationBanner.style.display = 'none', 300);
            }, 5000);
        } else {
            console.error("Notification elements not found!");
            alert(message);
        }
    }

    // Section switching logic
    const sidebarLinks = document.querySelectorAll('.sidebar a[data-section]');
    const sections = document.querySelectorAll('.dashboard-section');

    function showSection(sectionId) {
        sections.forEach(section => { section.style.display = 'none'; });
        const activeSection = document.getElementById(sectionId);
        if (activeSection) {
            activeSection.style.display = 'block';
        }
        sidebarLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('data-section') === sectionId) {
                link.classList.add('active');
            }
        });
    }

    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            showSection(sectionId);
            history.pushState(null, '', '#' + sectionId);
        });
    });

    // Initial load & URL parameter handling
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');
    const urlHash = window.location.hash ? window.location.hash.substring(1) : 'dashboard';

    if (successMessage) {
        showNotification(decodeURIComponent(successMessage), false);
        window.history.replaceState(null, null, window.location.pathname + window.location.hash);
    }
    if (errorMessage) {
        showNotification(decodeURIComponent(errorMessage), true);
        window.history.replaceState(null, null, window.location.pathname + window.location.hash);
    }

    showSection(urlHash);

    // Modal logic for accepting orders
    const driverDetailsModalEl = document.getElementById('driverDetailsModal');
    if (driverDetailsModalEl) {
        driverDetailsModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.classList.contains('accept-btn')) {
                const bookingId = button.getAttribute('data-booking-id');
                const modalBookingIdInput = document.getElementById('modalBookingId');

                if (modalBookingIdInput) {
                    modalBookingIdInput.value = bookingId;
                }
            }
        });
    }

    // Alert admin logic
    const alertModal = document.getElementById('alertModal');
    const submitAlertBtn = document.getElementById('submitAlertBtn');
    if (alertModal && submitAlertBtn) {
        document.getElementById('alertAdminBtn').addEventListener('click', function() {
            alertModal.style.display = 'flex';
        });

        submitAlertBtn.addEventListener('click', function() {
            const reason = document.getElementById('alertReason').value.trim();
            if (reason === '') {
                showNotification("Please enter a reason for the alert.", true);
                return;
            }
            submitAlertBtn.disabled = true;
            submitAlertBtn.textContent = 'Sending...';
            const form = new URLSearchParams();
            form.append('driver_id', '<?php echo $driver_id; ?>');
            form.append('message', reason);
            form.append('user_type', 'driver');
            fetch('php/alert_admin.php', {
                method: 'POST',
                body: form
            })
            .then(r => r.json())
            .then(data => {
                alertModal.style.display = 'none';
                document.getElementById('alertReason').value = '';
                if (data.success) {
                    showNotification("ALERT CONFIRMED: Admin has been notified.");
                } else {
                    showNotification(data.message || 'CRITICAL FAILURE: Could not send alert.', true);
                }
            })
            .catch(e => {
                showNotification('CRITICAL FAILURE: Network error while sending alert.', true);
                console.error("Alert failure:", e);
            })
            .finally(() => {
                submitAlertBtn.disabled = false;
                submitAlertBtn.textContent = 'Send Alert';
            });
        });
    }
});
</script>
</body>
</html>
