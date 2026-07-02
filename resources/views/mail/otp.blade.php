<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #F1F5F9; margin: 0; padding: 40px 20px; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: #295DA8; padding: 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; letter-spacing: 0.5px; }
        .body { padding: 36px 32px; }
        .greeting { color: #374151; font-size: 16px; margin-bottom: 16px; }
        .otp-box { background: #F1F5F9; border: 2px dashed #295DA8; border-radius: 10px; padding: 24px; text-align: center; margin: 24px 0; }
        .otp-code { font-size: 40px; font-weight: 700; letter-spacing: 12px; color: #295DA8; font-family: 'Courier New', monospace; }
        .note { color: #6B7280; font-size: 13px; margin-top: 20px; line-height: 1.6; }
        .footer { background: #F9FAFB; padding: 20px 32px; text-align: center; border-top: 1px solid #E5E7EB; }
        .footer p { color: #9CA3AF; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>NumbersSystem</h1>
        </div>
        <div class="body">
            <p class="greeting">Hello <strong>{{ $name }}</strong>,</p>
            <p style="color:#6B7280; font-size:14px;">Use the code below to verify your email address. This code is valid for <strong>10 minutes</strong>.</p>
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
            </div>
            <p class="note">If you did not request this code, you can safely ignore this email. Do not share this OTP with anyone.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} NumbersSystem. All rights reserved.</p>
        </div>
    </div>
</body>
</html>