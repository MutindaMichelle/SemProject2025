<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("connection.php");
$job_post_success_message = '';
if (isset($_SESSION['job_post_success']) && $_SESSION['job_post_success'] === true) {
    $job_post_success_message = "Your job has been posted successfully!";
    unset($_SESSION['job_post_success']); 
}

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'client') {
    header("Location: login.php");
    exit();
}

//Hello again! This is the Client Dashboard page. 
//Here I am adding functionality to fetch and display the client's posted jobs.
// ADDED: Fetch client's posted jobs
$client_id = $_SESSION['user_id'];
$jobs = [];
$stmt = $conn->prepare("SELECT id AS job_id, job_title FROM jobs_posted WHERE client_id = ?"); //We are going to cry here. I have updated the query to use 'id' as job_id for consistency.
// $stmt = $conn->prepare("SELECT job_id, job_title FROM jobs_posted WHERE client_id = ?");
// Note: Using 'id' as job_id for consistency with the artisan dashboard.
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $jobs[] = $row;
}
$stmt->close();
// END ADDED

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Find Artisans</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-left">
            <a href="#" class="navbar-brand">JuaKazi</a>
        </div>
        <div class="navbar-right">
            <a href="PostJob.php" class="navbar-btn post-job-btn">Post a Job</a>
            <a href="logout.php" class="navbar-btn logout-btn">Logout</a>
            
        </div>
    </nav>

    <main class="dashboard-content">
        <!-- Welcome Section and Search Bars -->
        <section class="top-section">
            <h1 class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Client'); ?>!</h1>
            <p class="tagline">Find skilled artisans near you.</p>
            <?php if (!empty($job_post_success_message)): ?>
                <div class="success-message" style="color: green; text-align: center; margin-top: 20px; padding: 15px; background-color: #eafaea; border: 1px solid #28a745; border-radius: 8px; max-width: 800px; margin-left: auto; margin-right: auto;">
                <p><?php echo htmlspecialchars($job_post_success_message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Search Bars for Artisan Type, County, and Sub County -->
            <div class="search-bars-container">
                <div class="search-input-wrapper">
                    <input type="text" name="artisan_type_search" id="artisanTypeSearch" placeholder="Type of artisan needed (e.g., Plumber)" class="search-input">
                    <button type="button" class="clear-search-btn" id="clearArtisanTypeSearchBtn" title="Clear search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- County Search Dropdown with Clear Button -->
                <div class="search-input-wrapper">
                    <select name="county_search" id="countySearch" class="search-select">
                        <option value="">Select County</option>
                    </select>
                    <button type="button" class="clear-search-btn" id="clearCountySearchBtn" title="Clear county">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Sub County Search Dropdown with Clear Button -->
                <div class="search-input-wrapper">
                    <select name="sub_county_search" id="subCountySearch" class="search-select">
                        <option value="">Select Sub County</option>
                    </select>
                    <button type="button" class="clear-search-btn" id="clearSubCountySearchBtn" title="Clear sub-county">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </section>

               <!-- ADDED: View Applications Section -->
                 <!-- Displaying the client's posted jobs -->
                    <section class="posted-jobs classy-jobs">
                <h2>Your Posted Jobs</h2>
                <?php if (empty($jobs)): ?>
                    <p>You have not posted any jobs yet.</p>
                <?php else: ?>
                    <ul class="job-list">
                        <?php foreach ($jobs as $job): ?>
                            <li class="job-item">
                                <span class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></span>
                                <a href="view_applications.php?job_id=<?php echo $job['job_id']; ?>" class="btn-primary">View Applications</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- END ADDED -->

              <!-- Artisan Cards Container (Initially empty, JavaScript will populate) -->
            <div class="artisan-cards-container">
                <p class="loading-message" style="text-align: center; padding: 20px;">Loading artisans...</p>
            </div>


    </main>

    <footer class="main-footer">
        <p>&copy; <?php echo date("Y"); ?> JuaKazi. All rights reserved.</p>
    </footer>

    <!--Styles for viewing applications button-->
     <style>
    /* ADDED: Simple button for View Applications */
  /* Posted Jobs Section */
.classy-jobs {
    margin-top: 40px;
    padding: 30px 20px;
    background-color: #f9fafc;
    border-top: 1px solid #ddd;
}

.classy-jobs h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #2c3e50;
}

/* Remove list dots and style */
.job-list {
    list-style: none;
    padding-left: 0;
    max-width: 800px;
    margin: 0 auto;
}

.job-item {
    background-color: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    transition: box-shadow 0.3s ease;
}

.job-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
}

.job-title {
    font-weight: bold;
    font-size: 16px;
    color: #333;
}

    /*  END ADDED */
    </style>

    <script>
        // --- Global References ---
        const artisanTypeSearchInput = document.getElementById('artisanTypeSearch');
        const countySearchSelect = document.getElementById('countySearch');
        const subCountySearchSelect = document.getElementById('subCountySearch');
        const artisanCardsContainer = document.querySelector('.artisan-cards-container');
        const clearArtisanTypeSearchBtn = document.getElementById('clearArtisanTypeSearchBtn');
        const clearCountySearchBtn = document.getElementById('clearCountySearchBtn');
        const clearSubCountySearchBtn = document.getElementById('clearSubCountySearchBtn');

        let allCountiesData = []; 
        function debounce(func, delay) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }
        async function fetchAndRenderArtisans() {
            const type = artisanTypeSearchInput.value.trim(); 
            const county = countySearchSelect.value;
            const sub_county = subCountySearchSelect.value;

            // Build URL search parameters from current filter values
            const params = new URLSearchParams();
            if (type) params.append('type', type);
            if (county) params.append('county', county);
            if (sub_county) params.append('sub_county', sub_county);

            // Dynamically determine the base path to correctly locate PHP backend script
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            const url = basePath + 'SearchClientDashboard.php?' + params.toString(); 

            // Display loading indicator while fetching
            artisanCardsContainer.innerHTML = '<p class="loading-message" style="text-align: center; padding: 20px;">Loading artisans...</p>';

            try {
                const response = await fetch(url);
                if (!response.ok) {
                    let errorDetails = `HTTP error! Status: ${response.status} - ${response.statusText}`;
                    try {
                        const errorJson = await response.json();
                        if (errorJson && errorJson.error) {
                            errorDetails += ` | Server Message: ${errorJson.error}`;
                        }
                    } catch (jsonError) {
                        
                    }
                    throw new Error(errorDetails); 
                }
                const artisans = await response.json(); 
                renderArtisanCards(artisans); 

            } catch (error) {
                console.error('Error fetching artisans:', error);
                artisanCardsContainer.innerHTML = `<p class="error-message" style="text-align: center; padding: 20px; color: red;">Failed to load artisans. Please try again. Details: ${error.message}</p>`;
            }
            updateClearButtonVisibility(); 
        }
        function renderArtisanCards(artisans) {
            artisanCardsContainer.innerHTML = ''; 
            if (!Array.isArray(artisans) || artisans.length === 0) {
                artisanCardsContainer.innerHTML = '<p class="no-results-message" style="text-align: center; padding: 20px;">No artisans found matching your criteria.</p>';
                return;
            }

            // Loop through each artisan and create their card HTML
            artisans.forEach(artisan => {
                const card = document.createElement('div');
                card.classList.add('artisan-card');

                const profileImgSrc = artisan.profile_image_url ? artisan.profile_image_url : 'images/default_profile.jpg';
                const img = document.createElement('img');
                img.src = profileImgSrc;
                img.alt = `${artisan.first_name || ''} ${artisan.last_name || ''}'s Profile`;
                img.classList.add('card-profile-pic');
                card.appendChild(img);

                const name = document.createElement('h3');
                name.classList.add('card-name');
                name.textContent = `${artisan.first_name || ''} ${artisan.last_name || ''}`;
                card.appendChild(name);

                const expertiseP = document.createElement('p');
                expertiseP.classList.add('card-expertise');
                let expertiseText = 'Expertise: N/A';
                // Ensure expertise is an array before attempting to slice/join
                if (Array.isArray(artisan.expertise) && artisan.expertise.length > 0) {
                    const display_expertise = artisan.expertise.slice(0, 3);
                    expertiseText = `Expertise: ${display_expertise.join(', ')}`;
                    if (artisan.expertise.length > 3) {
                        expertiseText += '...';
                    }
                }
                expertiseP.textContent = expertiseText;
                card.appendChild(expertiseP);

                const locationP = document.createElement('p');
                locationP.classList.add('card-location');
                locationP.textContent = `Location: ${artisan.sub_county || 'N/A'}, ${artisan.county || 'N/A'}`;
                card.appendChild(locationP);

                const yearsP = document.createElement('p');
                yearsP.classList.add('card-years');
                yearsP.textContent = `Experience: ${artisan.years_worked || 'N/A'} Years`;
                card.appendChild(yearsP);

                const viewProfileBtn = document.createElement('a');
                viewProfileBtn.href = `viewArtisanProfile.php?artisan_id=${artisan.user_id}`;
                viewProfileBtn.classList.add('view-profile-btn');
                viewProfileBtn.textContent = 'View Profile';
                card.appendChild(viewProfileBtn);

                artisanCardsContainer.appendChild(card);
            });
        }

        
        function updateClearButtonVisibility() {
            // Artisan Type Search
            if (clearArtisanTypeSearchBtn) {
                clearArtisanTypeSearchBtn.style.display = artisanTypeSearchInput.value.trim() !== '' ? 'block' : 'none';
            }

            // County Search
            if (clearCountySearchBtn) {
                clearCountySearchBtn.style.display = countySearchSelect.value !== '' ? 'block' : 'none';
            }

            // Sub County Search
            if (clearSubCountySearchBtn) {
                clearSubCountySearchBtn.style.display = subCountySearchSelect.value !== '' ? 'block' : 'none';
            }
        }

        // --- Event Listeners for Search Inputs ---
        const debouncedFetchAndRender = debounce(fetchAndRenderArtisans, 300);
        if (artisanTypeSearchInput) {
            artisanTypeSearchInput.addEventListener('input', debouncedFetchAndRender);
        }
        if (countySearchSelect) {
            countySearchSelect.addEventListener('change', populateSubCounties);
        }
        if (subCountySearchSelect) {
            subCountySearchSelect.addEventListener('change', fetchAndRenderArtisans);
        }
        if (clearArtisanTypeSearchBtn) {
            clearArtisanTypeSearchBtn.addEventListener('click', () => {
                artisanTypeSearchInput.value = '';
                debouncedFetchAndRender();
            });
        }
        if (clearCountySearchBtn) {
            clearCountySearchBtn.addEventListener('click', () => {
                countySearchSelect.value = '';
                subCountySearchSelect.innerHTML = '<option value="">Select Sub County</option>'; // Clear sub-county options when county is cleared
                fetchAndRenderArtisans();
            });
        }
        if (clearSubCountySearchBtn) {
            clearSubCountySearchBtn.addEventListener('click', () => {
                subCountySearchSelect.value = '';
                fetchAndRenderArtisans();
            });
        }
        async function loadCounties() {
            try {
                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
                const fullUrl = basePath + 'counties.json';

                const response = await fetch(fullUrl);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to load counties.json: HTTP status ${response.status}. Response: ${errorText.substring(0, 100)}...`);
                }
                allCountiesData = await response.json();
                if (!Array.isArray(allCountiesData) || !allCountiesData.every(c => c.name && Array.isArray(c.sub_counties))) {
                    throw new Error('Invalid structure in counties.json. Expected an array of objects with "name" and "sub_counties" arrays.');
                }
                countySearchSelect.innerHTML = '<option value="">Select County</option>';
                allCountiesData.forEach(county => {
                    const option = document.createElement('option');
                    option.value = county.name;
                    option.textContent = county.name;
                    countySearchSelect.appendChild(option);
                });
            } catch (error) {
                console.error('CRITICAL ERROR: Failed to load or parse counties data:', error);
                countySearchSelect.innerHTML = '<option value="">Error loading counties</option>';
                subCountySearchSelect.innerHTML = '<option value="">Error loading sub-counties</option>';
                alert(`CRITICAL ERROR: Unable to load county data. Please check 'counties.json' file and its format. Details: ${error.message}`);
            }
        }

        // Populates the Sub County dropdown based on selected County
        function populateSubCounties() {
            const selectedCountyName = countySearchSelect.value;
            subCountySearchSelect.innerHTML = '<option value="">Select Sub County</option>'; // Always clear first

            if (selectedCountyName && allCountiesData.length > 0) {
                
                const selectedCounty = allCountiesData.find(county => county.name.trim() === selectedCountyName.trim());
                
                if (selectedCounty && Array.isArray(selectedCounty.sub_counties)) {
                    selectedCounty.sub_counties.sort().forEach(subCounty => {
                        const option = document.createElement('option');
                        option.value = subCounty;
                        option.textContent = subCounty;
                        subCountySearchSelect.appendChild(option);
                    });
                } else {
                    console.warn(`populateSubCounties: No sub-counties found or invalid data for selected county "${selectedCountyName}". Found county object:`, selectedCounty);
                }
            }
            fetchAndRenderArtisans();
        }

        // --- Initial Load of Data and Dropdowns when the page is ready ---
        document.addEventListener('DOMContentLoaded', async () => {
            await loadCounties(); 
            fetchAndRenderArtisans(); 
            updateClearButtonVisibility(); 
        });
    </script>
</body>
</html>