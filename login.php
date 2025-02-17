<?php
session_start();
require_once 'database.php';

// Initialize error message variable
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate email
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Prevent SQL injection using prepared statements
        $stmt = $conn->prepare("SELECT id, password, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password
            if (password_verify($_POST['password'], $user['password'])) {
                // Start session and store user data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Redirect to dashboard or home page
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid email or password";
            }
        } else {
            // Use same error message to prevent user enumeration
            $error_message = "Invalid email or password";
        }
        $stmt->close();
    }
}

// Function to escape HTML to prevent XSS
function escape_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            position: relative;
            overflow: hidden;
            background: radial-gradient(
                circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(168, 85, 247, 0.08) 0%,
                rgba(0, 0, 0, 0.97) 50%
            );
        }

        .background {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            overflow: hidden;
        }

        .background::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.8) 0%, rgba(0, 0, 0, 0.9) 100%);
            pointer-events: none;
        }

        .glow {
            position: absolute;
            width: 40vmax;
            height: 40vmax;
            border-radius: 50%;
            background: radial-gradient(
                circle at center,
                rgba(236, 72, 153, 0.12) 0%,
                rgba(168, 85, 247, 0.08) 30%,
                rgba(168, 85, 247, 0) 70%
            );
            transform: translate(-50%, -50%);
            pointer-events: none;
            transition: all 0.15s ease;
            mix-blend-mode: screen;
        }

        .container {
            width: 100%;
            max-width: 400px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(to right, #a855f7, #ec4899);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #9ca3af;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.5);
            border-radius: 0.5rem;
            color: #fff;
            font-size: 1rem;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.2);
        }

        .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            width: 1.25rem;
            height: 1.25rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, #a855f7, #ec4899);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        button:hover {
            opacity: 0.9;
        }

        .signup-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.2s;
        }

        .signup-link:hover {
            color: #a855f7;
        }

        .signup-link svg {
            width: 1rem;
            height: 1rem;
            transition: transform 0.2s;
        }

        .signup-link:hover svg {
            transform: translateX(4px);
        }

        .error-message {
            background-color: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="background">
        <div class="glow"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1>Welcome Back</h1>
            <p class="subtitle">Sign in to continue</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo escape_html($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo escape_html($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <input type="email" name="email" placeholder="Email address" required 
                       value="<?php echo isset($_POST['email']) ? escape_html($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Sign In</button>
        </form>

        <a href="signup.php" class="signup-link">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            New user? Create account
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>

    <script>
        // Add mouse tracking for reactive background
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth) * 100;
            const y = (e.clientY / window.innerHeight) * 100;
            
            // Update the gradient position
            document.body.style.setProperty('--mouse-x', `${x}%`);
            document.body.style.setProperty('--mouse-y', `${y}%`);
            
            // Move the glow effect
            const glow = document.querySelector('.glow');
            glow.style.left = e.clientX + 'px';
            glow.style.top = e.clientY + 'px';
        });
    </script>
</body>
</html>
