<?php
require_once 'config.php';
require_once 'functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = $_POST['card_number'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    // Remove spaces from card number
    $cardNumber = str_replace(' ', '', $cardNumber);
    
    if ($cardNumber && $cvv) {
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$cardNumber]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($cvv, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $cardNumber;
            
            logAction($pdo, $user['id'], "Login successful", "IP: " . $_SERVER['REMOTE_ADDR']);
            
            header('Location: ' . BASE_PATH . 'dashboard.php');
            exit;
        } else {
            $error = 'Invalid card details';
        }
    } else {
        $error = 'Please fill all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment Gateway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #7209b7;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            padding: 20px;
        }
        
        .payment-container {
            max-width: 450px;
            width: 100%;
        }
        
        .credit-card {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 20px;
            padding: 25px;
            color: white;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .credit-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.05);
            transform: rotate(45deg);
        }
        
        .card-chip {
            width: 50px;
            height: 40px;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            border-radius: 8px;
            margin-bottom: 25px;
            position: relative;
        }
        
        .card-chip::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 35px;
            height: 25px;
            border: 2px solid #c9a961;
            border-radius: 4px;
        }
        
        .card-number-display {
            font-size: 22px;
            letter-spacing: 3px;
            margin-bottom: 25px;
            font-family: 'Courier New', monospace;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .card-info {
            display: flex;
            justify-content: space-between;
        }
        
        .card-holder, .card-expiry {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .card-logo {
            position: absolute;
            right: 25px;
            bottom: 25px;
            font-size: 40px;
            opacity: 0.8;
        }
        
        .payment-form {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }
        
        .card-number-input {
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-pay:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .security-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        
        .security-icons i {
            font-size: 24px;
            color: #aaa;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #dc2626;
        }
        
        .payment-methods {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            justify-content: center;
        }
        
        .payment-methods img {
            height: 30px;
            opacity: 0.6;
        }
        
        .loading {
            display: none;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .credit-card {
                height: 200px;
                padding: 20px;
            }
            
            .card-number-display {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="credit-card">
            <div class="card-chip"></div>
            <div class="card-number-display" id="cardDisplay">•••• •••• •••• ••••</div>
            <div class="card-info">
                <div>
                    <div class="card-holder">CARDHOLDER</div>
                    <div>YOUR NAME</div>
                </div>
                <div class="text-end">
                    <div class="card-expiry">EXPIRES</div>
                    <div>12/25</div>
                </div>
            </div>
            <i class="fab fa-cc-visa card-logo"></i>
        </div>
        
        <div class="payment-form">
            <div class="payment-methods">
                <i class="fab fa-cc-visa fa-2x" style="color: #1a1f71;"></i>
                <i class="fab fa-cc-mastercard fa-2x" style="color: #eb001b;"></i>
                <i class="fab fa-cc-amex fa-2x" style="color: #006fcf;"></i>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="paymentForm">
                <div class="form-group">
                    <label class="form-label">Card Number</label>
                    <input type="text" class="form-control card-number-input" name="card_number" id="cardNumber"
                           placeholder="Enter your card number" maxlength="50" required autocomplete="off">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="text" class="form-control" placeholder="MM/YY" maxlength="5" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">CVV</label>
                        <input type="password" class="form-control" name="cvv" placeholder="•••" maxlength="50" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cardholder Name</label>
                    <input type="text" class="form-control" placeholder="John Doe" style="text-transform: uppercase;" autocomplete="off">
                </div>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p class="text-center text-muted">Processing payment...</p>
                </div>
                
                <button type="submit" class="btn btn-pay" id="payButton">
                    <i class="fas fa-lock"></i>Pay Securely
                </button>
            </form>
            
            <div class="security-icons">
                <i class="fas fa-shield-alt"></i>
                <i class="fas fa-lock"></i>
                <i class="fas fa-certificate"></i>
            </div>
        </div>
    </div>
    
    <script>
        // Format card number display
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^a-zA-Z0-9\s_]/g, '');
            e.target.value = value;
            document.getElementById('cardDisplay').textContent = value || '•••• •••• •••• ••••';
        });
        
        // Format expiry date
        document.querySelector('input[placeholder="MM/YY"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
        
        // Form submit animation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            document.getElementById('loading').classList.add('active');
            document.getElementById('payButton').disabled = true;
        });
        
        // Card hover effect
        const inputs = document.querySelectorAll('.form-control');
        const card = document.querySelector('.credit-card');
        
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                card.style.transform = 'translateY(-10px) scale(1.02)';
                card.style.transition = 'transform 0.3s ease';
            });
            input.addEventListener('blur', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>