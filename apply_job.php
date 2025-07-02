<?php
session_start();
include("connection.php");

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Get artisan_id from database
$stmt = $conn->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$artisan = $result->fetch_assoc();
$artisan_id = $artisan['artisan_id'] ?? 0;
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $proposed_budget = trim($_POST['proposed_budget'] ?? '');
    $estimated_duration = trim($_POST['estimated_duration'] ?? '');

    if ($artisan_id && $job_id && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO job_applications (job_id, artisan_id, message, availability, proposed_budget, estimated_duration) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $job_id, $artisan_id, $message, $availability, $proposed_budget, $estimated_duration);

        if ($stmt->execute()) {
            echo "<script>alert('Application submitted successfully!'); window.location.href='ArtisanDashboard.php';</script>";
        } else {
            echo "Error applying: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Missing required fields.";
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Apply to Job</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 700px;
            background: white;
            margin: 60px auto;
            padding: 30px 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 5px solid var(--primary-color);
            border-radius: 10px;
        }

        h1 {
            color: var(--dark-color);
            margin-bottom: 25px;
            text-align: center;
        }

        label {
            font-weight: bold;
            display: block;
            margin: 15px 0 5px;
        }

        textarea,
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            width: 100%;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        @media (max-width: 768px) {
            .container {
                margin: 30px 15px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Apply for This Job</h1>
        <form method="POST">
            <label for="message">Why should the client pick you?</label>
            <textarea name="message" rows="5" required placeholder="Briefly explain your experience or past projects related to this job..."></textarea>

            <label for="availability">Your Availability (e.g. Mon-Fri, 9AM - 5PM)</label>
            <input type="text" name="availability" id="availability" required placeholder="Your available days and hours">

            <label for="estimated_duration">Estimated Time to Complete the Job</label>
            <input type="text" name="estimated_duration" id="estimated_duration" required placeholder="e.g., 2 days, 5 hours">

            <label for="proposed_budget">Proposed Budget (Ksh)</label>
            <input type="number" name="proposed_budget" id="proposed_budget" placeholder="Optional - e.g. 2500">

            <button type="submit" class="btn">Submit Application</button>
        </form>
    </div>

</body>
</html>
