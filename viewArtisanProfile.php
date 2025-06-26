<?php
session_start(); 

ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("connection.php"); 

// --- 1. Security Check: Ensure the user is logged in and is an artisan ---
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
     echo "
    <script>
        alert('You Are Either Not Logged In or Not an Artisan. Redirecting to LogIn Page...');
        setTimeout(function() {
            window.location.href = 'Registration.html';
        }, 1500);
    </script>
";
    exit();
}

$current_user_id = $_SESSION['user_id']; 

// Initialize variables to hold artisan data
$artisan_profile = null;
$error_message = '';

try {
    //SQL CODE
    $sql = "SELECT u.name AS user_full_name, u.email, u.phone, a.profile_image_url, a.first_name, a.last_name,
                a.age_range, a.description, a.expertise, a.years_worked, a.county, a.sub_county, a.availability,
                a.certifications
            FROM users AS u
            JOIN artisans AS a ON u.id = a.artisan_id
            WHERE
                u.id = ? AND u.userType = 'artisan'
            LIMIT 1"; 

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("SQL Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("i", $current_user_id); 
    $stmt->execute();
    $result = $stmt->get_result(); 

    if ($result->num_rows === 1) {
        $artisan_profile = $result->fetch_assoc();

        // --- 3. Process JSON fields ---
        
        if ($artisan_profile['expertise']) {
            $artisan_profile['expertise'] = json_decode($artisan_profile['expertise'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $artisan_profile['expertise'] = []; // Default to empty array on error
                error_log("JSON Decode Error for expertise (user_id: $current_user_id): " . json_last_error_msg());
            }
        } else {
            $artisan_profile['expertise'] = [];
        }

        if ($artisan_profile['certifications']) {
            $artisan_profile['certifications'] = json_decode($artisan_profile['certifications'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                
                $artisan_profile['certifications'] = []; 
                error_log("JSON Decode Error for certifications (user_id: $current_user_id): " . json_last_error_msg());
            }
        } else {
            $artisan_profile['certifications'] = [];
        }

    } else {
        header("Location: ArtisanProfile.php");
        exit();
    }

    $stmt->close();

} catch (Exception $e) {
    $error_message = "An error occurred while fetching profile: " . $e->getMessage();
    error_log($error_message); 
    // For user, provide a generic message or redirect
    header("Location: dashboard.php?error=profile_fetch_failed"); // Redirect to dashboard with error
    exit();
} finally {
    if ($conn) {
        $conn->close(); // Close connection
    }
}

// At this point, $artisan_profile contains all the data, ready for display.
// The HTML section below will use this data.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Artisan Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body

>
    <div class="profile-container">
        <?php if ($artisan_profile): ?>
            <h1><?php echo htmlspecialchars($artisan_profile['first_name'] . ' ' . $artisan_profile['last_name']); ?>'s Profile</h1>

            <div class="profile-header">
                <?php
                $profileImg = !empty($artisan_profile['profile_image_url']) ? htmlspecialchars($artisan_profile['profile_image_url']) : 'images/default_profile.jpg'; // Path to a default image if none uploaded
                ?>
                <img src="<?php echo $profileImg; ?>" alt="Profile Picture" class="profile-pic">
                <div class="header-info">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($artisan_profile['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($artisan_profile['phone']); ?></p>
                    <p><strong>Age Range:</strong> <?php echo htmlspecialchars($artisan_profile['age_range']); ?></p>
                    <p><strong>Years Worked:</strong> <?php echo htmlspecialchars($artisan_profile['years_worked']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($artisan_profile['sub_county'] . ', ' . $artisan_profile['county']); ?></p>
                    <p><strong>Availability:</strong> <?php echo htmlspecialchars($artisan_profile['availability']); ?></p>
                </div>
            </div>

            <div class="profile-section">
                <h3>About Me</h3>
                <p><?php echo nl2br(htmlspecialchars($artisan_profile['description'])); ?></p>
            </div>

            <div class="profile-section">
                <h3>Areas of Expertise</h3>
                <?php if (!empty($artisan_profile['expertise'])): ?>
                    <ul>
                        <?php foreach ($artisan_profile['expertise'] as $skill): ?>
                            <li><?php echo htmlspecialchars($skill); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No expertise listed yet.</p>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <h3>Certifications</h3>
                <?php if (!empty($artisan_profile['certifications'])): ?>
                    <ul>
                        <?php foreach ($artisan_profile['certifications'] as $cert_url): ?>
                            <li><a href="<?php echo htmlspecialchars($cert_url); ?>" target="_blank" rel="noopener noreferrer">View Certificate</a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No certifications uploaded yet.</p>
                <?php endif; ?>
            </div>

            <div class="profile-actions">
                <a href="ArtisanProfile.php" class="edit-profile-btn">Edit Profile</a>
                <!-- Add other action buttons here, e.g., to view public profile -->
            </div>

        <?php else: ?>
            <p>Your profile could not be loaded. Please try again or create your profile.</p>
            <a href="create_profile.php">Create Profile Now</a>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
