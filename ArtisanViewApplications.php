<?php
session_start();
include("connection.php"); // Your database connection file

// This page handles the artisan viewing the pages they've applied to,
// as well as deleting some applications
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Security Check: Ensure the user is logged in and is an artisan
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    $_SESSION['error_message'] = "You must be logged in as an artisan to view your applications.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$artisan_id = 0; // Initialize artisan_id

// Fetch the artisan_id from the artisans table using the user_id
$stmt_artisan = $conn->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
if ($stmt_artisan) {
    $stmt_artisan->bind_param("i", $user_id);
    $stmt_artisan->execute();
    $result_artisan = $stmt_artisan->get_result();
    if ($row_artisan = $result_artisan->fetch_assoc()) {
        $artisan_id = $row_artisan['artisan_id'];
    }
    $stmt_artisan->close();
} else {
    error_log("Failed to prepare artisan ID query in ArtisanViewApplications.php: " . $conn->error);
}

// If artisan_id is not found, it means their profile might not be complete
if ($artisan_id === 0) {
    $_SESSION['error_message'] = "Your artisan profile is not complete. Please complete it to view applications.";
    header("Location: ArtisanDashboard.php"); // Redirect back to dashboard
    exit();
}

// --- NEW: Handle Application Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application_id'])) {
    $application_to_delete_id = (int)$_POST['delete_application_id'];

    // Ensure the artisan is deleting their own application
    $delete_stmt = $conn->prepare("DELETE FROM job_applications WHERE id = ? AND artisan_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $application_to_delete_id, $artisan_id);
        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Application deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Application not found or you don't have permission to delete it.";
            }
        } else {
            $_SESSION['error_message'] = "Error deleting application: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error preparing delete statement: " . $conn->error;
    }
    // Redirect to clear POST data and show message
    header("Location: ArtisanViewApplications.php");
    exit();
}
// --- END NEW: Handle Application Deletion ---


// --- Fetch Artisan's Submitted Applications ---
$applications = []; // Initialize array to hold applications
$applications_sql = "
    SELECT
        ja.id AS application_id,
        ja.applied_at,
        ja.status,
        ja.message,
        ja.availability,
        ja.proposed_budget,
        ja.estimated_duration,
        jp.job_title,
        jp.id AS job_id,
        jp.budget AS job_original_budget,
        jp.is_negotiable,
        u.name AS client_name,
        u.phone AS client_phone
    FROM
        job_applications ja
    JOIN
        jobs_posted jp ON ja.job_id = jp.id
    JOIN
        users u ON jp.client_id = u.id
    WHERE
        ja.artisan_id = ?
    ORDER BY
        ja.applied_at DESC
";

$applications_stmt = $conn->prepare($applications_sql);
if ($applications_stmt) {
    $applications_stmt->bind_param("i", $artisan_id);
    $applications_stmt->execute();
    $applications_result = $applications_stmt->get_result();
    while ($row = $applications_result->fetch_assoc()) {
        $applications[] = $row;
    }
    $applications_stmt->close();
} else {
    error_log("Failed to prepare applications query in ArtisanViewApplications.php: " . $conn->error);
    $_SESSION['error_message'] = "Could not load your applications due to a database error.";
}

// --- Fetch Artisan's Profile Image for Navbar ---
$navbar_artisan_profile_image_url = 'images/default_profile.jpg'; // Default image
$navbar_profile_stmt = $conn->prepare("SELECT profile_image_url FROM artisans WHERE user_id = ?");
if ($navbar_profile_stmt) {
    $navbar_profile_stmt->bind_param("i", $user_id);
    $navbar_profile_stmt->execute();
    $navbar_profile_result = $navbar_profile_stmt->get_result();
    if ($navbar_profile_data = $navbar_profile_result->fetch_assoc()) {
        if (!empty($navbar_profile_data['profile_image_url'])) {
            $navbar_artisan_profile_image_url = htmlspecialchars($navbar_profile_data['profile_image_url']);
        }
    }
    $navbar_profile_stmt->close();
} else {
    error_log("Failed to prepare navbar artisan profile statement in ArtisanViewApplications.php: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
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

        /* Navbar Styles (copied for consistency) */
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

        /* Main Content Container */
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #1E5128;
            /* Deep Green */
            margin-bottom: 30px;
            font-size: 2.5em;
        }

        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .application-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #4CAF50;
            /* Primary Green accent */
        }

        .application-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .application-card h4 {
            font-size: 1.6em;
            color: #1E5128;
            /* Deep Green */
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 600;
            display: flex;
            /* To align title and status badge */
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }

        .application-card h4 a {
            text-decoration: none;
            color: inherit;
            /* Inherit color from h4 */
            flex-grow: 1;
            /* Allow job title to take available space */
        }

        .application-card h4 a:hover {
            text-decoration: underline;
        }

        .application-meta {
            font-size: 0.95em;
            color: #666;
            margin: 5px 0;
            line-height: 1.4;
        }

        .application-card p {
            font-size: 0.95em;
            color: #444;
            margin: 10px 0;
        }

        .application-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
            /* Ensure buttons take full width if stacked */
        }

        .application-actions .btn {
            /* General button style for this section */
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
            text-align: center;
            border: none;
            /* Remove default button border */
            cursor: pointer;
            /* Indicate it's clickable */
        }

        .application-actions .btn:hover {
            background-color: #388E3C;
            /* Darker Green */
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* NEW: Delete Button Specific Style */
        .application-actions .btn-delete {
            background-color: #dc3545;
            /* Red for delete */
        }

        .application-actions .btn-delete:hover {
            background-color: #c82333;
            /* Darker red */
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 15px;
            /* Space from job title */
            white-space: nowrap;
            /* Prevent status text from wrapping */
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
            .navbar {
                padding: 10px 20px;
                flex-direction: column;
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

            .container {
                margin: 15px auto;
                padding: 15px;
            }

            h1 {
                font-size: 2em;
            }

            .application-card {
                padding: 15px;
            }

            .application-card h4 {
                font-size: 1.4em;
                flex-direction: column;
                /* Stack title and badge on small screens */
                align-items: flex-start;
            }

            .status-badge {
                margin-left: 0;
                margin-top: 5px;
            }

            .application-actions .btn {
                /* Apply to all buttons in this section */
                display: block;
                margin-right: 0;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.8em;
            }

            .application-card h4 {
                font-size: 1.2em;
            }

            .application-meta,
            .application-card p {
                font-size: 0.9em;
            }

            .application-actions .btn {
                font-size: 0.8em;
                padding: 8px 10px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="ArtisanDashboard.php" class="navbar-brand">JuaKazi</a>
        </div>
        <div class="navbar-right">
            <a href="viewArtisanProfile.php">
                <img src="<?php echo $navbar_artisan_profile_image_url; ?>" alt="Profile Picture" class="navbar-profile-pic">
            </a>
            <a href="logout.php" class="navbar-btn logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>My Submitted Applications</h1>

        <?php
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

        <div class="applications-grid">
            <?php if (!empty($applications)): ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <h4>
                            <a href="job_details.php?job_id=<?php echo htmlspecialchars($app['job_id']); ?>">
                                <?php echo htmlspecialchars($app['job_title']); ?>
                            </a>
                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($app['status'])); ?>">
                                <?php echo htmlspecialchars($app['status']); ?>
                            </span>
                        </h4>
                        <div class="application-meta">
                            <strong>Client:</strong> <?php echo htmlspecialchars($app['client_name']); ?> (<?php echo htmlspecialchars($app['client_phone']); ?>) <br>
                            <strong>Applied On:</strong> <?php echo date("F j, Y, g:i a", strtotime($app['applied_at'])); ?>
                        </div>
                        <p><strong>My Message:</strong> <?php echo nl2br(htmlspecialchars($app['message'])); ?></p>
                        <?php if (!empty($app['availability'])): ?>
                            <p><strong>My Availability:</strong> <?php echo htmlspecialchars($app['availability']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($app['estimated_duration'])): ?>
                            <p><strong>My Estimated Duration:</strong> <?php echo htmlspecialchars($app['estimated_duration']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($app['proposed_budget'])): ?>
                            <p><strong>My Proposed Budget:</strong> Ksh <?php echo number_format($app['proposed_budget'], 2); ?></p>
                        <?php endif; ?>

                        <div class="application-actions">

                            <?php if ($app['status'] === 'pending' || $app['status'] === 'withdrawn'): ?>
                                <!-- Only allow deletion if status is pending or withdrawn -->
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="delete_application_id" value="<?php echo htmlspecialchars($app['application_id']); ?>">
                                    <button type="submit" class="btn btn-delete">Delete Application</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-applications-message">You haven't submitted any job applications yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>