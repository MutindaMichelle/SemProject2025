<?php
session_start();
include("connection.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'client') {
    header("Location: login.php");
    exit();
}

$job_id = $_GET['job_id'] ?? null;

if (!$job_id || !is_numeric($job_id)) {
    echo "Invalid job selected.";
    exit();
}

// Fetch job title
$job_title = '';
//$stmtJob = $conn->prepare("SELECT job_title FROM jobs_posted WHERE job_id = ? AND client_id = ?");
$stmtJob = $conn->prepare("SELECT job_title FROM jobs_posted WHERE id = ? AND client_id = ?"); // Updated to use 'id' as job_id for consistency
//Damn, another SQL query update to use 'id' as job_id for consistency

if (!$stmtJob) {
    echo "Error preparing job title query: " . $conn->error;
    exit;
}

$stmtJob->bind_param("ii", $job_id, $_SESSION['user_id']);
$stmtJob->execute();
$resultJob = $stmtJob->get_result();
if ($resultJob->num_rows > 0) {
    $job_title = $resultJob->fetch_assoc()['job_title'];
} else {
    echo "You are not authorized to view this job.";
    exit();
}

// Fetch applications
$stmt = $conn->prepare("
    SELECT ja.*, a.first_name, a.last_name, a.profile_image_url
    FROM job_applications ja
    JOIN artisans a ON ja.artisan_id = a.artisan_id
    WHERE ja.job_id = ?
    ORDER BY ja.applied_at DESC
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications for <?php echo htmlspecialchars($job_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .application-card {
            background: white;
            border: 1px solid #ccc;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 20px auto;
            border-radius: 8px;
            max-width: 800px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .application-card h3 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .application-card p {
            margin: 8px 0;
            color: #555;
        }
        .artisan-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            float: right;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <h1 style="text-align:center;">Applications for "<?php echo htmlspecialchars($job_title); ?>"</h1>

    <?php if ($result->num_rows === 0): ?>
        <p style="text-align: center;">No applications received for this job yet.</p>
    <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="application-card">
                <img src="<?php echo $row['profile_image_url'] ?: 'images/default_profile.jpg'; ?>" class="artisan-image" alt="Artisan">
                <h3><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></h3>
                <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                <?php if ($row['availability']): ?>
                    <p><strong>Availability:</strong> <?php echo htmlspecialchars($row['availability']); ?></p>
                <?php endif; ?>
                <?php if ($row['estimated_duration']): ?>
                    <p><strong>Estimated Duration:</strong> <?php echo htmlspecialchars($row['estimated_duration']); ?></p>
                <?php endif; ?>
                <?php if ($row['proposed_budget']): ?>
                    <p><strong>Proposed Budget:</strong> <?php echo htmlspecialchars($row['proposed_budget']); ?></p>
                <?php endif; ?>
                <p><small>Applied on: <?php echo date("F j, Y, g:i a", strtotime($row['applied_at'])); ?></small></p>
                <a href="viewArtisanProfile.php" class="view-profile-btn">View Full Profile</a>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</body>
</html>
