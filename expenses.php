<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to get exchange rates
function getExchangeRates() {
    $url = "https://open.er-api.com/v6/latest/USD";
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['rates'])) {
            return $data['rates'];
        }
    }
    return null;
}

$exchangeRates = getExchangeRates();

// Common currencies list
$commonCurrencies = [
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'JPY' => 'Japanese Yen',
    'AUD' => 'Australian Dollar',
    'CAD' => 'Canadian Dollar',
    'CHF' => 'Swiss Franc',
    'CNY' => 'Chinese Yuan',
    'INR' => 'Indian Rupee',
    'SGD' => 'Singapore Dollar'
];

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
    $category = $expense['category_name'];
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

        /* Currency Converter Styles */
        .currency-converter {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .converter-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }

        .converter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .converter-input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: white;
            transition: border-color 0.2s;
        }

        .converter-input:focus {
            outline: none;
            border-color: #a855f7;
        }

        .converter-result {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            margin-top: 1rem;
            display: none;
        }

        .result-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin-bottom: 0.5rem;
        }

        .result-rate {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1 class="text-3xl font-bold mb-6">Expenses Overview</h1>

            <!-- Currency Converter -->
            <div class="currency-converter">
                <h2 class="converter-title">Currency Converter</h2>
                <div class="converter-form">
                    <div>
                        <label class="form-label">From</label>
                        <select id="fromCurrency" class="converter-input">
                            <?php foreach ($commonCurrencies as $code => $name): ?>
                                <option value="<?php echo $code; ?>"><?php echo "$code - $name"; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <select id="toCurrency" class="converter-input">
                            <?php foreach ($commonCurrencies as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $code === 'USD' ? 'selected' : ''; ?>>
                                    <?php echo "$code - $name"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Amount</label>
                        <input type="number" id="convertAmount" class="converter-input" step="0.01" value="1">
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn" onclick="convertCurrency()">Convert</button>
                    </div>
                </div>
                <div id="conversionResult" class="converter-result">
                    <div class="result-amount"></div>
                    <div class="result-rate"></div>
                </div>
            </div>

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
        // Currency conversion functionality
        const exchangeRates = <?php echo json_encode($exchangeRates ?: new stdClass()); ?>;
        
        function convertCurrency() {
            const fromCurrency = document.getElementById('fromCurrency').value;
            const toCurrency = document.getElementById('toCurrency').value;
            const amount = parseFloat(document.getElementById('convertAmount').value);
            
            if (!exchangeRates || !amount) {
                alert('Currency conversion is currently unavailable. Please try again later.');
                return;
            }
            
            // Convert through USD as base
            const fromRate = exchangeRates[fromCurrency] || 1;
            const toRate = exchangeRates[toCurrency] || 1;
            
            const result = (amount / fromRate) * toRate;
            
            const resultElement = document.getElementById('conversionResult');
            resultElement.style.display = 'block';
            resultElement.querySelector('.result-amount').textContent = 
                `${amount.toFixed(2)} ${fromCurrency} = ${result.toFixed(2)} ${toCurrency}`;
            resultElement.querySelector('.result-rate').textContent = 
                `1 ${fromCurrency} = ${(toRate/fromRate).toFixed(4)} ${toCurrency}`;
        }

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
