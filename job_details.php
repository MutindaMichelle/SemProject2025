<?php
session_start();
include("connection.php");
//Hey Michelle! To this page i added a navbar, added functionality to fetch the profile image from the database for
// the nav bar, made the page responsive, and made it green themed.


// Check if the user is logged in AND is an artisan
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

// --- NEW: Fetch artisan profile for the navbar image ---
$artisan_profile_sql = "SELECT profile_image_url FROM artisans WHERE user_id = ?";
$artisan_profile_stmt = $conn->prepare($artisan_profile_sql);
if ($artisan_profile_stmt) {
    $artisan_profile_stmt->bind_param("i", $user_id);
    $artisan_profile_stmt->execute();
    $artisan_profile_result = $artisan_profile_stmt->get_result();
    $artisan = $artisan_profile_result->fetch_assoc(); // Populate $artisan variable
    $artisan_profile_stmt->close();
} else {
    // Handle error if statement preparation fails
    error_log("Failed to prepare artisan profile statement in job_details.php: " . $conn->error);
    $artisan = ['profile_image_url' => '']; // Set a default empty value to prevent errors
}
// --- END NEW ---


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
        /* General Body Styles */
        body {
            font-family: "Inter", sans-serif;
            background-color: #f0f2f5;
            /* Light grey background */
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }

        /* Navbar Styles (copied from ArtisanDashboard.php for consistency) */
        .navbar {
            background-color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 60px;
            box-sizing: border-box;
        }

        .navbar-left,
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar-brand {
            font-size: 1.8em;
            font-weight: 700;
            color: #4CAF50;
            /* Primary Green */
            text-decoration: none;
        }

        .navbar-btn {
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .navbar-btn:hover {
            background-color: #388E3C;
            /* Darker Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .navbar-btn.logout-btn {
            background-color: #f44336;
            /* Red for logout button */
        }

        .navbar-btn.logout-btn:hover {
            background-color: #d32f2f;
        }

        /* Navbar Profile Picture */
        .navbar-profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4CAF50;
            /* Primary Green border */
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .navbar-profile-pic:hover {
            transform: scale(1.05);
        }

        /* CARD STYLE */
        .job-card {
            max-width: 800px;
            margin: 30px auto;
            padding: 25px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-left: 5px solid #4CAF50;
            /* Green accent */
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .job-card h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #1E5128;
            /* Deep Green */
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
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
        }

        .btn-back {
            background-color: #bdc3c7;
            /* Grey for back button */
            color: #2c3e50;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Responsive Adjustments (copied from ArtisanDashboard.php for consistency) */
        @media (max-width: 768px) {
            .navbar {
                padding: 10px 20px;
                flex-direction: column;
                /* Stack navbar items */
                height: auto;
            }

            .navbar-left,
            .navbar-right {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
                gap: 10px;
            }

            .navbar-brand {
                font-size: 1.5em;
            }

            .navbar-btn {
                padding: 8px 15px;
                font-size: 0.9em;
            }

            .navbar-profile-pic {
                width: 35px;
                height: 35px;
                border-width: 1px;
            }

            .job-card {
                margin: 15px auto;
                padding: 15px;
            }

            .button-row {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .job-card h1 {
                font-size: 24px;
            }

            .job-card p {
                font-size: 14px;
            }

            .btn {
                font-size: 14px;
                padding: 8px 15px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="ArtisanDashboard.php" class="navbar-brand">JuaKazi</a>
            <!-- Add other left-aligned links if any -->
        </div>
        <div class="navbar-right">
            <!-- Profile Picture Link (now with fetched $artisan data) -->
            <a href="viewArtisanProfile.php">
                <?php if (!empty($artisan['profile_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($artisan['profile_image_url']); ?>" alt="Profile Picture" class="navbar-profile-pic">
                <?php else: ?>
                    <img src="images/default_profile.jpg" alt="Default Profile" class="navbar-profile-pic">
                <?php endif; ?>
            </a>
            <a href="logout.php" class="navbar-btn logout-btn">Logout</a>
        </div>
    </nav>

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