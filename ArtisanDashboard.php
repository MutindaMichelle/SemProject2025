<?php
session_start();
require_once 'connection.php'; // Adjust if your file is named differently

// ✅ Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch artisan profile
$profile_sql = "
    SELECT a.*, 
           GROUP_CONCAT(DISTINCT ae.expertise_name SEPARATOR ', ') AS expertise_list
    FROM artisans a
    LEFT JOIN artisan_expertise ae ON a.artisan_id = ae.artisan_id
    WHERE a.user_id = ?
    GROUP BY a.artisan_id
";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$artisan = $profile_result->fetch_assoc();

// ❗ If artisan not found, force logout
if (!$artisan) {
    header("Location: logout.php");
    echo "<script>alert('Your artisan profile is not found. Please contact support.');</script>";
    exit();
}

// ✅ Fetch job matches
$artisan_id = $artisan['artisan_id'];
$county = $artisan['county'];

$jobs_sql = "
    SELECT j.*, u.name AS client_name
    FROM jobs_posted j
    JOIN users u ON j.client_id = u.id
    WHERE j.required_expertise IN (
        SELECT expertise_name FROM artisan_expertise WHERE artisan_id = ?
    )
    OR j.county = ?
    ORDER BY 
        CASE 
            WHEN j.urgency = 'VERY URGENT!' THEN 1
            WHEN j.urgency = 'Within 24 hours' THEN 2
            WHEN j.urgency = 'Within 1 week' THEN 3
            ELSE 4
        END,
        j.id DESC
";
$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->bind_param("is", $artisan_id, $county);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Artisan Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #2c3e50;
            padding: 20px;
            color: white;
            text-align: center;
            position: relative;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
        }
        .logout-btn:hover {
            background-color:rgb(199, 119, 15);
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .profile {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .profile img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
        }
        .profile-details {
            flex-grow: 1;
        }
        .profile-details h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        .expertise-tags {
            margin-top: 10px;
        }
        .tag {
            background-color: #2ecc71;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 8px;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        .jobs-section h3 {
            color: #34495e;
        }
        .job-card {
            border: 1px solid #ddd;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .job-card h4 {
            margin: 0;
            color: #2980b9;
        }
        .job-meta {
            font-size: 0.9rem;
            color: #555;
            margin-top: 5px;
        }
        .job-actions {
            margin-top: 10px;
        }
        .job-actions a {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 10px;
            display: inline-block;
        }
        .job-actions a:hover {
            background-color: #2980b9;
        }

        .view-profile-btn {
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }
        .view-profile-btn:hover {
            background-color:rgb(86, 160, 179);
        }
    
    </style>
</head>
<body>
<header>
    <h1>Welcome to Your Artisan Dashboard</h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</header>

<div class="container">
    <!-- ✅ Artisan Profile Info -->
    <div class="profile">
        <?php if (!empty($artisan['profile_image_url'])): ?>
            <img src="<?php echo htmlspecialchars($artisan['profile_image_url']); ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="images/default_profile.jpg" alt="No Profile Image">
        <?php endif; ?>

       <div class="profile-details">
           <h2><?php echo htmlspecialchars($artisan['first_name'] . ' ' . $artisan['last_name']); ?></h2>
           <p><?php echo htmlspecialchars($artisan['description']); ?></p>
            <a href="viewArtisanProfile.php" class="view-profile-btn">View Full Profile</a>

            <div class="expertise-tags">
                <?php
                if (!empty($artisan['expertise_list'])) {
                    $expertise_array = explode(',', $artisan['expertise_list']);
                    foreach ($expertise_array as $skill) {
                        echo '<span class="tag">' . htmlspecialchars(trim($skill)) . '</span>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- ✅ Matching Jobs -->
    <div class="jobs-section">
        <h3>Matching Jobs</h3>
        <?php if ($jobs_result->num_rows > 0): ?>
            <?php while ($job = $jobs_result->fetch_assoc()): ?>
                <div class="job-card">
                    <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
                    <div class="job-meta">
                        <strong>Urgency:</strong> <?php echo htmlspecialchars($job['urgency']); ?> |
                        <strong>Budget:</strong> Ksh <?php echo number_format($job['budget'], 2); ?> 
                        <?php if ($job['is_negotiable']) echo '(Negotiable)'; ?> <br>
                        <strong>Location:</strong> <?php echo htmlspecialchars($job['sub_county'] . ', ' . $job['county']); ?> <br>
                        <strong>Client:</strong> <?php echo htmlspecialchars($job['client_name']); ?>
                    </div>
                    <p><?php echo htmlspecialchars($job['job_description']); ?></p>
                    <div class="job-actions">
                        <a href="job_details.php?job_id=<?php echo $job['id']; ?>">View Details</a>
                        <a href="apply_job.php?job_id=<?php echo $job['id']; ?>">Apply</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No matching jobs found at the moment.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
