<?php
session_start();
require_once 'connection.php';

// I added a navbar to this page, made it green and responsive
// added a profile icon to the navbar and removed the profile part
// of the page.


//CHANGE TWO OF THE NIGHT:
//I added a functionality for the artisans to see the jobs
//they've applied to.
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
    // echo "<script>alert('Your artisan profile is not found. Please contact support.');</script>"; // Removed alert, as it blocks redirection
    exit();
}

// ✅ Fetch job matches
$artisan_id = $artisan['artisan_id'];
$county = $artisan['county'];

$jobs_sql = "
    SELECT j.*, u.name AS client_name, j.status
    FROM jobs_posted j
    JOIN users u ON j.client_id = u.id
    WHERE (
        j.required_expertise IN (
            SELECT expertise_name FROM artisan_expertise WHERE artisan_id = ?
        )
        OR j.county = ?
    )
    AND j.status = 'open' -- Filter to show only open jobs
    AND NOT EXISTS ( -- ADDED: Filter out jobs the artisan has already applied for
        SELECT 1
        FROM job_applications ja
        WHERE ja.job_id = j.id AND ja.artisan_id = ?
    )
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
$jobs_stmt->bind_param("isi", $artisan_id, $county, $artisan_id);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Body Styles */
        body {
            font-family: "Inter", sans-serif;
            /* Consistent font */
            background-color: #f0f2f5;
            /* Light grey background */
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }

        /* Navbar Styles (adapted from Client Dashboard) */
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
            /* Green brand color */
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

        /* NEW: Navbar Profile Picture */
        .navbar-profile-pic {
            width: 40px;
            /* Smaller size for navbar */
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4CAF50;
            /* Primary Green border */
            /* Blue border */
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .navbar-profile-pic:hover {
            transform: scale(1.05);
        }

        /* Top Section / Welcome Message (adapted for Artisan Dashboard) */
        .top-section {
            background-color: #E8F5E9;
            /* Light Green */
            /* Light blue background */
            padding: 40px 20px;
            text-align: center;
            margin-top: -1px;
            /* To prevent double border/shadow issues */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .welcome-message {
            font-size: 2.8em;
            color: #1E5128;
            /* Deep Green */
            margin-bottom: 10px;
            font-weight: 800;
        }

        .tagline {
            font-size: 1.3em;
            color: #555;
            margin-bottom: 30px;
        }

        .jobs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #E8F5E9;
            /* Light Green border */
            padding-bottom: 0.5rem;
        }

        .view-applications-btn {
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            white-space: nowrap;
            /* Prevent button text from wrapping */
        }

        .view-applications-btn:hover {
            background-color: #388E3C;
            /* Darker Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Main Content Container */
        .dashboard-content {
            flex-grow: 1;
            padding: 30px 20px;
            max-width: 1200px;
            margin: 20px auto;
            box-sizing: border-box;
        }

        /* Profile Section (adapted for Artisan Dashboard) */
        .profile-section {
            display: flex;
            flex-wrap: wrap;
            /* Allow wrapping on smaller screens */
            align-items: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #E8F5E9;
            /* Light Green border */
        }

        .profile-image-wrapper {
            flex-shrink: 0;
            /* Prevent image from shrinking */
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #4CAF50;
            /* Primary Green */
            /* Blue border for profile pic */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-details {
            flex-grow: 1;
            min-width: 250px;
            /* Ensure text content doesn't get too narrow */
        }

        .profile-details h2 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #1E5128;
            /* Deep Green */
            font-size: 1.8rem;
        }

        .profile-details p {
            color: #555;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .view-profile-btn {
            background-color: #4CAF50;
            /* Primary Green */
            /* Blue button */
            color: white;
            padding: 0.7rem 1.4rem;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: background-color 0.3s ease;
            display: inline-block;
            /* Allows margin-top */
            margin-top: 0.5rem;
        }

        .view-profile-btn:hover {
            background-color: #388E3C;
            /* Darker Green */
            /* Darker blue on hover */
        }

        .expertise-tags {
            margin-top: 1rem;
        }

        .tag {
            background-color: #66BB6A;
            /* Accent Green for tags */
            /* Green tag */
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            display: inline-block;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        /* Job Card Styles (adapted from Artisan Card on Client Dashboard) */
        .jobs-section h3 {
            color: #1E5128;
            /* Deep Green */
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #E8F5E9;
            /* Light Green border */
            padding-bottom: 0.5rem;
        }

        .job-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            /* Responsive grid */
            gap: 1.5rem;
        }

        .job-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            text-align: left;
            /* Changed from center to left for job details */
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            /* Align content to start */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #4CAF50;
            /* Primary Green accent */
            /* Accent border */
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .job-card h4 {
            font-size: 1.6em;
            /* Slightly larger for job titles */
            color: #1E5128;
            /* Deep Green */
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .job-meta {
            font-size: 0.95em;
            color: #666;
            margin: 5px 0;
            line-height: 1.4;
        }

        .job-card p {
            font-size: 0.95em;
            color: #444;
            margin: 10px 0;
        }

        .job-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .job-actions a {
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            flex-grow: 1;
            /* Allow buttons to grow */
            text-align: center;
        }

        .job-actions a:hover {
            background-color: #388E3C;
            /* Darker Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .view-profile-btn {
            background-color: #4CAF50;
            /* Primary Green */
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }

        .view-profile-btn:hover {
            background-color: #388E3C;
            /* Darker Green */
        }

        .job-status-info {
            background-color: #f0f0f0;
            color: #555;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 600;
            text-align: center;
            flex-grow: 1;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 15px auto;
                padding: 15px;
            }

            .profile {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .profile img {
                margin-bottom: 10px;
            }

            .job-card {
                padding: 10px;
            }

            .job-actions a {
                display: block;
                margin-right: 0;
                margin-bottom: 10px;
            }

            /* Navbar adjustments for small screens */
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
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.5rem;
            }

            .welcome-message {
                font-size: 1.8em;
            }

            .tagline {
                font-size: 0.9em;
            }

            .profile-details h2 {
                font-size: 1.4rem;
            }

            .job-card h4 {
                font-size: 1.2em;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="#" class="navbar-brand">JuaKazi</a>
            <!-- Add other left-aligned links if any -->
        </div>
        <div class="navbar-right">
            <!-- NEW: Profile Picture Link -->
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

    <!-- Top Section -->
    <div class="top-section">
        <h1 class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
        <p class="tagline">Your skills, your opportunities. Find jobs that match your expertise and location.</p>
    </div>

    <div class="dashboard-content">


        <!-- ✅ Matching Jobs -->
        <div class="jobs-section">
            <div class="jobs-header">
                <h3>Matching Jobs Based on Your Location</h3>
                <a href="ArtisanViewApplications.php" class="view-applications-btn">View My Applications</a>
            </div>
            <div class="job-grid">
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
                                <?php
                                // Always show View Details button
                                //echo '<a href="job_details.php?job_id=' . $job['id'] . '" class="job-action-btn">View Details</a>';
                                if (isset($job['status']) && $job['status'] === 'open') {
                                    echo '<a href="apply_job.php?job_id=' . $job['id'] . '" class="job-action-btn apply-btn">Apply</a>';
                                    echo '<a href="job_details.php?job_id=' . $job['id'] . '" class="job-action-btn">View Details</a>';
                                } else {
                                    echo '<span class="job-status-info">Status: ' . htmlspecialchars(ucfirst($job['status'] ?? 'Unknown')) . '</span>';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No matching jobs found at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>