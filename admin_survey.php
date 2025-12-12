<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ./login.html");
    exit();
}
include __DIR__ . '/php/db_connect.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Survey Requests</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    font-family: 'Roboto', sans-serif;
    background: #fdfdfd;
    margin: 0;
    padding: 20px;
    color: #333;
}

/* Header */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
header h2 {
    color: #2196f3;
}
header button {
    padding: 10px 15px;
    background: #ff9800;
    color: #000;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
header button:hover {
    background: #ffb74d;
}

/* Table */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
th, td {
    padding: 12px;
    text-align: left;
}
th {
    background: #2196f3;
    color: #fff;
}
tbody tr:nth-child(even) {
    background: #f9f9f9;
}
tbody tr:hover {
    background: #ffe082;
}
</style>
</head>
<body>

<header>
    <h2>Survey Requests</h2>
    <button onclick="window.location.href='admin_dashboard.php'"><i class="fa-solid fa-arrow-left"></i> Back</button>
</header>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Product Type</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Submitted At</th>
        </tr>
    </thead>
    <tbody>
    <?php
      $sql = "SELECT * FROM survey ORDER BY created_at DESC";
      $result = $conn->query($sql);
      if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>
                      <td>".htmlspecialchars($row['id'])."</td>
                      <td>".htmlspecialchars($row['product_type'])."</td>
                      <td>".htmlspecialchars($row['name'])."</td>
                      <td>".htmlspecialchars($row['contact'])."</td>
                      <td>".htmlspecialchars($row['created_at'])."</td>
                    </tr>";
          }
      } else {
          echo "<tr><td colspan='5' style='text-align:center'>No survey requests found.</td></tr>";
      }
    ?>
    </tbody>
</table>

</body>
</html>
