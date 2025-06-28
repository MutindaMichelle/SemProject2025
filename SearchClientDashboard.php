<?php
session_start();

ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); 
header('Content-Type: application/json');
include("connection.php"); 

$artisan_list = []; // Initialize an empty array to store fetched artisan data

try {
    // --- 2. Get Search Parameters from GET Request ---
    $search_type = $_GET['type'] ?? '';        
    $search_county = $_GET['county'] ?? '';    
    $search_sub_county = $_GET['sub_county'] ?? ''; 

    // --- 3. Build the SQL Query Dynamically ---
    $sql = "SELECT u.id AS user_id, a.profile_image_url, a.first_name, a.last_name, a.expertise,
                a.county, a.sub_county, a.years_worked
            FROM
                users AS u
            JOIN
                artisans AS a ON u.id = a.artisan_id
            WHERE
                u.userType = 'artisan'"; 

    $params = []; // Array to hold parameters for prepared statement
    $types = "";  // String to hold types for prepared statement

    // Add conditions based on provided search parameters
    if (!empty($search_type)) {
        $sql .= " AND a.expertise LIKE ?";
        $params[] = "%" . $search_type . "%";
        $types .= "s";
    }
    if (!empty($search_county)) {
        $sql .= " AND a.county = ?";
        $params[] = $search_county;
        $types .= "s";
    }
    if (!empty($search_sub_county)) {
        $sql .= " AND a.sub_county = ?";
        $params[] = $search_sub_county;
        $types .= "s";
    }

    $sql .= " ORDER BY a.first_name ASC"; 

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("SQL Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    // If there are parameters, bind them
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // This part makes json data be in php array format
            if (!empty($row['expertise'])) {
                $decoded_expertise = json_decode($row['expertise'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['expertise'] = $decoded_expertise;
                } else {
                    error_log("JSON Decode Error for expertise (user_id: {$row['user_id']}): " . json_last_error_msg());
                    $row['expertise'] = [];
                }
            } else {
                $row['expertise'] = [];
            }
            $artisan_list[] = $row; // Add processed row to the list
        }
    }

    $stmt->close();

} catch (Exception $e) {
  
    http_response_code(500);
    echo json_encode(['error' => 'Server error fetching artisans: ' . $e->getMessage()]);
    error_log("fetch_artisans.php error: " . $e->getMessage()); 
    exit(); 
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close(); 
    }
}

// --- 4. Encode and Output JSON Response ---
// If no error, output the fetched artisan list as JSON.
echo json_encode($artisan_list);
?>