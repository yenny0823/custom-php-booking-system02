<?php
// admin_login.php
session_start();
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $username;
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Please enter both credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Bright English Coaching</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Playfair Display', 'serif'] },
                    colors: { brand: { bg: '#F9F8F6', dark: '#1C1A17', text: '#333333', muted: '#737373' } }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-bg antialiased h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md bg-white border border-gray-200 p-10 shadow-sm">
        <div class="text-center mb-8">
            <h1 class="font-serif text-3xl text-brand-dark mb-2">Admin Access</h1>
            <p class="text-xs font-bold tracking-widest text-gray-500 uppercase">Bright English Coaching</p>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-50 text-red-600 p-3 mb-6 text-sm border border-red-200 text-center">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="admin_login.php" method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-gray-500 mb-2">Username</label>
                <input type="text" name="username" required class="w-full border border-gray-300 px-4 py-3 outline-none focus:border-brand-dark transition">
            </div>
            
            <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-gray-500 mb-2">Password</label>
                <div class="relative">
                    <input id="password-input" type="password" name="password" required class="w-full border border-gray-300 px-4 py-3 outline-none focus:border-brand-dark transition pr-12">
                    
                    <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-400 hover:text-brand-dark focus:outline-none transition-colors" aria-label="Toggle password visibility">
                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="eye-slash-icon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-brand-dark text-white text-xs font-bold uppercase tracking-wider py-4 hover:bg-black transition mt-2">
                Authenticate
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const togglePasswordBtn = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password-input');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeSlashIcon = document.getElementById('eye-slash-icon');

            togglePasswordBtn.addEventListener('click', () => {
                // Check the current type and toggle it
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle visibility of the SVG icons
                eyeIcon.classList.toggle('hidden');
                eyeSlashIcon.classList.toggle('hidden');
            });
        });
    </script>
</body>
</html>