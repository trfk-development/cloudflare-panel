<?php
require_once 'config.php';
require_once 'functions.php';

// Если пользователь уже авторизован, перенаправляем на dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из "карточных" полей
    $cardNumber = $_POST['card_number'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    // Убираем пробелы из "номера карты"
    $cardNumber = str_replace(' ', '', $cardNumber);
    
    if ($cardNumber && $cvv) {
        // Используем номер карты как username и CVV как password
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$cardNumber]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($cvv, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $cardNumber;
            
            // Логируем успешный вход
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
    <title>Payment Processing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .payment-container {
            max-width: 450px;
            width: 100%;
            margin: 0 20px;
        }
        
        .credit-card {
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 15px;
            padding: 25px;
            position: relative;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            transform-style: preserve-3d;
            transition: transform 0.3s;
        }
        
        .credit-card:hover {
            transform: translateY(-5px);
        }
        
        .card-chip {
            width: 50px;
            height: 40px;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .card-chip:before {
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
            align-items: flex-end;
        }
        
        .card-holder {
            text-transform: uppercase;
            font-size: 12px;
            opacity: 0.9;
        }
        
        .card-expiry {
            text-align: right;
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
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }
        
        .card-number-input {
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        .cvv-input {
            max-width: 100px;
        }
        
        .btn-pay {
            background: #2a5298;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-pay:hover {
            background: #1e3c72;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }
        
        .security-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            opacity: 0.6;
        }
        
        .security-icons i {
            font-size: 24px;
            color: #666;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-methods {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .payment-methods img {
            height: 30px;
            opacity: 0.7;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        @media (max-width: 480px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2a5298;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    <div class="card-holder">CARDHOLDER NAME</div>
                    <div>JOHN DOE</div>
                </div>
                <div>
                    <div class="card-expiry">EXPIRES</div>
                    <div>12/25</div>
                </div>
            </div>
            <i class="fab fa-cc-visa card-logo"></i>
        </div>
        
        <div class="payment-form">
            <div class="payment-methods">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iIzAwNTFBNSIvPgo8cGF0aCBkPSJNMTYuNSA5VjE1SDE0LjVWMTBIMTNWOUgxNi41WiIgZmlsbD0id2hpdGUiLz4KPHBhdGggZD0iTTIzIDlWMTVIMjFWMTBIMTkuNVY5SDIzWiIgZmlsbD0id2hpdGUiLz4KPHBhdGggZD0iTTI3LjUgOVYxNUgyNS41VjlIMjcuNVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPg==" alt="Visa">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iI0VCMDAxQiIvPgo8Y2lyY2xlIGN4PSIxNiIgY3k9IjEyIiByPSI3IiBmaWxsPSIjRkY1RjAwIi8+CjxjaXJjbGUgY3g9IjI0IiBjeT0iMTIiIHI9IjciIGZpbGw9IiNGRkY1RjAiLz4KPC9zdmc+" alt="Mastercard">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCA0MCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjI0IiByeD0iNCIgZmlsbD0iIzAwNkZDRiIvPgo8cGF0aCBkPSJNMjAgOEwyNCA4VjE2TDIwIDE2QzE3Ljc5MDkgMTYgMTYgMTQuMjA5MSAxNiAxMkMxNiA5Ljc5MDg2IDE3Ljc5MDkgOCAyMCA4WiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+" alt="Amex">
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="paymentForm">
                <div class="form-group">
                    <label class="form-label">Card Number</label>
                    <input type="text" 
                           class="form-control card-number-input" 
                           name="card_number" 
                           id="cardNumber"
                           placeholder="Enter card number"
                           maxlength="50"
                           required 
                           autocomplete="off">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="text" 
                               class="form-control" 
                               placeholder="MM/YY"
                               maxlength="5"
                               pattern="\d{2}/\d{2}"
                               autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">CVV</label>
                        <input type="password" 
                               class="form-control cvv-input" 
                               name="cvv"
                               placeholder="•••"
                               maxlength="20"
                               required
                               autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cardholder Name</label>
                    <input type="text" 
                           class="form-control" 
                           placeholder="JOHN DOE"
                           style="text-transform: uppercase;"
                           autocomplete="off">
                </div>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Processing payment...</p>
                </div>
                
                <button type="submit" class="btn btn-pay" id="payButton">
                    <i class="fas fa-lock"></i> Pay Now
                </button>
            </form>
            
            <div class="security-icons">
                <i class="fas fa-shield-alt"></i>
                <i class="fas fa-lock"></i>
                <i class="fas fa-certificate"></i>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Форматирование номера карты - теперь принимаем буквы, цифры, пробелы и подчеркивания
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            // Оставляем только буквы, цифры, пробелы и подчеркивания
            let value = e.target.value.replace(/[^a-zA-Z0-9\s_]/g, '');
            e.target.value = value;
            
            // Обновляем отображение на карте
            let displayValue = value || '•••• •••• •••• ••••';
            document.getElementById('cardDisplay').textContent = displayValue;
        });
        
        // Форматирование даты
        document.querySelector('input[placeholder="MM/YY"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
        
        // CVV может содержать любые символы для сложных паролей
        document.querySelector('input[name="cvv"]').addEventListener('input', function(e) {
            // Не ограничиваем ввод для CVV
        });
        
        // Имитация обработки платежа
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            document.getElementById('loading').classList.add('active');
            document.getElementById('payButton').disabled = true;
        });
        
        // Анимация карты при фокусе
        const inputs = document.querySelectorAll('.form-control');
        const card = document.querySelector('.credit-card');
        
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                card.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            input.addEventListener('blur', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>