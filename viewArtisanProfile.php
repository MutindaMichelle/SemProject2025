<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("connection.php");

$artisan_profile = null;
$error_message = '';
$artisan_to_view_id = null; 
$is_viewing_own_profile = false; 

// Priority 1: Check if 'artisan_id' is provided in the URL (e.g., from client_dashboard.php)
if (isset($_GET['artisan_id'])) {
    $artisan_to_view_id = (int)$_GET['artisan_id']; 
    // Check if the currently logged-in user is an artisan viewing their OWN profile
    if (isset($_SESSION['user_id']) && $_SESSION['userType'] === 'artisan' && $_SESSION['user_id'] == $artisan_to_view_id) {
        $is_viewing_own_profile = true;
    }
}
// Priority 2: If no 'artisan_id' in URL, but a user is logged in as an artisan, they are viewing their OWN profile by default.
elseif (isset($_SESSION['user_id']) && $_SESSION['userType'] === 'artisan') {
    $artisan_to_view_id = $_SESSION['user_id'];
    $is_viewing_own_profile = true;
}
// If neither of the above conditions is met, we don't have a valid artisan ID to fetch.
else {
    $error_message = "No artisan specified or unauthorized access to view profiles. Please log in or select an artisan from the dashboard.";
}

// Only proceed with database query if a valid $artisan_to_view_id was found
if ($artisan_to_view_id) {
    try {
        $sql = "SELECT u.name AS user_full_name, u.email, u.phone, a.profile_image_url, a.first_name, a.last_name, a.age_range,
                    a.description, a.expertise, a.years_worked, a.county, a.sub_county, a.availability, a.certifications
                FROM
                    users AS u
                JOIN
                    artisans AS a ON u.id = a.artisan_id
                WHERE
                    u.id = ? AND u.userType = 'artisan'
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("i", $artisan_to_view_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $artisan_profile = $result->fetch_assoc();

            // Process JSON fields (expertise)
            if (!empty($artisan_profile['expertise'])) {
                $decoded_expertise = json_decode($artisan_profile['expertise'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $artisan_profile['expertise'] = $decoded_expertise;
                } else {
                    $artisan_profile['expertise'] = [];
                    error_log("JSON Decode Error for expertise (user_id: $artisan_to_view_id): " . json_last_error_msg());
                }
            } else {
                $artisan_profile['expertise'] = [];
            }

            // Process JSON fields (certifications)
            if (!empty($artisan_profile['certifications'])) {
                $decoded_certifications = json_decode($artisan_profile['certifications'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $artisan_profile['certifications'] = $decoded_certifications;
                } else {
                    $artisan_profile['certifications'] = [];
                    error_log("JSON Decode Error for certifications (user_id: $artisan_to_view_id): " . json_last_error_msg());
                }
            } else {
                $artisan_profile['certifications'] = [];
            }

        } else {
           
            if ($is_viewing_own_profile) {
                echo "
                    <script>
                        alert('You are not logged in. Redirecting to LogIn Page...');
                        setTimeout(function() {
                            window.location.href = ' ArtisanProfile.php';
                        }, 1500);
                    </script>
                ";
                exit();
            } else {
                $error_message = "Artisan profile not found. The requested artisan may not exist or has not completed their profile.";
            }
        }

        $stmt->close();

    } catch (Exception $e) {
        // This catches general database/preparation errors, not just 0 rows.
        $error_message = "An error occurred while fetching profile: " . $e->getMessage();
        error_log($error_message);
       
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="profile-container">
        <?php if ($artisan_profile): ?>
            <h1><?php echo htmlspecialchars($artisan_profile['first_name'] . ' ' . $artisan_profile['last_name']); ?>'s Profile</h1>

            <div class="profile-header">
                <?php
                $profileImg = !empty($artisan_profile['profile_image_url']) ? htmlspecialchars($artisan_profile['profile_image_url']) : 'images/default_profile.jpg';
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
                <?php
                // --- Conditional display for Edit Profile button ---
                // Only show "Edit Profile" if a user is logged in AND
                // if they are an artisan AND
                // if the profile being viewed is THEIR OWN profile.
                if (isset($_SESSION['user_id']) && $_SESSION['userType'] === 'artisan' && $_SESSION['user_id'] == $artisan_to_view_id) {
                    echo '<a href="ArtisanProfile.php" class="edit-profile-btn">Edit Profile</a>';
                }
                if (isset($_SESSION['user_id']) && $_SESSION['userType'] === 'client' && $artisan_profile && !empty($artisan_profile['phone'])) {
                    $whatsapp_phone = preg_replace('/[^0-9]/', '', $artisan_profile['phone']);
                    $whatsapp_url = "https://wa.me/" . htmlspecialchars($whatsapp_phone);

                    echo '<a href="' . $whatsapp_url . '" class="contact-artisan-btn whatsapp-btn" target="_blank" rel="noopener noreferrer">';
                    echo '<i class="fab fa-whatsapp"></i> Contact Artisan';
                    echo '</a>';
                }
                
                ?>

            </div>

        <?php else: ?>
            <!-- Display error message if no profile was found or other issue -->
            <p style="color: red; font-weight: bold; text-align: center; margin-top: 50px;"><?php echo htmlspecialchars($error_message); ?></p>
            <?php
            // Provide a "Create Profile" link ONLY if it's the artisan viewing their own missing profile
            if (isset($_SESSION['user_id']) && $_SESSION['userType'] === 'artisan' && $is_viewing_own_profile) {
                 echo '<p style="text-align: center;">If you are an artisan, you can create your profile now:</p>';
                 echo '<div style="text-align: center;"><a href="ArtisanProfile.php" class="edit-profile-btn">Create Profile Now</a></div>';
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>