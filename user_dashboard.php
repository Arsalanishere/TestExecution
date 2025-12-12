<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'php/db_connect.php';

// Check authentication and role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.html");
    exit();
}

// Get values from session
$user_id   = $_SESSION['user_id'];   // integer ID (used in queries)
$username  = $_SESSION['username'];  // string username (used for display)

// --- Fetch Messages ---
$unreadMessageCount = 0;
$userMessages = [];

// Get all messages for this user
$stmt = $conn->prepare("
    SELECT m.id, m.message_text, m.is_read, m.created_at
    FROM messages m
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userMessages = $result->fetch_all(MYSQLI_ASSOC);

// Count unread messages
$unreadMessageCount = 0;
foreach ($userMessages as $message) {
    if ($message['is_read'] == 0) {
        $unreadMessageCount++;
    }
}
$stmt->close();

// --- 3. Fetch User Stats (Existing) ---
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$totalBookings = $result['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as pending FROM bookings WHERE user_id=? AND status='Pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$pendingShipments = $result['pending'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as labor FROM bookings WHERE user_id=? AND labour_count>0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$laborRequests = $result['labor'] ?? 0;
$stmt->close();

// --- 4. Vehicle Usage Chart Data ---
$stmt = $conn->prepare("SELECT vehicle, COUNT(*) as count FROM bookings WHERE user_id=? GROUP BY vehicle");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicleResult = $stmt->get_result();
$vehicleUsage = ['Truck'=>0, 'Shehzor'=>0, 'Suzuki'=>0];
while($row = $vehicleResult->fetch_assoc()){
    $vehicleUsage[$row['vehicle']] = $row['count'];
}
$stmt->close();

// --- 5. Monthly Bookings Trend ---
$currentYear = date('Y');
$monthlyBookings = array_fill(1,12,0);
$stmt = $conn->prepare("SELECT MONTH(created_at) as month, COUNT(*) as count 
                        FROM bookings 
                        WHERE user_id=? AND YEAR(created_at)=? 
                        GROUP BY MONTH(created_at)");
$stmt->bind_param("ii", $user_id, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $monthlyBookings[$row['month']] = $row['count'];
}
$stmt->close();

// --- 6. Recent Bookings List ---
$sqlRecent = "SELECT tracking_id, sender_name, receiver_name, status, created_at AS updated
              FROM bookings
              WHERE user_id=?
              ORDER BY created_at DESC
              LIMIT 5";
$stmt = $conn->prepare($sqlRecent);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentBookings = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Cargo Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <style>
        :root {
            --sidebar-width: 220px;
            --sidebar-collapsed-width: 60px;
            --primary-color: #1a73e8;
            --secondary-color: #f4f6f9;
            --card-shadow: 0 4px 15px rgba(0,0,0,0.1);
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--secondary-color);
            margin: 0;
            padding: 0;
        }

        /* Sidebar */
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-width);
            background-color: #111;
            color: white;
            padding-top: 60px;
            transition: width var(--transition-speed);
            z-index: 1000;
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar .logo {
            padding: 15px 0;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar a:hover { background-color: var(--primary-color); }
        .sidebar a.active { background-color: rgba(255,255,255,0.1); }

        .toggle-btn {
            position: absolute;
            bottom: 20px;
            left: 15px; right: 15px;
            padding: 10px;
            background-color: rgba(255,255,255,0.1);
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        /* Main */
        .main {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin-left var(--transition-speed);
        }
        .sidebar.collapsed ~ .main { margin-left: var(--sidebar-collapsed-width); }

        /* Cards */
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover { transform: scale(1.01); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }

        /* Alerts */
        #alertAdminBtn {
            box-shadow: 0 0 10px rgba(255,0,0,0.6);
            font-size: 1.1rem;
            padding: 12px 20px;
        }

        /* Messages */
        .message-badge { position: relative; }
        .message-badge .badge {
            position: absolute;
            top: -8px; right: -10px;
            padding: 5px 7px;
            border-radius: 50%;
            font-size: 12px;
        }
        .message-dropdown {
            position: absolute;
            right: 0;
            top: 50px;
            background: white;
            width: 320px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1050;
            display: none;
        }
        .message-dropdown.show { display: block; }
        .message-item { padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; }
        .message-item:hover { background-color: #f8f9fa; }
        .message-item.unread { font-weight: bold; background-color: #f8f9fa; }
        .message-date { font-size: 0.8rem; color: #6c757d; }

        @media (max-width: 768px) {
            .sidebar { width: var(--sidebar-collapsed-width); }
            .main { margin-left: var(--sidebar-collapsed-width); }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">Cargo Service</div>
        <a href="user_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a>
        <a href="book_cargo.php"><i class="bi bi-truck"></i> <span>Book Cargo</span></a>
        <a href="track.html"><i class="bi bi-geo-alt"></i> <span>Track Shipment</span></a>
        <a href="survey.html"><i class="bi bi-bar-chart-line-fill"></i> <span>Survey</span></a>
        <a href="php/profile.php"><i class="bi bi-person-circle"></i> <span>Profile</span></a>
        <a href="php/my_bookings.php"><i class="bi bi-card-list"></i> <span>My Bookings</span></a>
        <a href="php/logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        <button class="toggle-btn" id="toggleSidebar"><i class="bi bi-chevron-left"></i></button>
    </div>

    <!-- Main -->
    <div class="main">
        <div class="container-fluid">

            <!-- Top Row -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                    <p class="text-muted">Manage your cargo bookings efficiently.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Alert Button -->
                    <button id="alertAdminBtn" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#alertModal">
                        <i class="bi bi-exclamation-triangle-fill"></i> <span class="d-none d-sm-inline">Alert Admin</span>
                    </button>
                    <!-- Messages -->
                    <div class="message-badge">
                        <button id="messagesBtn" class="btn btn-light position-relative">
                            <i class="bi bi-envelope"></i>
                            <?php if ($unreadMessageCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $unreadMessageCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="message-dropdown" id="messageDropdown">
                            <div class="p-3 border-bottom">
                                <h6 class="m-0">Messages (<?php echo count($userMessages); ?>)</h6>
                            </div>
                            <div class="message-list" style="max-height:300px; overflow-y:auto;">
                                <?php if (!empty($userMessages)): ?>
                                    <?php foreach ($userMessages as $message): ?>
                                        <?php $messageData = json_decode($message['message_text'], true); ?>
                                        <div class="message-item <?php echo $message['is_read'] ? 'read':'unread'; ?>"
                                             data-message='<?php echo htmlspecialchars($message['message_text']); ?>'
                                             data-message-id="<?php echo $message['id']; ?>">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong>Booking #<?php echo $messageData['booking_id'] ?? 'N/A'; ?></strong><br>
                                                    <small>Driver: <?php echo $messageData['driver_name'] ?? 'N/A'; ?></small>
                                                </div>
                                                <small class="message-date"><?php echo date('M d, H:i', strtotime($message['created_at'])); ?></small>
                                            </div>
                                            <?php if (!$message['is_read']): ?><span class="badge bg-primary">New</span><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-3 text-center text-muted">No messages found</div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($userMessages)): ?>
                                <div class="p-2 border-top">
                                    <a href="#" class="btn btn-sm btn-outline-primary w-100" id="viewAllMessages">View All Messages</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="row">
                <div class="col-md-3 mb-3"><div class="card bg-dark text-white p-3 text-center"><i class="bi bi-truck display-4"></i><h5>Total Bookings</h5><h3><?php echo $totalBookings; ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card bg-primary text-white p-3 text-center"><i class="bi bi-clock-history display-4"></i><h5>Pending Shipments</h5><h3><?php echo $pendingShipments; ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card bg-success text-white p-3 text-center"><i class="bi bi-people display-4"></i><h5>Labor Requests</h5><h3><?php echo $laborRequests; ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card bg-warning text-dark p-3 text-center"><i class="bi bi-bar-chart display-4"></i><h5>Vehicle Usage</h5><h3><?php echo array_sum($vehicleUsage); ?> total</h3></div></div>
            </div>

            <!-- Charts -->
            <div class="row">
                <div class="col-md-6 mb-3"><div class="card p-3"><h5>Monthly Bookings (<?php echo $currentYear; ?>)</h5><canvas id="bookingsChart"></canvas></div></div>
                <div class="col-md-6 mb-3"><div class="card p-3"><h5>Vehicle Usage</h5><canvas id="vehicleChart"></canvas></div></div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-4 mb-3"><div class="card p-3 text-center"><i class="bi bi-truck display-1 text-primary"></i><h5>Book Cargo</h5><p>Create a new cargo booking quickly.</p><a href="book_cargo.php" class="btn btn-primary">Book Now</a></div></div>
                <div class="col-md-4 mb-3"><div class="card p-3 text-center"><i class="bi bi-geo-alt display-1 text-success"></i><h5>Track Shipment</h5><p>Check status of your cargo in real-time.</p><a href="track.html" class="btn btn-success">Track Now</a></div></div>
                <div class="col-md-4 mb-3"><div class="card p-3 text-center"><i class="bi bi-bar-chart-line-fill display-1 text-warning"></i><h5>Take Survey</h5><p>Share feedback to improve services.</p><a href="survey.html" class="btn btn-warning text-white">Survey</a></div></div>
            </div>

            <!-- Recent Bookings -->
            <div class="card mt-4 p-3">
                <h5>Recent Bookings (Last 5)</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Tracking ID</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Created At</th></tr></thead>
                        <tbody>
                        <?php if ($recentBookings->num_rows > 0): while($row = $recentBookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['tracking_id']; ?></td>
                                <td><?php echo $row['sender_name']; ?></td>
                                <td><?php echo $row['receiver_name']; ?></td>
                                <td><span class="badge 
                                    <?php echo ($row['status']=='Delivered')?'bg-success':(($row['status']=='In Transit')?'bg-primary':'bg-warning text-dark'); ?>">
                                    <?php echo $row['status']; ?></span></td>
                                <td><?php echo $row['updated']; ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center">No recent bookings found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Confirm Emergency Alert</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>⚠️ Are you sure you want to alert the admin?</strong></p>
                <textarea class="form-control" id="alertReason" rows="3" placeholder="Describe the issue (optional)"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="confirmAlertBtn">Send Alert</button>
            </div>
        </div></div>
    </div>

    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-envelope-open-fill"></i> Message Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageModalContent"></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Chart Data
const monthlyData = <?php echo json_encode(array_values($monthlyBookings)); ?>;
const vehicleData = <?php echo json_encode(array_values($vehicleUsage)); ?>;
const vehicleLabels = <?php echo json_encode(array_keys($vehicleUsage)); ?>;
const userMessages = <?php echo json_encode($userMessages); ?>;
const userId = <?php echo $user_id; ?>;
const username = '<?php echo $username; ?>';

// Initialize Charts
new Chart(document.getElementById('bookingsChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
            label: 'Bookings',
            data: monthlyData,
            backgroundColor: '#1a73e8'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('vehicleChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: vehicleLabels,
        datasets: [{
            label: 'Vehicle Usage',
            data: vehicleData,
            backgroundColor: ['#0d6efd','#2fe81a','#ffc107']
        }]
    },
    options: { responsive: true }
});

// Sidebar Toggle
document.getElementById('toggleSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

// Handle message item click (modal + mark as read)
const messageItems = document.querySelectorAll('.message-item');
messageItems.forEach(item => {
    item.addEventListener('click', function() {
        const messageText = this.getAttribute('data-message');
        const messageId = this.getAttribute('data-message-id');
        const messageData = JSON.parse(messageText);

        // Populate modal with message details
        const modalContent = document.getElementById('messageModalContent');
        modalContent.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Booking Information</h6>
                    <p><strong>Booking ID:</strong> ${messageData.booking_id || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Driver Details</h6>
                    <p><strong>Name:</strong> ${messageData.driver_name || 'N/A'}</p>
                    <p><strong>Email:</strong> ${messageData.driver_email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${messageData.driver_contact || 'N/A'}</p>
                    <p><strong>Vehicle:</strong> ${messageData.driver_vehicle || 'N/A'}</p>
                    <p><strong>Address:</strong> ${messageData.driver_address || 'N/A'}</p>
                </div>
            </div>
        `;

        // Show modal
        const messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
        messageModal.show();

        // Mark as read
        if (!this.classList.contains('read')) {
            fetch('php/mark_message_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.add('read');
                    this.classList.remove('unread');
                    const badge = document.querySelector('.message-badge .badge');
                    if (badge) {
                        const current = parseInt(badge.textContent);
                        if (current > 0) {
                            badge.textContent = current - 1;
                            if (current - 1 === 0) {
                                badge.remove();
                            }
                        }
                    }
                }
            });
        }
    });
});

  // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', ()=> {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Messages dropdown toggle
        const msgBtn = document.getElementById('messagesBtn');
        const msgDropdown = document.getElementById('messageDropdown');
        msgBtn.addEventListener('click', ()=> msgDropdown.classList.toggle('show'));

        // Close dropdown on outside click
        document.addEventListener('click', function(e) {
            if (!msgBtn.contains(e.target) && !msgDropdown.contains(e.target)) {
                msgDropdown.classList.remove('show');
            }
        });
// Alert Admin Functionality
document.getElementById('confirmAlertBtn').addEventListener('click', function() {
    const reason = document.getElementById('alertReason').value.trim();

    // Disable button to prevent double-click
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Sending...';

    const form = new URLSearchParams();
    form.append('user', username);
    form.append('message', reason || 'Emergency alert raised without details.');

    fetch('php/alert_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
    })
    .then(r => r.text())
    .then(t => {
        alert('Alert Sent: ' + t);
        bootstrap.Modal.getInstance(document.getElementById('alertModal')).hide();
        document.getElementById('alertReason').value = '';
    })
    .catch(err => {
        alert('Failed to send alert. Check console for details.');
        console.error('Alert failure:', err);
    })
    .finally(() => {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-send me-1"></i>Send Alert';
    });
});
</script>

</body>
</html>
