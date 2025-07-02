<?php
session_start();
include("connection.php");

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['job_id'])) {
    echo "Job not found.";
    exit;
}

$job_id = (int)$_GET['job_id'];

$stmt = $conn->prepare("
    SELECT j.*, u.name AS client_name, u.phone AS client_phone
    FROM jobs_posted j
    JOIN users u ON j.client_id = u.id
    WHERE j.id = ?
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Job not found or no access.";
    exit;
}

$job = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Job Details</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* CARD STYLE */
        .job-card {
            max-width: 800px;
            margin: 30px auto;
            padding: 25px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-left: 5px solid #3498db;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .job-card h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .job-card p {
            font-size: 16px;
            color: #333;
            margin: 10px 0;
        }

        .job-card strong {
            color: #555;
        }

        .job-image {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            margin-top: 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .button-row {
            margin-top: 25px;
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 18px;
            font-size: 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }

        .btn-apply {
            background-color: #27ae60;
            color: white;
        }

        .btn-back {
            background-color: #bdc3c7;
            color: #2c3e50;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <div class="job-card">
        <h1><?php echo htmlspecialchars($job['job_title']); ?></h1>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($job['job_description'])); ?></p>
        <p><strong>Expertise Needed:</strong> <?php echo htmlspecialchars($job['required_expertise']); ?></p>
        <p><strong>Urgency:</strong> <?php echo htmlspecialchars($job['urgency']); ?></p>
        <p><strong>Budget:</strong> Ksh <?php echo number_format($job['budget'], 2); ?><?php if ($job['is_negotiable']) echo " (Negotiable)"; ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($job['sub_county']) . ', ' . htmlspecialchars($job['county']); ?></p>
        <p><strong>Posted by:</strong> <?php echo htmlspecialchars($job['client_name']); ?> (<?php echo htmlspecialchars($job['client_phone']); ?>)</p>
        <p><strong>Posted on:</strong> <?php echo date("F j, Y, g:i a", strtotime($job['created_at'])); ?></p>

        <?php if (!empty($job['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($job['image_path']); ?>" class="job-image" alt="Job Image">
        <?php endif; ?>

        <div class="button-row">
            <a href="apply_job.php?job_id=<?php echo $job_id; ?>" class="btn btn-apply">Apply Now</a>
            <a href="ArtisanDashboard.php" class="btn btn-back">Back to Dashboard</a>
        </div>
    </div>

</body>
</html>
