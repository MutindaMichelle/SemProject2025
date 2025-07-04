<?php
//This page now prevents duplicate applications,
//includes php session error messages, and emphasises on
// the pending status.
session_start();
include("connection.php");

// Set display errors for debugging (can be removed in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Check if user is logged in and is an artisan
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    $_SESSION['error_message'] = "You must be logged in as an artisan to apply for jobs.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Get artisan_id from database
$artisan_id = 0; // Initialize with a default value
$stmt_artisan = $conn->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
if ($stmt_artisan) {
    $stmt_artisan->bind_param("i", $user_id);
    $stmt_artisan->execute();
    $result_artisan = $stmt_artisan->get_result();
    if ($artisan_data = $result_artisan->fetch_assoc()) {
        $artisan_id = $artisan_data['artisan_id'];
    }
    $stmt_artisan->close();
} else {
    // Log error if statement preparation fails
    error_log("Failed to prepare artisan ID query: " . $conn->error);
}


// Check for valid job_id and artisan_id before proceeding with POST logic
if ($job_id === 0 || $artisan_id === 0) {
    $_SESSION['error_message'] = "Invalid job or artisan profile not found. Cannot process application.";
    header("Location: ArtisanDashboard.php"); // Redirect to dashboard if IDs are invalid
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $proposed_budget = trim($_POST['proposed_budget'] ?? '');
    $estimated_duration = trim($_POST['estimated_duration'] ?? '');

    // 2. Prevent Duplicate Applications
    $check_stmt = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND artisan_id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("ii", $job_id, $artisan_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $_SESSION['error_message'] = "You have already applied for this job.";
            header("Location: job_details.php?job_id=" . $job_id); // Redirect back to job details
            exit();
        }
        $check_stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error during duplicate application check: " . $conn->error;
        header("Location: ArtisanDashboard.php");
        exit();
    }


    if (!empty($message)) { // Ensure message is not empty as it's required
        // 3. Add 'status' to Insert Statement and use 'applied_at' (if exists, otherwise CURRENT_TIMESTAMP)
        // Assuming 'applied_at' is handled by DEFAULT CURRENT_TIMESTAMP in DB,
        // so we don't need to specify it in the INSERT query unless you want to override.
        // We *do* need to add the 'status' column.
        $status = 'pending'; // Default status for new applications

        $stmt_insert = $conn->prepare("INSERT INTO job_applications (job_id, artisan_id, message, availability, proposed_budget, estimated_duration, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt_insert) {
            $stmt_insert->bind_param("iisssss", $job_id, $artisan_id, $message, $availability, $proposed_budget, $estimated_duration, $status);

            if ($stmt_insert->execute()) {
                // 4. Replace alert and window.location.href with session messages and header redirect
                $_SESSION['success_message'] = "Application submitted successfully!";
                header("Location: ArtisanDashboard.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error submitting application: " . $stmt_insert->error;
                header("Location: job_details.php?job_id=" . $job_id); // Redirect back to job details with error
                exit();
            }
            $stmt_insert->close();
        } else {
            $_SESSION['error_message'] = "Database error preparing insert statement: " . $conn->error;
            header("Location: ArtisanDashboard.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Message field cannot be empty.";
        header("Location: job_details.php?job_id=" . $job_id); // Redirect back to job details with error
        exit();
    }
}

$conn->close(); // Close connection after all PHP logic
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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