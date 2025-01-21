<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all expenses grouped by category
// Fetch all expenses grouped by category
$stmt = $conn->prepare("
    SELECT 
        c.name as category_name,
        t.title as trip_destination,
        e.amount,
        e.date as date,
        e.category_id
    FROM expenses e
    JOIN trips t ON e.trip_id = t.id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY e.date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Calculate total expenses by category
$expenses_by_category = [];
foreach ($expenses as $expense) {
    $category = $expense['category_name'];  // Changed from 'category' to 'category_name'
    if (!isset($expenses_by_category[$category])) {
        $expenses_by_category[$category] = 0;
    }
    $expenses_by_category[$category] += $expense['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - TravelHub</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .chart-container {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .expenses-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 2rem;
        }

        .expenses-table th,
        .expenses-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .expenses-table th {
            background: rgba(0, 0, 0, 0.3);
            font-weight: 500;
            color: white;
        }

        .expenses-table tr:hover td {
            background: rgba(255, 255, 255, 0.05);
        }

        .amount {
            font-family: monospace;
            color: white;
        }

        .budget-progress {
            height: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.25rem;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .budget-bar {
            height: 100%;
            background: linear-gradient(to right, #a855f7, #ec4899);
            transition: width 0.3s ease;
        }

        h1, h2, h3 {
            color: white;
        }

        .text-3xl {
            font-size: 1.875rem;
            line-height: 2.25rem;
        }

        .font-bold {
            font-weight: 700;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .text-lg {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }

        .font-semibold {
            font-weight: 600;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .text-gray-400 {
            color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1 class="text-3xl font-bold mb-6">Expenses Overview</h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3 class="text-lg font-semibold mb-2">Total Expenses</h3>
                    <p class="text-3xl font-bold">
                        $<?php echo number_format(array_sum(array_column($expenses, 'amount')), 2); ?>
                    </p>
                </div>
                
                <div class="stat-card">
                    <h3 class="text-lg font-semibold mb-2">Average per Trip</h3>
                    <p class="text-3xl font-bold">
                        $<?php 
                        $trip_count = count(array_unique(array_column($expenses, 'trip_destination')));
                        echo $trip_count > 0 
                            ? number_format(array_sum(array_column($expenses, 'amount')) / $trip_count, 2)
                            : '0.00';
                        ?>
                    </p>
                </div>
            </div>

            <!-- Expenses by Category Chart -->
            <div class="chart-container">
                <h3 class="text-xl font-semibold mb-4">Expenses by Category</h3>
                <canvas id="categoryChart"></canvas>
            </div>

            <!-- Recent Expenses Table -->
            <div class="stat-card">
                <h3 class="text-xl font-semibold mb-4">Recent Expenses</h3>
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Trip</th>
                            <th>Category</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($expenses, 0, 10) as $expense): ?>
                            <tr>
                            <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
<td><?php echo htmlspecialchars($expense['trip_destination']); ?></td>
<td><?php echo htmlspecialchars($expense['category_name']); ?></td>
<td class="amount">$<?php echo number_format($expense['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($expenses_by_category)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($expenses_by_category)); ?>,
                    backgroundColor: [
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>