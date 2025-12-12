<?php
session_start();
// FIX: Changed authentication logic to check for 'role' == 'admin' 
// which is the variable actually set in your login.php file.
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}
include __DIR__ . '/php/db_connect.php';

/* ========== Shipment counts ========== */
$statusCounts = ['Pending'=>0,'In Transit'=>0,'Delivered'=>0];
$sqlChart="SELECT status,COUNT(*) as count FROM shipments GROUP BY status";
$resultChart=$conn->query($sqlChart);
if($resultChart){
    while($row=$resultChart->fetch_assoc()){
        if(isset($statusCounts[$row['status']])) $statusCounts[$row['status']]=$row['count'];
    }
}

/* ========== Shipment trends ========== */
$trendLabels=[];$trendData=[];
$sqlTrend="SELECT DATE(updated) as day,COUNT(*) as count FROM shipments 
           WHERE updated>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)
           GROUP BY DATE(updated) ORDER BY DATE(updated)";
$resultTrend=$conn->query($sqlTrend);
while($row=$resultTrend->fetch_assoc()){
    $trendLabels[]=$row['day'];
    $trendData[]=$row['count'];
}

/* ========== Recent shipments ========== */
$sqlRecent="SELECT b.tracking_id,s.status,b.sender_name,b.receiver_name,s.updated
            FROM shipments s JOIN bookings b ON s.booking_id=b.id
            ORDER BY s.updated DESC LIMIT 10";
$resultRecent=$conn->query($sqlRecent);

/* ========== Alerts with guest fallback (show only Pending/Seen) ========== */
$sqlAlerts="
SELECT a.alert_id,
       CASE 
        WHEN u.username IS NOT NULL THEN u.username
        WHEN a.username IS NOT NULL THEN a.username
        ELSE CONCAT('Guest-',a.user_id) 
       END as username,
       a.alert_message,a.alert_time,a.status
FROM alerts a
LEFT JOIN users u ON a.user_id=u.id
WHERE a.status!='Resolved'
ORDER BY a.alert_time DESC";
$resultAlerts=$conn->query($sqlAlerts);
$alertCount=$resultAlerts?$resultAlerts->num_rows:0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* --- Your existing CSS remains the same --- */
body{font-family:'Roboto',sans-serif;background:#fdfdfd;margin:0;color:#333;}
.sidebar{position:fixed;top:0;left:0;bottom:0;width:220px;background:#1b1b1b;padding-top:20px;display:flex;flex-direction:column;box-shadow:2px 0 8px rgba(0,0,0,0.2);}
.sidebar h2{color:#ff9800;text-align:center;margin-bottom:20px;font-size:22px;}
.sidebar a{color:#ccc;padding:12px 20px;text-decoration:none;display:flex;align-items:center;margin-bottom:5px;border-radius:5px;}
.sidebar a:hover{background:#ff9800;color:#000;}
.sidebar a i{margin-right:10px;}
header{margin-left:220px;padding:20px;background:#2196f3;color:#fff;font-size:20px;font-weight:bold;display:flex;justify-content:space-between;align-items:center;}
header .alert-btn{background:red;color:#fff;padding:10px 20px;border:none;border-radius:5px;box-shadow:0 0 15px red;cursor:pointer;font-weight:bold;animation:pulse 2s infinite;}
header .alert-btn i{margin-right:6px;}
@keyframes pulse{0%{box-shadow:0 0 5px red;}50%{box-shadow:0 0 20px red;}100%{box-shadow:0 0 5px red;}}
.main{margin-left:220px;padding:20px;}
.stats{display:flex;gap:20px;margin-bottom:20px;flex-wrap:wrap;}
.stats .card{flex:1;min-width:150px;background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center;}
.stats .card h3{color:#ff9800;margin-bottom:10px;}
.stats .card p{font-size:22px;font-weight:bold;}
.card{background:#fff;border-radius:10px;padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
.card h2{color:#2196f3;margin-bottom:15px;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:12px;text-align:left;border-bottom:1px solid #ccc;}
th{background:#2196f3;color:#fff;}
.graph-row{display:flex;gap:20px;flex-wrap:wrap;}
.graph-row .graph-card{flex:1;min-width:300px;}
/* Alert modal */
#alertModal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;}
#alertModal .modal-content{background:white;margin:5% auto;padding:20px;border-radius:10px;width:80%;max-width:900px;max-height:80%;overflow:auto;box-shadow:0 0 20px red;}
#alertModal h2{color:red;margin-bottom:15px;}
.alert-table th{background:red;color:#fff;}
.alert-row-pending{background:rgba(255,0,0,0.1);}
.alert-row-seen{background:rgba(255,165,0,0.1);}
.alert-row-resolved{background:rgba(0,255,0,0.1);}
.action-btn{background:green;color:white;padding:5px 10px;border:none;border-radius:3px;cursor:pointer;margin:2px;}
.close-btn{margin-top:10px;background:black;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;}
.success-msg{margin-bottom:15px;padding:10px;background:#4caf50;color:white;border-radius:5px;}
</style>
</head>
<body>
<div class="sidebar">
    <h2><i class="fa-solid fa-cubes"></i> Cargo Admin</h2>

    <a href="admin_dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="php/admin_tracking.php"><i class="fa-solid fa-truck"></i> Tracking</a> 
    <a href="php/admin_reports.php"><i class="fa-solid fa-file-pdf"></i> Reports</a>
    <a href="php/admin_users.php"><i class="fa-solid fa-users"></i> Users</a>
    <a href="admin_survey.php"><i class="fa-solid fa-list-check"></i> Survey Requests</a>
    <a href="php/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>


<header>
    <div>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></div>
    <button class="alert-btn" onclick="document.getElementById('alertModal').style.display='block';">
        <i class="fa-solid fa-bell"></i> Alerts (<?php echo $alertCount; ?>)
    </button>
</header>

<div class="main">

<?php
// Show success message after action
if(isset($_GET['msg']) && $_GET['msg']!=""){
    echo "<div class='success-msg'>".htmlspecialchars($_GET['msg'])."</div>";
}
?>

<div class="stats">
    <div class="card"><h3>Pending</h3><p><?php echo $statusCounts['Pending']; ?></p></div>
    <div class="card"><h3>In Transit</h3><p><?php echo $statusCounts['In Transit']; ?></p></div>
    <div class="card"><h3>Delivered</h3><p><?php echo $statusCounts['Delivered']; ?></p></div>
</div>

<div class="card">
    <h2>Shipment Status Overview</h2>
    <canvas id="shipmentChart" height="80"></canvas>
</div>

<div class="graph-row">
    <div class="card graph-card">
        <h2>Shipment Percentage</h2>
        <canvas id="shipmentPie" height="50"></canvas>
    </div>
    <div class="card graph-card">
        <h2>Shipment Trends (Last 7 Days)</h2>
        <canvas id="shipmentLine" height="180"></canvas>
    </div>
</div>

<div class="card">
    <h2>Recent Shipments</h2>
    <table>
        <thead><tr><th>Tracking ID</th><th>Status</th><th>Sender</th><th>Receiver</th><th>Updated</th></tr></thead>
        <tbody>
        <?php
        if($resultRecent && $resultRecent->num_rows>0){
            while($row=$resultRecent->fetch_assoc()){
                echo "<tr>";
                echo "<td>".htmlspecialchars($row['tracking_id'])."</td>";
                echo "<td>".htmlspecialchars($row['status'])."</td>";
                echo "<td>".htmlspecialchars($row['sender_name'])."</td>";
                echo "<td>".htmlspecialchars($row['receiver_name'])."</td>";
                echo "<td>".htmlspecialchars($row['updated'])."</td>";
                echo "</tr>";
            }
        } else echo "<tr><td colspan='5'>No recent shipments found.</td></tr>";
        ?>
        </tbody>
    </table>
</div>
</div>

<!-- ALERT MODAL -->
<div id="alertModal">
  <div class="modal-content">
    <h2>⚠️ Security Alerts</h2>
    <table class="alert-table" width="100%">
      <tr>
        <th>User</th><th>Message</th><th>Time</th><th>Status</th><th>Action</th>
      </tr>
      <?php 
      if($resultAlerts && $resultAlerts->num_rows>0){
        $resultAlerts->data_seek(0); // Reset pointer after counting rows
        while($alert=$resultAlerts->fetch_assoc()){
          $rowClass=$alert['status']=='Pending'?'alert-row-pending':'alert-row-seen';
          echo "<tr class='$rowClass'>";
          echo "<td>".htmlspecialchars($alert['username'])."</td>";
          echo "<td style='font-weight:bold;color:red;'>".htmlspecialchars($alert['alert_message'])."</td>";
          echo "<td>".htmlspecialchars($alert['alert_time'])."</td>";
          echo "<td>".htmlspecialchars($alert['status'])."</td>";
          echo "<td>
                   <form method='post' action='php/update_alert.php' style='display:inline;'>
                      <input type='hidden' name='alert_id' value='".$alert['alert_id']."'>
                      <button type='submit' name='mark_seen' class='action-btn' style='background:#ff9800;'>Mark Seen</button>
                   </form>
                   <form method='post' action='php/update_alert.php' style='display:inline;'>
                      <input type='hidden' name='alert_id' value='".$alert['alert_id']."'>
                      <button type='submit' name='mark_resolved' class='action-btn'>Mark Resolved</button>
                   </form>
                 </td>";
          echo "</tr>";
        }
      } else echo "<tr><td colspan='5'>No alerts found.</td></tr>";
      ?>
    </table>
    <button class="close-btn" onclick="document.getElementById('alertModal').style.display='none'">Close</button>
  </div>
</div>

<script>
// ChartJS
const shipmentData={pending:<?php echo $statusCounts['Pending'];?>,inTransit:<?php echo $statusCounts['In Transit'];?>,delivered:<?php echo $statusCounts['Delivered'];?>};
new Chart(document.getElementById('shipmentChart'),{type:'bar',data:{labels:['Pending','In Transit','Delivered'],datasets:[{label:'Shipments',data:[shipmentData.pending,shipmentData.inTransit,shipmentData.delivered],backgroundColor:['#ff9800','#2196f3','#4caf50']}]},options:{scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('shipmentPie'),{type:'pie',data:{labels:['Pending','In Transit','Delivered'],datasets:[{data:[shipmentData.pending,shipmentData.inTransit,shipmentData.delivered],backgroundColor:['#ff9800','#2196f3','#4caf50']}]}})
new Chart(document.getElementById('shipmentLine'),{type:'line',data:{labels:<?php echo json_encode($trendLabels);?>,datasets:[{label:'Shipments per Day',data:<?php echo json_encode($trendData);?>,borderColor:'#ff9800',backgroundColor:'rgba(255,152,0,0.2)',fill:true,tension:0.3}]}})
</script>
</body>
</html>
