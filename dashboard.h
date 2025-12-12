<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cargo Service Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
    }

    body {
      background-color: #f2f2f2;
    }

    header {
      background-color: #ffd700;
      color: #d62828;
      padding: 20px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    nav {
      background-color: #d62828;
      color: white;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    nav a {
      color: white;
      margin-left: 20px;
      text-decoration: none;
      font-weight: 500;
    }

    .container {
      padding: 30px;
    }

    .card {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .card h2 {
      color: #d62828;
      margin-bottom: 15px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      border: 1px solid #ccc;
      padding: 12px;
      text-align: left;
    }

    th {
      background-color: #ffd700;
      color: #d62828;
    }

    canvas {
      max-width: 100%;
    }
  </style>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
  <header>Welcome to Cargo Service Dashboard</header>

  <nav>
    <div>Dashboard</div>
    <div>
      <a href="#">Shipments</a>
      <a href="#" id="tracking">Tracking</a>
      <a href="#" id="reports">Reports</a>
      <a href="#">About us</a>
      <a href="#" id="logout">Logout</a>


    </div>
  </nav>

    <div class="container">

    <!-- Tracking Search Bar -->
    <div class="search-container" id="trackingSearch">
      <input type="text" id="searchInput" placeholder="Enter Tracking ID">
      <button onclick="searchTracking()">Search</button>
    </div>

  <div class="container">
    <div class="card">
      <h2>Shipment Overview</h2>
      <canvas id="shipmentChart" height="100"></canvas>
    </div>

    <div class="card">
      <h2>Recent Shipments</h2>
      <table>
        <thead>
          <tr>
            <th>Tracking ID</th>
            <th>Status</th>
            <th>Sender</th>
            <th>Receiver</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>CARGO12345</td>
            <td>In Transit</td>
            <td>Ali Khan</td>
            <td>Sarah Malik</td>
            <td>2025-04-15</td>
          </tr>
          <tr>
            <td>CARGO12346</td>
            <td>Delivered</td>
            <td>Ahmad Raza</td>
            <td>Zoya Siddiqui</td>
            <td>2025-04-14</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx = document.getElementById('shipmentChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Pending', 'In Transit', 'Delivered'],
        datasets: [{
          label: 'Number of Shipments',
          data: [8, 14, 22],
          backgroundColor: [
            '#ff9800',
            '#2196f3',
            '#4caf50'
          ],
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });
    document.getElementById('logout').addEventListener('click', function(e) {
      e.preventDefault();
      window.location.href = '../login.html';
    });
    document.getElementById('tracking').addEventListener('click', function(e) {
      e.preventDefault();
      const searchContainer = document.getElementById('trackingSearch');
      searchContainer.style.display = searchContainer.style.display === 'none' ? 'block' : 'none';
    });

    // Search Function
    function searchTracking() {
      const trackingID = document.getElementById('searchInput').value;
      if (trackingID) {
        alert(`Searching for Tracking ID: ${trackingID}`);
        // Implement search logic here
      } else {
        alert("Please enter a Tracking ID.");
      }
    }
    document.getElementById('reports').addEventListener('click', function(e) {
      e.preventDefault();
      generatePDF();
    });

    function generatePDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();

      doc.setFontSize(18);
      doc.text('Recent Shipments Report', 14, 22);

      doc.setFontSize(12);
      doc.setTextColor(100);

      const columns = ["Tracking ID", "Status", "Sender", "Receiver", "Updated"];
      const rows = [];
      document.querySelectorAll('table tbody tr').forEach(tr => {
        const rowData = [];
        tr.querySelectorAll('td').forEach(td => {
          rowData.push(td.textContent);
        });
        rows.push(rowData);
      });

      doc.autoTable({
        startY: 30,
        head: [columns],
        body: rows,
        theme: 'striped'
      });

      doc.save('Recent_Shipments_Report.pdf');
    }
   

  </script>
</body>
</html>
