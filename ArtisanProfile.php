<?php
session_start(); //starts the session
ini_set('display_errors', 1); //tells php to display all errors on the screen
ini_set('display_startup_errors', 1); //display startup errors
error_reporting(E_ALL); //report every error
include("connection.php"); // Include your database connection file


// Ensure the user is logged in and is an artisan if not, redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'artisan') {
    echo "
    <script>
        alert('You are not logged in. Redirecting to LogIn Page...');
        setTimeout(function() {
            window.location.href = 'Registration.html';
        }, 1500);
    </script>
";
    exit();
}

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Collect form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $age_range = trim($_POST['ArtisanAge'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $years_worked = (int)($_POST['years'] ?? 0);
    $county = trim($_POST['county'] ?? '');
    $sub_county = trim($_POST['sub-county'] ?? '');
    $availability = trim($_POST['availability'] ?? '');

    // expertise[] will be an array; json_encode it for storage
    $expertise = $_POST['expertise'] ?? [];
    // Filter out any empty expertise fields from dynamic inputs
    $expertise = array_filter($expertise, function ($value) {
        return !empty(trim($value));
    });
    $expertise_json = json_encode(array_values($expertise)); // array_values to re-index array numerically if needed
    $expertise = array_filter($expertise, function ($value) {
        return !empty(trim($value));
    });
    $expertise_json = json_encode(array_values($expertise));
    // Initialize variables for file URLs
    $profile_image_url = null;
    $certification_files_urls = [];
    $upload_dir = 'uploads/artisan_profiles/'; // Make sure this directory exists and is writable!
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }
    // 2. Handle Profile Image Upload
    if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['imageUpload']['tmp_name']; //gets the inage and stores it in a temporary file
        $file_name = uniqid() . '_' . basename($_FILES['imageUpload']['name']); // Generate unique name and concatenates it with the image's actual name
        $target_file = $upload_dir . $file_name; //builds the actual target path

        if (move_uploaded_file($file_tmp, $target_file)) {
            $profile_image_url = $target_file; // Store the file in the uploads directory
        } else {
            echo "Failed to upload profile image.<br>";
            // You might want to exit or handle this more gracefully
        }
    }

    // 3. Handle Certification Files Uploads. Same logic as the images one.
    if (isset($_FILES['uploaded_file']) && !empty($_FILES['uploaded_file']['name'][0])) {
        foreach ($_FILES['uploaded_file']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['uploaded_file']['error'][$key] == UPLOAD_ERR_OK) {
                $file_name = uniqid() . '_' . basename($_FILES['uploaded_file']['name'][$key]);
                $target_file = $upload_dir . $file_name;

                if (move_uploaded_file($tmp_name, $target_file)) {
                    $certification_files_urls[] = $target_file;
                } else {
                    echo "Failed to upload certification file: " . htmlspecialchars($_FILES['uploaded_file']['name'][$key]) . "<br>";
                }
            }
        }
    }
    $certifications_json = json_encode($certification_files_urls);

    // Basic check for required text fields
    if (empty($first_name) || empty($last_name) || empty($age_range) || empty($description) || empty($county) || empty($sub_county) || empty($availability)) {
        echo "Error: Please fill in all required text fields.";
        exit;
    }

    // Check if years_worked is a positive integer
    if ($years_worked < 0) {
        echo "Error: Years worked cannot be negative.";
        exit;
    }

    // 5. Preparation of SQL Statement
    //Hello Chambira, I might have changed the artisan_id to user_id in the SQL query below, please check if it works as expected.
    // This is because artisan_id was not defined in the form, but user_id is the logged-in user's ID.
    // If artisan_id is needed, you can change it back to artisan_id and ensure it's defined in the form.
    //inserts new artisan
    $sql = "INSERT INTO artisans (
                user_id, profile_image_url, first_name, last_name, age_range,
                description, expertise, years_worked, county, sub_county,
                availability, certifications
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) 
            ON DUPLICATE KEY UPDATE
                profile_image_url = VALUES(profile_image_url),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                age_range = VALUES(age_range),
                description = VALUES(description),
                expertise = VALUES(expertise),
                years_worked = VALUES(years_worked),
                county = VALUES(county),
                sub_county = VALUES(sub_county),
                availability = VALUES(availability),
                certifications = VALUES(certifications),
                updated_at = CURRENT_TIMESTAMP";


    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit();
    }

    // Determine if profile_image_url and certifications_json are NULL or empty strings
    $bound_profile_image_url = $profile_image_url ?: NULL; // If empty string, make it NULL
    $bound_certifications_json = !empty($certification_files_urls) ? $certifications_json : NULL;

    // Bind parameters. They fill the ? in the SQL query
    $stmt->bind_param(
        "issssssissss",
        $user_id,
        $bound_profile_image_url,
        $first_name,
        $last_name,
        $age_range,
        $description,
        $expertise_json,
        $years_worked,
        $county,
        $sub_county,
        $availability,
        $bound_certifications_json
    );

    // 6. Execute the statement
    if ($stmt->execute()) {

        header("Location:ArtisanDashboard.php"); // Redirect to Artisan Dashboard after successful save
        echo "<script>alert('Profile saved successfully!');</script>";
        exit();
    } else {
        echo "Error saving profile: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    // If someone tries to access this page directly without POST submission
    echo "Access denied. This page should be accessed via form submission.";
}
