<?php
session_start();

// Include database and auth configuration
require_once 'config/database.php';
require_once 'config/auth.php';

// Initialize auth and check if user is logged in
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.html');
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get current user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user profile data
$profile = null;
$stats = [
    'profile_views' => 0,
    'applications_sent' => 0,
    'interviews_scheduled' => 0,
    'response_rate' => 0
];

try {
    // Get user profile
    $query = "SELECT first_name, last_name, email, phone, location, bio, role, 
                     skills, work_experience, education, created_at
              FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        throw new Exception("Profile not found");
    }
    
    // Calculate profile completion percentage
    $completion_fields = ['first_name', 'last_name', 'email', 'phone', 'location', 'bio', 'skills'];
    $completed_fields = 0;
    foreach ($completion_fields as $field) {
        if (!empty($profile[$field])) {
            $completed_fields++;
        }
    }
    $profile_completion = round(($completed_fields / count($completion_fields)) * 100);
    
    // Get user statistics
    $stats_query = "SELECT 
                        (SELECT COUNT(*) FROM applications WHERE candidate_id = :user_id) as applications_sent,
                        (SELECT COUNT(*) FROM interviews i 
                         JOIN applications a ON i.application_id = a.id 
                         WHERE a.candidate_id = :user_id AND i.status = 'scheduled') as interviews_scheduled";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stats_stmt->execute();
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_stats) {
        $stats['applications_sent'] = $user_stats['applications_sent'];
        $stats['interviews_scheduled'] = $user_stats['interviews_scheduled'];
        $stats['profile_views'] = rand(50, 200); // Mock data for demo
        $stats['response_rate'] = $stats['applications_sent'] > 0 ? 
            round(($stats['interviews_scheduled'] / $stats['applications_sent']) * 100) : 0;
    }
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = "Error loading profile data";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $update_query = "UPDATE users SET 
                         first_name = :first_name,
                         last_name = :last_name,
                         email = :email,
                         phone = :phone,
                         location = :location,
                         bio = :bio,
                         updated_at = NOW()
                         WHERE id = :user_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $_POST['first_name']);
        $update_stmt->bindParam(':last_name', $_POST['last_name']);
        $update_stmt->bindParam(':email', $_POST['email']);
        $update_stmt->bindParam(':phone', $_POST['phone']);
        $update_stmt->bindParam(':location', $_POST['location']);
        $update_stmt->bindParam(':bio', $_POST['bio']);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh profile data
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Failed to update profile";
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = "Error updating profile";
    }
}

// Parse work experience and education JSON
$work_experience = [];
$education = [];

if (!empty($profile['work_experience'])) {
    $work_experience = json_decode($profile['work_experience'], true) ?: [];
}

if (!empty($profile['education'])) {
    $education = json_decode($profile['education'], true) ?: [];
}

// Parse skills
$skills_array = [];
if (!empty($profile['skills'])) {
    $skills_array = array_map('trim', explode(',', $profile['skills']));
}

// Get user initials for avatar
$initials = '';
if ($profile) {
    $initials = strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - RecruitWarX Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #31485d;
            --secondary-color: #3289c3;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand, .nav-link {
            color: white !important;
            font-weight: 500;
        }

        .nav-link:hover {
            color: #ecf0f1 !important;
        }

        .dashboard-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
        }

        .btn-light {
            background: white;
            border: 2px solid var(--secondary-color);
            color: var(--secondary-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-light:hover {
            background: var(--secondary-color);
            color: white;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(50, 137, 195, 0.25);
        }

        .progress-bar {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }

        .skill-tag {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .experience-item, .education-item {
            border-left: 3px solid var(--secondary-color);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="candidate-dashboard.html"><i class="fas fa-briefcase me-2"></i>RecruitWarX</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.html"><i class="fas fa-search me-1"></i>Find Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.html"><i class="fas fa-paper-plane me-1"></i>My Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php"><i class="fas fa-user me-1"></i>Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="resume.html"><i class="fas fa-file-alt me-1"></i>Resume</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-bell me-1"></i>Notifications</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="handlers/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="dashboard-title">My Profile</h1>
                    <p class="dashboard-subtitle">Manage your professional information and preferences</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" id="editProfileBtn">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <span class="text-white" style="font-size: 2rem; font-weight: bold;"><?php echo $initials; ?></span>
                            </div>
                        </div>
                        <h4><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h4>
                        <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $profile['role'])); ?></p>
                        <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($profile['location'] ?: 'Not specified'); ?></p>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Profile Completion</span>
                                <span class="text-primary fw-bold"><?php echo $profile_completion; ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $profile_completion; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="resume.html" class="btn btn-primary">
                                <i class="fas fa-file-alt me-2"></i>View Resume
                            </a>
                            <button class="btn btn-primary" onclick="downloadResume()">
                                <i class="fas fa-download me-2"></i>Download Resume
                            </button>
                            <button class="btn btn-outline-primary">
                                <i class="fas fa-share me-2"></i>Share Profile
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Profile Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Profile Views</span>
                            <strong><?php echo $stats['profile_views']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Applications Sent</span>
                            <strong><?php echo $stats['applications_sent']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Interview Invites</span>
                            <strong><?php echo $stats['interviews_scheduled']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Response Rate</span>
                            <strong><?php echo $stats['response_rate']; ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="personalInfoForm" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($profile['first_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($profile['last_name']); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?: ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($profile['location'] ?: ''); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Professional Summary</label>
                                <textarea class="form-control" rows="4" name="bio" readonly><?php echo htmlspecialchars($profile['bio'] ?: ''); ?></textarea>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Work Experience -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Work Experience</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($work_experience)): ?>
                            <p class="text-muted">No work experience added yet. <a href="resume.html">Add your experience</a></p>
                        <?php else: ?>
                            <?php foreach ($work_experience as $index => $exp): ?>
                                <div class="experience-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($exp['jobTitle'] ?? ''); ?></h6>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($exp['company'] ?? ''); ?></p>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars(($exp['startDate'] ?? '') . ' - ' . ($exp['endDate'] ?? '')); ?></p>
                                    <p class="mb-0"><?php echo htmlspecialchars($exp['description'] ?? ''); ?></p>
                                </div>
                                <?php if ($index < count($work_experience) - 1): ?>
                                    <hr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Skills -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i>Skills</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($skills_array)): ?>
                            <p class="text-muted">No skills added yet. <a href="resume.html">Add your skills</a></p>
                        <?php else: ?>
                            <?php foreach ($skills_array as $skill): ?>
                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Education -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Education</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($education)): ?>
                            <p class="text-muted">No education added yet. <a href="resume.html">Add your education</a></p>
                        <?php else: ?>
                            <?php foreach ($education as $index => $edu): ?>
                                <div class="education-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($edu['degree'] ?? ''); ?></h6>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($edu['institution'] ?? ''); ?></p>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($edu['year'] ?? ''); ?></p>
                                    <?php if (!empty($edu['grade'])): ?>
                                        <p class="mb-0">Grade: <?php echo htmlspecialchars($edu['grade']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($index < count($education) - 1): ?>
                                    <hr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit profile functionality
        document.getElementById('editProfileBtn').addEventListener('click', function() {
            const inputs = document.querySelectorAll('#personalInfoForm input, #personalInfoForm textarea');
            const isEditing = this.innerHTML.includes('Edit');
            
            if (isEditing) {
                // Enable editing
                inputs.forEach(input => {
                    if (input.name !== 'action') {
                        input.removeAttribute('readonly');
                    }
                });
                this.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
                this.classList.remove('btn-light');
                this.classList.add('btn-success');
            } else {
                // Submit form to save changes
                document.getElementById('personalInfoForm').submit();
            }
        });

        function downloadResume() {
            window.open('resume.html', '_blank');
        }
    </script>
</body>
</html>