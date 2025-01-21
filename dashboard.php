<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch trips for expense form dropdown and news
$stmt = $conn->prepare("SELECT id, title FROM trips WHERE user_id = ? AND start_date >= CURRENT_DATE() ORDER BY start_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch categories for expense form dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE user_id = ? OR user_id IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch next upcoming trip
$stmt = $conn->prepare("
    SELECT title, start_date 
    FROM trips 
    WHERE user_id = ? 
    AND start_date >= CURRENT_DATE()
    ORDER BY start_date ASC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$next_trip = $stmt->get_result()->fetch_assoc();

// Fetch travel news for destinations
function getTravelNews($destinations) {
    $apiKey = 'b9a18d2723be4cda9a43eb953e85928c'; // Your NewsAPI key
    $news = [];
    
    if (empty($destinations)) {
        error_log("No destinations found for news search");
        return [];
    }
    
    foreach ($destinations as $trip) {
        $destination = urlencode($trip['title'] . ' travel');
        $url = "https://newsapi.org/v2/everything?q={$destination}&sortBy=publishedAt&language=en&pageSize=2&apiKey={$apiKey}";
        
        error_log("Fetching news for destination: " . $trip['title']);
        $response = @file_get_contents($url);
        
        if ($response === false) {
            error_log("Failed to fetch news for " . $trip['title'] . ": " . error_get_last()['message']);
            continue;
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            continue;
        }
        
        if (isset($result['articles']) && !empty($result['articles'])) {
            $news = array_merge($news, array_slice($result['articles'], 0, 2));
        } else {
            error_log("No articles found for " . $trip['title']);
            if (isset($result['status'])) {
                error_log("API response status: " . $result['status']);
                if (isset($result['message'])) {
                    error_log("API message: " . $result['message']);
                }
            }
        }
    }
    
    if (empty($news)) {
        error_log("No news articles found for any destination");
        return [];
    }
    
    // Sort by published date and get latest 4 articles
    usort($news, function($a, $b) {
        return strtotime($b['publishedAt']) - strtotime($a['publishedAt']);
    });
    
    return array_slice($news, 0, 4);
}

$travelNews = getTravelNews($trips);

function escape_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getWeatherData($city, $date) {
    // First, get coordinates for the city using Geocoding API
    $geocodeUrl = "https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($city) . "&count=1&language=en&format=json";
    $geocodeData = @file_get_contents($geocodeUrl);
    
    if ($geocodeData === false) {
        return null;
    }
    
    $geocodeResult = json_decode($geocodeData, true);
    
    if (empty($geocodeResult['results'])) {
        return null;
    }
    
    $location = $geocodeResult['results'][0];
    $lat = $location['latitude'];
    $lon = $location['longitude'];
    
    // Calculate days between now and trip date for forecast index
    $today = new DateTime();
    $tripDate = new DateTime($date);
    $interval = $today->diff($tripDate);
    $daysDiff = $interval->days;
    
    // Then fetch weather data using coordinates
    $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&daily=temperature_2m_max,precipitation_probability_max&timezone=auto&forecast_days=" . ($daysDiff + 1);
    $weatherData = @file_get_contents($weatherUrl);
    
    if ($weatherData === false) {
        return null;
    }
    
    $data = json_decode($weatherData, true);
    
    // Return weather for the specific date if available
    if (isset($data['daily']) && isset($data['daily']['temperature_2m_max'][$daysDiff])) {
        return [
            'temperature' => $data['daily']['temperature_2m_max'][$daysDiff],
            'precipitation' => $data['daily']['precipitation_probability_max'][$daysDiff]
        ];
    }
    
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Dashboard</title>
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

        .top-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .welcome-card {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            height: 100%;
        }

        .weather-card {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            height: 200px;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(to right, #a855f7, #ec4899);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .weather-icon {
            width: 3rem;
            height: 3rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .weather-temp {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0.25rem 0;
            background: linear-gradient(to right, #a855f7, #ec4899);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .weather-location {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .weather-rain {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.5rem;
        }

        .expense-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
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
            position: relative;
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

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }

        .btn-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.5rem;
        }

        .news-card {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .news-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }

        .news-list {
            display: grid;
            gap: 1rem;
        }

        .news-item {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }

        .news-item:hover {
            transform: translateX(5px);
        }

        .news-item a {
            color: white;
            text-decoration: none;
        }

        .news-item-title {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .news-item-source {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .news-item-date {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="top-row">
                <!-- Welcome Message -->
                <div class="welcome-card">
                    <h1 class="welcome-title">Welcome back, <?php echo escape_html($user['username']); ?>!</h1>
                    <p class="text-gray-400">Track your travel plans and stay updated with destination weather forecasts.</p>
                    
                    <div class="expense-buttons">
                        <button class="btn" onclick="openModal('addExpenseModal')">
                            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Expense
                        </button>
                        <button class="btn" onclick="openModal('manageExpensesModal')">
                            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Manage Expenses
                        </button>
                    </div>
                </div>

                <!-- Weather Information -->
                <?php if ($next_trip): 
                    $weatherData = getWeatherData($next_trip['title'], $next_trip['start_date']);
                    if ($weatherData): ?>
                    <div class="weather-card">
                        <div class="weather-location"><?php echo escape_html($next_trip['title']); ?></div>
                        <div class="weather-temp"><?php echo round($weatherData['temperature']); ?>°C</div>
                        <div class="weather-rain">
                            <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                            <?php echo $weatherData['precipitation']; ?>% rain
                        </div>
                    </div>
                <?php else: ?>
                    <div class="weather-card">
                        <p>Weather forecast unavailable</p>
                    </div>
                <?php endif; else: ?>
                    <div class="weather-card">
                        <p>No upcoming trips</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Travel News Section -->
            <div class="news-card">
                <h2 class="news-title">Latest Travel News</h2>
                <div class="news-list">
                    <?php if (!empty($travelNews)): ?>
                        <?php foreach ($travelNews as $news): ?>
                            <div class="news-item">
                                <a href="<?php echo escape_html($news['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <div class="news-item-title"><?php echo escape_html($news['title']); ?></div>
                                    <div class="news-item-source">
                                        <?php echo escape_html($news['source']['name']); ?> • 
                                        <span class="news-item-date">
                                            <?php echo date('M j, Y', strtotime($news['publishedAt'])); ?>
                                        </span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="news-item">
                            <p>No travel news available. Please check if:
                                <?php if (empty($trips)): ?>
                                    You have any upcoming trips added.
                                <?php else: ?>
                                    The News API key is properly configured.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Expense Modal -->
            <div id="addExpenseModal" class="modal">
                <div class="modal-content">
                    <button class="btn-close" onclick="closeModal('addExpenseModal')">×</button>
                    <h2 class="modal-title">Add New Expense</h2>
                    <form id="addExpenseForm" action="add_expense.php" method="POST">
                        <div class="form-group">
                            <label class="form-label">Trip</label>
                            <select name="trip_id" class="form-input" required>
                                <?php foreach ($trips as $trip): ?>
                                    <option value="<?php echo $trip['id']; ?>"><?php echo escape_html($trip['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-input" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo escape_html($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" step="0.01" class="form-input" required>
                        </div>
                        <button type="submit" class="btn">Add Expense</button>
                    </form>
                </div>
            </div>

            <!-- Manage Expenses Modal -->
            <div id="manageExpensesModal" class="modal">
                <div class="modal-content">
                    <button class="btn-close" onclick="closeModal('manageExpensesModal')">×</button>
                    <h2 class="modal-title">Manage Expenses</h2>
                    <!-- Add expense management content here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>