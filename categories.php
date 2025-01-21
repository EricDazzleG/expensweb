<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = filter_var($_POST['category_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Check if category is used in any expenses
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?");
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        $message = "Cannot delete category as it is being used in expenses.";
    } else {
        // Only allow deletion of user's own categories
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $category_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Category deleted successfully!";
        } else {
            $message = "Error deleting category.";
        }
    }
}

// Handle new category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
    $category_name = trim(filter_var($_POST['category_name'], FILTER_SANITIZE_STRING));
    $category_type = trim(filter_var($_POST['category_type'], FILTER_SANITIZE_STRING));
    $icon = match($category_type) {
        'Food' => 'utensils',
        'Accommodation' => 'home',
        'Transportation' => 'car',
        'Activities' => 'ticket',
        'Shopping' => 'shopping-bag',
        'Miscellaneous' => 'ellipsis-h',
        default => 'tag'
    };
    
    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name, type, user_id, icon) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $category_name, $category_type, $user_id, $icon);
        
        if ($stmt->execute()) {
            $message = "Category added successfully!";
        } else {
            $message = "Error adding category.";
        }
    }
}

// Fetch all categories (default + user's custom)
$stmt = $conn->prepare("
    SELECT id, name, type, icon, user_id IS NULL as is_default 
    FROM categories 
    WHERE user_id = ? OR user_id IS NULL 
    ORDER BY type, name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group categories by type
$grouped_categories = [];
foreach ($categories as $category) {
    $grouped_categories[$category['type']][] = $category;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - TravelHub</title>
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

        .category-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .category-section {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .category-list {
            margin-top: 1rem;
        }

        .category-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: transform 0.2s;
        }

        .category-item:hover {
            transform: translateX(5px);
        }

        .new-category-form {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
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
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        select.form-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        select.form-input option {
            background-color: #1a1a1a;
            color: white;
            padding: 0.75rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #a855f7;
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

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background: rgba(168, 85, 247, 0.2);
            border-radius: 0.25rem;
            font-size: 0.75rem;
            margin-left: auto;
        }

        .category-actions {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h1 class="text-3xl font-bold mb-6">Categories</h1>

            <?php if (!empty($message)): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- New Category Form -->
            <div class="new-category-form">
                <h2 class="text-xl font-semibold mb-4">Add New Category</h2>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category Type</label>
                        <select name="category_type" class="form-input" required>
                            <option value="Food">Food</option>
                            <option value="Accommodation">Accommodation</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Activities">Activities</option>
                            <option value="Shopping">Shopping</option>
                            <option value="Miscellaneous">Miscellaneous</option>
                        </select>
                    </div>

                    <button type="submit" name="new_category" class="btn">
                        <i class="fas fa-plus"></i>
                        Add Category
                    </button>
                </form>
            </div>

            <!-- Categories List -->
            <div class="category-container">
                <?php foreach ($grouped_categories as $type => $type_categories): ?>
                    <div class="category-section">
                        <h3 class="text-lg font-semibold capitalize"><?php echo htmlspecialchars($type); ?></h3>
                        <div class="category-list">
                            <?php foreach ($type_categories as $category): ?>
                                <div class="category-item">
                                    <i class="fas fa-<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                    <?php if ($category['is_default']): ?>
                                        <span class="category-badge">Default</span>
                                    <?php else: ?>
                                        <div class="category-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" name="delete_category" class="btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>