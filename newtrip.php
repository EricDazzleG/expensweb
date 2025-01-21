<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destination = trim(filter_var($_POST['destination'], FILTER_SANITIZE_STRING));
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (!empty($destination) && !empty($start_date) && !empty($end_date)) {
        $stmt = $conn->prepare("
            INSERT INTO trips (user_id, title, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, 'Planning')
        ");
        $stmt->bind_param("isss", $user_id, $destination, $start_date, $end_date);
        
        if ($stmt->execute()) {
            $trip_id = $stmt->insert_id;
            
            // Handle activities
            if (isset($_POST['activities']) && is_array($_POST['activities'])) {
                $stmt = $conn->prepare("
                    INSERT INTO activities (trip_id, title, description, planned_date) 
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($_POST['activities'] as $activity) {
                    $activity_title = trim(filter_var($activity['title'], FILTER_SANITIZE_STRING));
                    $activity_desc = trim(filter_var($activity['description'], FILTER_SANITIZE_STRING));
                    $planned_date = $activity['date'];
                    
                    $stmt->bind_param("isss", $trip_id, $activity_title, $activity_desc, $planned_date);
                    $stmt->execute();
                }
            }
            
            header("Location: mytrips.php");
            exit();
        } else {
            $message = "Error creating trip.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Trip - TravelHub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #000000 50%, #1a1a1a 100%);
            color: white;
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 240px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            width: calc(100% - 240px);
        }

        .sidebar.collapsed + .main-content {
            margin-left: 72px;
            width: calc(100% - 72px);
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: white;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #a855f7;
        }

        .activities-container {
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
        }

        .activity-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(to right, #a855f7, #ec4899);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        h1, h2, h3 {
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1 class="text-3xl font-bold mb-6">Plan New Trip</h1>

            <div class="form-container">
                <?php if (!empty($message)): ?>
                    <div class="alert"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="newTripForm">
                    <div class="form-group">
                        <label class="form-label">Destination</label>
                        <input type="text" name="destination" class="form-input" required>
                    </div>

                    <div class="grid">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-input" required>
                        </div>
                    </div>

                    <div class="activities-container">
                        <h3 class="text-xl font-semibold mb-4">Planned Activities</h3>
                        <div id="activitiesList"></div>
                        <button type="button" class="btn btn-secondary" onclick="addActivity()">
                            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Activity
                        </button>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="btn">Create Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let activityCount = 0;

        function addActivity() {
            const container = document.createElement('div');
            container.className = 'activity-item';
            container.innerHTML = `
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label">Activity Title</label>
                        <input type="text" name="activities[${activityCount}][title]" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Planned Date</label>
                        <input type="date" name="activities[${activityCount}][date]" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="activities[${activityCount}][description]" class="form-input" rows="2"></textarea>
                </div>
                <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
            `;
            
            document.getElementById('activitiesList').appendChild(container);
            activityCount++;
        }
    </script>
</body>
</html>