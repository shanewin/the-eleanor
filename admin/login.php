<?php
require_once 'auth.php';

// Already logged in? Go to dashboard
if (isAdmin()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
$resetSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $email = $_POST['email'] ?? '';

    if ($action === 'reset') {
        // Supabase password reset
        $ch = curl_init(SUPABASE_URL . '/auth/v1/recover');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_PUBLISHABLE_KEY
        ]);
        curl_exec($ch);
        curl_close($ch);
        $resetSent = true;
    } else {
        $password = $_POST['password'] ?? '';
        $result = supabaseLogin($email, $password);

        if ($result && !empty($result['access_token'])) {
            $_SESSION['supabase_access_token'] = $result['access_token'];
            $_SESSION['supabase_refresh_token'] = $result['refresh_token'] ?? null;
            $_SESSION['supabase_user'] = $result['user'] ?? null;
            header('Location: /admin/index.php');
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Eleanor | Command Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        body {
            background: #0f1117;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: #1a1d27;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 2.5rem 2rem;
        }
        .brand { letter-spacing: 0.3em; font-size: 0.7rem; color: rgba(255,255,255,0.35); text-transform: uppercase; }
        .form-control {
            background: #12141c;
            border: 1px solid rgba(255,255,255,0.08);
            color: #fff;
            border-radius: 8px;
            padding: 0.7rem 0.9rem;
            font-size: 0.9rem;
        }
        .form-control:focus {
            background: #0d0f15;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            color: #fff;
        }
        .form-label { font-size: 0.75rem; color: rgba(255,255,255,0.4); font-weight: 500; letter-spacing: 0.03em; }
        .btn-primary {
            background: #3b82f6;
            border: none;
            border-radius: 8px;
            padding: 0.7rem;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .btn-primary:hover { background: #2563eb; }
        .forgot-link { font-size: 0.75rem; color: rgba(255,255,255,0.3); text-decoration: none; }
        .forgot-link:hover { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand mb-1">The Eleanor</div>
            <h5 class="fw-semibold text-white mb-1" style="font-size:1.1rem">Command Center</h5>
            <p class="text-white-50 mb-0" style="font-size:0.78rem">Lead Response & Enrichment Platform</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 text-center" style="font-size:0.8rem; border-radius:8px"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($resetSent): ?>
            <div class="alert alert-success py-2 text-center" style="font-size:0.8rem; border-radius:8px">Password reset link sent. Check your email.</div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="action" value="login" id="formAction">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required autofocus>
            </div>
            <div class="mb-3" id="passwordGroup">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">Sign In</button>
            <div class="text-center">
                <a href="#" class="forgot-link" id="forgotLink">Forgot password?</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('forgotLink').addEventListener('click', function(e) {
            e.preventDefault();
            const pwGroup = document.getElementById('passwordGroup');
            const btn = document.getElementById('submitBtn');
            const action = document.getElementById('formAction');
            const pw = document.getElementById('password');

            if (action.value === 'login') {
                pwGroup.style.display = 'none';
                pw.removeAttribute('required');
                btn.textContent = 'Send Reset Link';
                action.value = 'reset';
                this.textContent = 'Back to sign in';
            } else {
                pwGroup.style.display = 'block';
                pw.setAttribute('required', '');
                btn.textContent = 'Sign In';
                action.value = 'login';
                this.textContent = 'Forgot password?';
            }
        });
    </script>
</body>
</html>
