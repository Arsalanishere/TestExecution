<?php
session_start();
// FIX: Changed condition to check for the explicit 'user' role for robustness
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: ../login.html");
    exit();
}
$user = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Cargo - Cargo Service</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background-color: #f8f9fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

h2 {
  font-weight: 600;
  color: #0d6efd;
}

.card {
  border-radius: 12px;
  border: none;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.card:hover {
  transform: translateY(-3px);
  transition: 0.2s ease-in-out;
}

.btn-primary {
  background: linear-gradient(45deg,#0d6efd,#4e9af1);
  border: none;
  border-radius: 8px;
}

.container-form {
  max-width: 800px;
  margin: 40px auto; /* center */
}
</style>
</head>
<body>

<div class="container-form">
    <h2 class="text-center mb-1"><i class="bi bi-truck"></i> Book Your Cargo</h2>
    <p class="text-center text-muted mb-4">Fill in details to create a new cargo booking.</p>

    <div class="card p-4">
        <form action="php/book.php" method="POST">
            <h5 class="mb-3">Sender Details</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="sender_name" class="form-label">Sender Name</label>
                    <input type="text" class="form-control" id="sender_name" name="sender_name" required>
                </div>
                <div class="col-md-6">
                    <label for="sender_phone" class="form-label">Sender Phone</label>
                    <input type="text" class="form-control" id="sender_phone" name="sender_phone" oninput="limitelevenDigits(this)" required>
                </div>
            </div>

            <h5 class="mt-4 mb-3">Receiver Details</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="receiver_name" class="form-label">Receiver Name</label>
                    <input type="text" class="form-control" id="receiver_name" name="receiver_name" required>
                </div>
                <div class="col-md-6">
                    <label for="receiver_phone" class="form-label">Receiver Phone</label>
                    <input type="text" class="form-control" id="receiver_phone" name="receiver_phone" oninput="limitelevenDigits(this)" required>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <label for="pickup" class="form-label">Pickup Location</label>
                    <input type="text" class="form-control" id="pickup" name="pickup" required>
                </div>
                <div class="col-md-6">
                    <label for="destination" class="form-label">Destination</label>
                    <input type="text" class="form-control" id="destination" name="destination" required>
                </div>
            </div>

            <!-- Vehicle + Cargo Type -->
            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <label for="vehicle" class="form-label">Select Vehicle</label>
                    <select class="form-select" id="vehicle" name="vehicle" required>
                        <option value="">Choose a vehicle</option>
                        <option value="Truck">Truck</option>
                        <option value="Shehzor">Shehzor</option>
                        <option value="Suzuki">Suzuki</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="cargo_type" class="form-label">Cargo Type</label>
                    <select class="form-select" id="cargo_type" name="cargo_type" onchange="toggleOtherCargo()">
                        <option value="">Select cargo type</option>
                        <option value="Glass">Glass</option>
                        <option value="Wood">Wood</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Other">Other (Specify below)</option>
                    </select>
                    <input type="text" class="form-control mt-2" id="other_cargo" name="other_cargo" placeholder="Specify other cargo type" style="display:none;">
                </div>
            </div>

            <div class="mt-3">
                <label for="labour_required" class="form-label">Need Labor?</label>
                <select class="form-select" id="labour_required" name="labour_required" onchange="toggleLabourCount()">
                    <option value="No">No</option>
                    <option value="Yes">Yes</option>
                </select>
            </div>

            <div class="mt-3" id="labour_count_section" style="display:none;">
                <label for="labour_count" class="form-label">Number of Persons</label>
                <input type="number" class="form-control" id="labour_count" name="labour_count" min="1" max="50" oninput="limitTwoDigits(this)">
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Book Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleLabourCount(){
    const labourRequired = document.getElementById('labour_required').value === 'Yes';
    document.getElementById('labour_count_section').style.display = labourRequired ? 'block' : 'none';
    if(!labourRequired) document.getElementById('labour_count').value = '';
}
function limitTwoDigits(input){
    // Keep the value between 1 and 20 (and max length 2 for user experience)
    if(input.value.length > 2) input.value = input.value.slice(0,2);
    let val = parseInt(input.value);
    if(val > 20) input.value = 20;
    else if(val < 1 || isNaN(val)) input.value = '';
}
function limitelevenDigits(input){
    // Allow only digits and limit to 11 characters
    input.value = input.value.replace(/[^\d]/g,'').slice(0,11);
}
function toggleOtherCargo(){
    const isOther = document.getElementById('cargo_type').value === 'Other';
    document.getElementById('other_cargo').style.display = isOther ? 'block' : 'none';
    if(!isOther) document.getElementById('other_cargo').value = '';
}
</script>

</body>
</html>
