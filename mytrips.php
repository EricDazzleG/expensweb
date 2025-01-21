<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle trip deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $trip_id = filter_var($_POST['trip_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // First check if there are any expenses or activities linked to this trip
    $check_expenses = $conn->prepare("SELECT COUNT(*) as count FROM expenses WHERE trip_id = ?");
    $check_expenses->bind_param("i", $trip_id);
    $check_expenses->execute();
    $expenses_count = $check_expenses->get_result()->fetch_assoc()['count'];
    
    $check_activities = $conn->prepare("SELECT COUNT(*) as count FROM activities WHERE trip_id = ?");
    $check_activities->bind_param("i", $trip_id);
    $check_activities->execute();
    $activities_count = $check_activities->get_result()->fetch_assoc()['count'];
    
    if ($expenses_count > 0 || $activities_count > 0) {
        $message = "Cannot delete trip. Please delete all associated expenses and activities first.";
    } else {
        // Delete the trip
        $delete_stmt = $conn->prepare("DELETE FROM trips WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $trip_id, $user_id);
        
        if ($delete_stmt->execute()) {
            $message = "Trip deleted successfully!";
        } else {
            $message = "Error deleting trip.";
        }
    }
}

// Fetch all trips for the user
$stmt = $conn->prepare("
    SELECT 
        t.*,
        COALESCE(SUM(e.amount), 0) as total_expenses,
        (t.budget - COALESCE(SUM(e.amount), 0)) as remaining_budget
    FROM trips t
    LEFT JOIN expenses e ON t.id = e.trip_id
    WHERE t.user_id = ?
    GROUP BY t.id
    ORDER BY t.start_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Trips - TravelHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        h1 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 2rem;
            background: linear-gradient(to right, #a855f7, #ec4899);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .trips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .trip-card {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: transform 0.2s;
        }

        .trip-card:hover {
            transform: translateY(-5px);
        }

        .trip-header {
            margin-bottom: 1rem;
        }

        .trip-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .trip-dates {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .trip-description {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .trip-budget {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .budget-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .budget-item:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(to right, #a855f7, #ec4899);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .trip-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }

        .modal-content p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .btn-confirm {
            padding: 0.5rem 1.5rem;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-cancel {
            padding: 0.5rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-confirm:hover {
            opacity: 0.9;
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1>My Trips</h1>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'Cannot') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="trips-grid">
                <?php foreach ($trips as $trip): ?>
                    <div class="trip-card">
                        <div class="trip-header">
                            <h2><?php echo htmlspecialchars($trip['title']); ?></h2>
                            <span class="trip-dates">
                                <?php echo date('M d, Y', strtotime($trip['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($trip['end_date'])); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($trip['description'])): ?>
                            <p class="trip-description"><?php echo htmlspecialchars($trip['description']); ?></p>
                        <?php endif; ?>

                        <div class="trip-budget">
                            <div class="budget-item">
                                <span>Budget:</span>
                                <span>$<?php echo number_format($trip['budget'], 2); ?></span>
                            </div>
                            <div class="budget-item">
                                <span>Spent:</span>
                                <span>$<?php echo number_format($trip['total_expenses'], 2); ?></span>
                            </div>
                            <div class="budget-item">
                                <span>Remaining:</span>
                                <span>$<?php echo number_format($trip['remaining_budget'], 2); ?></span>
                            </div>
                        </div>

                        <div class="trip-actions">
                            <a href="edit_trip.php?id=<?php echo $trip['id']; ?>" class="btn">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $trip['id']; ?>, '<?php echo htmlspecialchars($trip['title'], ENT_QUOTES); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Delete Trip</h2>
            <p>Are you sure you want to delete "<span id="tripTitle"></span>"?</p>
            <p>This action cannot be undone.</p>
            <div class="modal-buttons">
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="trip_id" id="tripId">
                    <button type="submit" name="delete_trip" class="btn-confirm">Delete</button>
                </form>
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(tripId, tripTitle) {
            document.getElementById('deleteModal').classList.add('active');
            document.getElementById('tripId').value = tripId;
            document.getElementById('tripTitle').textContent = tripTitle;
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>