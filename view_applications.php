<?php
session_start();
include("connection.php");
// I genuinely do not remember the changes i made here, lkn it works so yay!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'client') {
    $_SESSION['error_message'] = "You must be logged in as a client to view job applications.";
    header("Location: ArtisanDashboard.php");
    exit();
}

$client_id = $_SESSION['user_id'];
$job_id = $_GET['job_id'] ?? null;

if (!$job_id || !is_numeric($job_id)) {
    $_SESSION['error_message'] = "Invalid job selected.";
    header("Location: ClientDahboard.php"); // Redirect to client dashboard if job_id is invalid
    exit();
}

// Fetch job details including its current status
$job_details = null;
$stmtJob = $conn->prepare("SELECT job_title, status FROM jobs_posted WHERE id = ? AND client_id = ?");
if (!$stmtJob) {
    $_SESSION['error_message'] = "Error preparing job details query: " . $conn->error;
    header("Location: client_dashboard.php");
    exit();
}
$stmtJob->bind_param("ii", $job_id, $client_id);
$stmtJob->execute();
$resultJob = $stmtJob->get_result();
if ($resultJob->num_rows > 0) {
    $job_details = $resultJob->fetch_assoc();
    $job_title = $job_details['job_title'];
    $job_status = $job_details['status']; // Get current job status
} else {
    $_SESSION['error_message'] = "Job not found or you are not authorized to view its applications.";
    header("Location: client_dashboard.php");
    exit();
}
$stmtJob->close();


// --- NEW: Handle Accept Application POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_application_id'])) {
    $accepted_application_id = (int)$_POST['accept_application_id'];
    $accepted_artisan_id = (int)$_POST['artisan_id']; // Artisan ID of the accepted application

    // Start a transaction for atomicity
    $conn->begin_transaction();
    $success = true;

    try {
        // 1. Update the jobs_posted table: Set job status to 'ongoing' and assign accepted_artisan_id
        $update_job_stmt = $conn->prepare("UPDATE jobs_posted SET status = 'ongoing', accepted_artisan_id = ? WHERE id = ? AND client_id = ? AND status = 'open'");
        if (!$update_job_stmt) {
            throw new Exception("Error preparing job update: " . $conn->error);
        }
        $update_job_stmt->bind_param("iii", $accepted_artisan_id, $job_id, $client_id);
        $update_job_stmt->execute();
        if ($update_job_stmt->affected_rows === 0) {
            // This means the job was not 'open' or client_id didn't match, or already updated
            throw new Exception("Job not found, not open, or already assigned. Cannot accept application.");
        }
        $update_job_stmt->close();

        // 2. Update the accepted application's status to 'accepted'
        $update_accepted_app_stmt = $conn->prepare("UPDATE job_applications SET status = 'accepted' WHERE id = ? AND job_id = ? AND artisan_id = ?");
        if (!$update_accepted_app_stmt) {
            throw new Exception("Error preparing accepted application update: " . $conn->error);
        }
        $update_accepted_app_stmt->bind_param("iii", $accepted_application_id, $job_id, $accepted_artisan_id);
        $update_accepted_app_stmt->execute();
        if ($update_accepted_app_stmt->affected_rows === 0) {
            throw new Exception("Accepted application not found or status already set.");
        }
        $update_accepted_app_stmt->close();

        // 3. Update all other applications for this job to 'withdrawn'
        $update_others_stmt = $conn->prepare("UPDATE job_applications SET status = 'withdrawn' WHERE job_id = ? AND id != ?");
        if (!$update_others_stmt) {
            throw new Exception("Error preparing other applications update: " . $conn->error);
        }
        $update_others_stmt->bind_param("ii", $job_id, $accepted_application_id);
        $update_others_stmt->execute();
        $update_others_stmt->close();

        $conn->commit(); // Commit the transaction if all updates were successful
        $_SESSION['success_message'] = "Application accepted successfully! Job status updated to 'ongoing'.";
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $_SESSION['error_message'] = "Failed to accept application: " . $e->getMessage();
        error_log("Accept Application Error: " . $e->getMessage()); // Log error for debugging
    }

    // Redirect to clear POST data and show message
    header("Location: view_applications.php?job_id=" . $job_id);
    exit();
}
// --- END NEW: Handle Accept Application POST Request ---


// Fetch applications (now also considering job_status for conditional display)
$applications = [];
$stmt = $conn->prepare("
    SELECT ja.*, a.first_name, a.last_name, a.profile_image_url, a.user_id AS artisan_user_id
    FROM job_applications ja
    JOIN artisans a ON ja.artisan_id = a.artisan_id
    WHERE ja.job_id = ?
    ORDER BY ja.applied_at DESC
");
if (!$stmt) {
    $_SESSION['error_message'] = "Error preparing applications query: " . $conn->error;
    header("Location: client_dashboard.php");
    exit();
}
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Applications for <?php echo htmlspecialchars($job_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 900px;
            /* Increased max-width for better layout */
            background: white;
            margin: 60px auto;
            padding: 30px 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #4CAF50;
            /* Green primary color */
            border-radius: 10px;
        }

        h1 {
            color: #1E5128;
            /* Deep Green */
            margin-bottom: 25px;
            text-align: center;
        }

        .job-status-display {
            /* NEW: Style for job status */
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            background-color: #e8f5e9;
            /* Light green */
            color: #1E5128;
            /* Deep green */
            border: 1px solid #4CAF50;
        }

        .application-card {
            background: white;
            border: 1px solid #ccc;
            border-left: 4px solid #4CAF50;
            /* Green primary color */
            padding: 20px;
            margin: 20px auto;
            border-radius: 8px;
            max-width: 800px;
            /* Keep individual card width */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .application-card h3 {
            margin-bottom: 5px;
            color: #1E5128;
            /* Deep Green */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .application-card p {
            margin: 0;
            /* Adjusted margin */
            color: #555;
        }

        .artisan-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #eee;
        }

        .artisan-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4CAF50;
            /* Green border */
            flex-shrink: 0;
        }

        .artisan-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
        }

        .application-details {
            margin-bottom: 15px;
        }

        .application-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 18px;
            font-size: 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            border: none;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.2s ease;
            flex-grow: 1;
            /* Allow buttons to grow */
        }

        .btn-view-profile {
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
        }

        .btn-view-profile:hover {
            background-color: #388E3C;
            /* Darker Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* NEW: Accept Button Style */
        .btn-accept {
            background-color: #28a745;
            /* Bootstrap Success Green */
            color: white;
        }

        .btn-accept:hover {
            background-color: #218838;
            /* Darker Success Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Status badges (re-using styles from artisan dashboard) */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-pending {
            background-color: #ffc107;
            color: #333;
        }

        /* Yellow */
        .status-accepted {
            background-color: #28a745;
            color: white;
        }

        /* Green */
        .status-withdrawn {
            background-color: #6c757d;
            color: white;
        }

        /* Grey */

        /* Message for no applications */
        .no-applications-message {
            text-align: center;
            font-size: 1.2em;
            color: #555;
            padding: 50px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 30px 15px;
                padding: 20px;
            }

            .application-card {
                padding: 15px;
            }

            .application-card h3 {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .artisan-info {
                flex-direction: column;
                text-align: center;
            }

            .artisan-image {
                margin-bottom: 10px;
            }

            .application-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Applications for "<?php echo htmlspecialchars($job_title); ?>"</h1>

        <?php
        // Display job status
        echo '<div class="job-status-display">Job Status: ' . htmlspecialchars(ucfirst($job_status)) . '</div>';

        // Display session messages if any
        if (isset($_SESSION['success_message'])) {
            echo '<p style="color: green; text-align: center; margin-bottom: 20px;">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<p style="color: red; text-align: center; margin-bottom: 20px;">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>

        <?php if (empty($applications)): ?>
            <p class="no-applications-message">No applications received for this job yet.</p>
        <?php else: ?>
            <?php foreach ($applications as $row): ?>
                <?php
                // Determine if the application should be shown based on job status
                // If job is ongoing/completed/cancelled, only show the accepted application
                // If job is open, show all pending applications
                $display_application = true;
                if ($job_status !== 'open' && $row['status'] !== 'accepted') {
                    $display_application = false; // Hide non-accepted applications if job is not open
                }
                ?>

                <?php if ($display_application): ?>
                    <div class="application-card">
                        <div class="artisan-info">
                            <img src="<?php echo htmlspecialchars($row['profile_image_url'] ?: 'images/default_profile.jpg'); ?>" class="artisan-image" alt="Artisan">
                            <span class="artisan-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($row['status'])); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </div>

                        <div class="application-details">
                            <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                            <?php if ($row['availability']): ?>
                                <p><strong>Availability:</strong> <?php echo htmlspecialchars($row['availability']); ?></p>
                            <?php endif; ?>
                            <?php if ($row['estimated_duration']): ?>
                                <p><strong>Estimated Duration:</strong> <?php echo htmlspecialchars($row['estimated_duration']); ?></p>
                            <?php endif; ?>
                            <?php if ($row['proposed_budget']): ?>
                                <p><strong>Proposed Budget:</strong> Ksh <?php echo htmlspecialchars(number_format($row['proposed_budget'], 2)); ?></p>
                            <?php endif; ?>
                            <p><small>Applied on: <?php echo date("F j, Y, g:i a", strtotime($row['applied_at'])); ?></small></p>
                        </div>

                        <div class="application-actions">
                            <a href="viewArtisanProfile.php?artisan_id=<?php echo htmlspecialchars($row['artisan_user_id']); ?>" class="btn btn-view-profile">View Full Profile</a>

                            <?php
                            // Show Accept button ONLY if job is 'open' and application is 'pending'
                            if ($job_status === 'open' && $row['status'] === 'pending') {
                                echo '<form method="POST" style="display: inline-block; margin: 0;">';
                                echo '<input type="hidden" name="accept_application_id" value="' . htmlspecialchars($row['id']) . '">';
                                echo '<input type="hidden" name="artisan_id" value="' . htmlspecialchars($row['artisan_id']) . '">';
                                echo '<button type="submit" class="btn btn-accept">Accept Application</button>';
                                echo '</form>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; // End display_application check 
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>