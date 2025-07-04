<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("connection.php"); 

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'client') {
    header("Location: login.php?error=not_logged_in_client");
    exit();
}

$client_id = $_SESSION['user_id'];

$errors = [];
$old_input = [];
$success_message = '';
if (isset($_SESSION['form_errors'])) {
    $errors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']); // Clear errors after retrieving
}
if (isset($_SESSION['form_data'])) {
    $old_input = $_SESSION['form_data'];
    unset($_SESSION['form_data']); // Clear old data after retrieving
}
if (isset($_SESSION['job_post_success'])) {
    if ($_SESSION['job_post_success'] === true) {
        $success_message = "Your job has been posted successfully!";
    }
    unset($_SESSION['job_post_success']); // Clear flag after displaying
}

// --- Process Form Submission if POST request ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];
    $old_input = $_POST; 

    // --- 1. Collect and Sanitize Form Data ---
    $job_title = htmlspecialchars(trim($_POST['JobTitle'] ?? ''));
    $job_description = htmlspecialchars(trim($_POST['About'] ?? ''));
    $urgency = htmlspecialchars($_POST['Urgency'] ?? '');
    $required_expertise = htmlspecialchars(trim($_POST['expertise'] ?? ''));
    $county = htmlspecialchars($_POST['county'] ?? '');
    $sub_county = htmlspecialchars($_POST['sub-county'] ?? '');
    if (isset($_POST['budget']) && is_numeric($_POST['budget']) && (float)$_POST['budget'] >= 0) {
        $budget = (float)$_POST['budget'];
    } else {
        $budget = 0.00; 
    }

    $is_negotiable = (isset($_POST['negotiable']) && $_POST['negotiable'] === 'yes') ? 1 : 0;
    $image_path = null; 

    // --- 2. Validate Inputs ---
    if (empty($job_title)) { $errors['JobTitle'] = "Job Title is required."; }
    if (empty($job_description)) { $errors['About'] = "Job Description is required."; }
    $allowed_urgencies = ["VERY URGENT!", "Within 24 hours", "Within 1 week", "Flexible"];
    if (empty($urgency)) {
        $errors['Urgency'] = "Work Urgency is required.";
    } elseif (!in_array($urgency, $allowed_urgencies)) {
        $errors['Urgency'] = "Invalid urgency selected.";
    }

    if (empty($required_expertise)) { $errors['expertise'] = "Expected Artisan Expertise is required."; }
    if (empty($county)) { $errors['county'] = "County is required."; }
    if (empty($sub_county)) { $errors['sub-county'] = "Sub County is required."; }
    
    // Budget validation based on type and value
    if ($budget <= 0) { // If budget is not set, not numeric, or negative
        $errors['budget'] = "Budget is required and must be a positive number.";
    }
    if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['uploaded_file']['tmp_name'];
        $file_name = $_FILES['uploaded_file']['name'];
        $file_size = $_FILES['uploaded_file']['size'];
        $file_type = $_FILES['uploaded_file']['type'];

        $upload_dir = 'uploads/job_images/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) { // Check if mkdir fails
                $errors['uploaded_file'] = "Failed to create upload directory. Check permissions.";
                error_log("Failed to create directory: " . $upload_dir);
            }
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file_type, $allowed_types)) {
            $errors['uploaded_file'] = "Only JPG, PNG, GIF, and WEBP images are allowed.";
        }
        $max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
        if ($file_size > $max_file_size) {
            $errors['uploaded_file'] = "Image file is too large (max 5MB allowed).";
        }
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = uniqid('job_image_', true) . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;
        if (empty($errors) && is_dir($upload_dir)) {
            if (move_uploaded_file($file_tmp_name, $destination)) {
                $image_path = $destination; // Store the relative path
            } else {
                $errors['uploaded_file'] = "Failed to move uploaded image. Check permissions.";
                error_log("Failed to move uploaded file from {$file_tmp_name} to {$destination}");
            }
        }
    } else if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle specific PHP upload errors
        switch ($_FILES['uploaded_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors['uploaded_file'] = "Uploaded file exceeds maximum size allowed by server configuration.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors['uploaded_file'] = "File upload was interrupted. Please try again.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors['uploaded_file'] = "Server configuration error: Missing temporary folder for uploads.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors['uploaded_file'] = "Server error: Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors['uploaded_file'] = "A PHP extension stopped the file upload. Check server logs.";
                break;
            default:
                $errors['uploaded_file'] = "An unknown error occurred during file upload. Error code: " . $_FILES['uploaded_file']['error'];
                break;
        }
    } else {
        if (isset($_POST['JobTitle'])) {
             $errors['uploaded_file'] = "An image file is required for the job post.";
        }
    }
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO jobs_posted (client_id, job_title, job_description, urgency, required_expertise, county, sub_county, budget, is_negotiable, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("SQL Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param(
                "issssssdis", $client_id, $job_title, $job_description, $urgency, $required_expertise, $county,
                $sub_county, $budget, $is_negotiable, $image_path 
            );

            if ($stmt->execute()) {
                $_SESSION['job_post_success'] = true;
                $_SESSION['job_post_success'] = "Job Posted Successfully! Now we await applications.";
                header("Location: ClientDashboard.php");
                exit(); 
            } else {
                throw new Exception("Database execution failed: " . $stmt->error);
            }

            $stmt->close();

        } catch (Exception $e) {
            $errors['database_error'] = "Database error: " . $e->getMessage();
            error_log("Job Post DB Error: " . $e->getMessage()); 
        } finally {
            if (isset($conn) && $conn instanceof mysqli) {
                $conn->close();
            }
        }
    }
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $old_input;
        // No header redirect here, as the page will just render itself with errors
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post A Job</title>
    <link rel="stylesheet" href="style.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <a href="ClientDashboard.php" class="navbar-brand">JuaKazi</a>
        </div>
        <div class="navbar-right">
            <a href="logout.php" class="navbar-btn logout-btn">Logout</a>
        </div>
    </nav>
    <p class="tagline">Fill the Form Below to Post a New Job</p>

    <form class="post_job_form" action="PostJob.php" method="POST" enctype="multipart/form-data">
        <?php if (!empty($errors)): ?>
            <div class="error-messages" style="color: red; margin-bottom: 15px; border: 1px solid red; padding: 10px; border-radius: 8px; background-color: #ffeaea;">
                <p>Please correct the following errors:</p>
                <ul>
                    <?php foreach ($errors as $field_name => $message): ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="color: green; margin-bottom: 15px; border: 1px solid green; padding: 10px; border-radius: 8px; background-color: #eafaea;">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?> 

        <label for="JobTitle">Enter Your Job Title: </label>
        <input type="text" name="JobTitle" id="JobTitle" placeholder="Plumbing work" value="<?php echo htmlspecialchars($old_input['JobTitle'] ?? ''); ?>" required/>

        <label for="Urgency">Work Urgency: </label>
        <select name="Urgency" id="Urgency" required>
            <option value="">Select Urgency</option>
            <?php
            $urgency_options = ["VERY URGENT!", "Within 24 hours", "Within 1 week", "Flexible"];
            foreach ($urgency_options as $option):
                $selected = (isset($old_input['Urgency']) && $old_input['Urgency'] === $option) ? 'selected' : '';
                echo "<option value=\"{$option}\" {$selected}>{$option}</option>";
            endforeach;
            ?>
        </select>

        <label for="About">Brief description of the work needs: </label>
        <textarea name="About" id="About" placeholder="Broken Pipe. Car broken down" rows="5" required><?php echo htmlspecialchars($old_input['About'] ?? ''); ?></textarea>

        <label for="expertise">Expected Artisan Expertise: </label>
        <input type="text" name="expertise" id="expertise" placeholder="plumbing" value="<?php echo htmlspecialchars($old_input['expertise'] ?? ''); ?>" required/>

        <label for="fileInput"><br>Upload Appropriate Images of the Problem: </label>
        <div class="file-drop-area" id="drop-area" onclick="document.getElementById('fileInput').click()">
            <input type="file" id="fileInput" name="uploaded_file" hidden required />
            <div class="file-drop-message">
                <div class="upload-icon">&#8681;</div>
                <div class="file-preview" id="filePreview"></div>
                <p>You can drag and drop an image here.</p>
            </div>
        </div>

        <label for="countySelect"><br>Location: </label>
        <div class="input-group">
            <select name="county" id="countySelect" required>
                <option value="">Select County</option>
                <!-- Populated by JavaScript -->
            </select>

            <select name="sub-county" id="subCountySelect" required>
                <option value="">Select Sub County</option>
                <!-- Populated by JavaScript -->
            </select>
        </div>

        <label for="budget">Budget: </label>
        <input type="number" name="budget" id="budget" placeholder="Ksh. 1000" value="<?php echo htmlspecialchars($old_input['budget'] ?? ''); ?>" required/>

        <div class="checkbox-group">
            <input type="checkbox" name="negotiable" id="negotiable_checkbox" value="yes" <?php echo (isset($old_input['negotiable']) && $old_input['negotiable'] === 'yes') ? 'checked' : ''; ?>>
            <label for="negotiable_checkbox">Wage is negotiable</label>
        </div>

        <button type="submit" class="upload-btn">Post Job</button>
    </form>

    <script>
        // --- Global DOM References ---
        const countySelect = document.getElementById('countySelect');
        const subCountySelect = document.getElementById('subCountySelect');
        const fileInput = document.getElementById("fileInput");
        const filePreviewContainer = document.getElementById("filePreview");
        const dropArea = document.getElementById('drop-area');

        let allCountiesData = []; 
        let selectedFile = null;
        const oldSelectedCounty = "<?php echo htmlspecialchars($old_input['county'] ?? ''); ?>";
        const oldSelectedSubCounty = "<?php echo htmlspecialchars($old_input['sub-county'] ?? ''); ?>";

        // --- Functions for Image Upload (Single Image) ---
        function renderFilePreviews() {
            filePreviewContainer.innerHTML = ''; 

            const dropAreaMessage = dropArea.querySelector('p'); 

            if (!selectedFile) {
                if (dropAreaMessage) {
                    dropAreaMessage.textContent = 'You can drag and drop an image here.';
                }
                return;
            }

            if (dropAreaMessage) {
                dropAreaMessage.textContent = `Selected: ${selectedFile.name}`;
            }

            const previewBox = document.createElement('div');
            previewBox.classList.add('preview-box');

            const removeBtn = document.createElement('span');
            removeBtn.classList.add('remove-file-btn');
            removeBtn.innerHTML = '&times;';
            removeBtn.title = 'Remove image';
            removeBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                removeFile();
            });
            previewBox.appendChild(removeBtn);

            if (selectedFile.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.addEventListener("load", function () {
                    const img = document.createElement('img');
                    img.src = reader.result;
                    img.alt = selectedFile.name;
                    previewBox.appendChild(img);
                    const fileNameText = document.createElement('span');
                    fileNameText.classList.add('file-name-text');
                    fileNameText.textContent = selectedFile.name;
                    previewBox.appendChild(fileNameText);
                });
                reader.readAsDataURL(selectedFile);
            } else {
                alert("Only image files are allowed for upload.");
                removeFile();
                return;
            }
            filePreviewContainer.appendChild(previewBox);
        }

        function removeFile() {
            selectedFile = null;
            fileInput.value = ''; 
            updateFileInputFiles();
            renderFilePreviews();
        }

        function updateFileInputFiles() {
            const dataTransfer = new DataTransfer();
            if (selectedFile) {
                dataTransfer.items.add(selectedFile);
            }
            fileInput.files = dataTransfer.files;
        }

        fileInput.addEventListener("change", function () {
            if (this.files.length > 0) {
                const newFile = this.files[0];
                if (!newFile.type.startsWith('image/')) {
                     alert("Only image files are allowed. Please select an image.");
                     this.value = '';
                     selectedFile = null;
                } else {
                    selectedFile = newFile;
                }
            } else {
                selectedFile = null;
            }
            updateFileInputFiles();
            renderFilePreviews();
        });

        // Handle drag and drop
        if (dropArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
            });

            dropArea.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const droppedFiles = dt.files;

                if (droppedFiles.length > 0) {
                    const newFile = droppedFiles[0];
                    if (!newFile.type.startsWith('image/')) {
                        alert("Only image files are allowed. Please drop an image.");
                        selectedFile = null;
                    } else {
                        selectedFile = newFile;
                    }
                } else {
                    selectedFile = null;
                }
                updateFileInputFiles();
                renderFilePreviews();
            }, false);
        }

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }


        // --- Functions for County/Sub-County Dropdown Population ---
        async function loadCounties() {
            try {
                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
                const fullUrl = basePath + 'counties.json';

                const response = await fetch(fullUrl);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                allCountiesData = await response.json();

                countySelect.innerHTML = '<option value="">Select County</option>';
                allCountiesData.forEach(county => {
                    const option = document.createElement('option');
                    option.value = county.name;
                    option.textContent = county.name;
                    countySelect.appendChild(option);
                });
                
                // Repopulate county selection if old data exists
                if (oldSelectedCounty) {
                    countySelect.value = oldSelectedCounty;
                    populateSubCounties(true);
                }

            } catch (error) {
                console.error('Error loading counties data:', error);
                countySelect.innerHTML = '<option value="">Error loading counties</option>';
                subCountySelect.innerHTML = '<option value="">Error loading sub-counties</option>';
            }
        }

        function populateSubCounties(isRepopulating = false) {
            const selectedCountyName = countySelect.value;
            subCountySelect.innerHTML = '<option value="">Select Sub County</option>'; // Reset sub-county dropdown

            if (selectedCountyName) {
                const selectedCounty = allCountiesData.find(county => county.name.trim() === selectedCountyName.trim());
                if (selectedCounty && Array.isArray(selectedCounty.sub_counties)) {
                    selectedCounty.sub_counties.sort().forEach(subCounty => {
                        const option = document.createElement('option');
                        option.value = subCounty;
                        option.textContent = subCounty;
                        subCountySelect.appendChild(option);
                    });
                    
                    // Repopulate sub-county selection if old data exists and we are repopulating
                    if (isRepopulating && oldSelectedSubCounty) {
                        subCountySelect.value = oldSelectedSubCounty;
                    }
                } else {
                    console.warn(`populateSubCounties: No sub-counties found or invalid data for selected county "${selectedCountyName}". Found county object:`, selectedCounty);
                }
            }
        }

        // --- Event Listeners for Dropdowns ---
        countySelect.addEventListener('change', () => populateSubCounties(false));


        // --- Initial Load of Data and Dropdowns ---
        document.addEventListener('DOMContentLoaded', async () => {
            await loadCounties(); // Load counties data
            renderFilePreviews(); // Initial render for file preview
        });

    </script>
</body>
</html>