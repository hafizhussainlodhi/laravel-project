<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - NumbersSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .form-input {
            --tw-bg-opacity: 1;
            --tw-shadow: 0 1px 3px 0 rgba(0, 0, 0, .1), 0 1px 2px -1px rgba(0, 0, 0, .1);
            --tw-ring-inset: ;
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: rgba(17, 24, 39, 0.1);
            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);

            appearance: none;
            background-color: rgb(255 255 255 / var(--tw-bg-opacity, 1));
            border-radius: .375rem;
            box-shadow:
                var(--tw-ring-offset-shadow),
                var(--tw-ring-shadow),
                var(--tw-shadow);
            color: #4b5563;
            font-size: .875rem;
            line-height: 1.25rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            --tw-ring-color: #295DA8;
        }

        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* OTP Cells */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .otp-cell {
            width: 44px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #295DA8;
            border-radius: .375rem;
            border: none;
            --tw-shadow: 0 1px 3px 0 rgba(0, 0, 0, .1), 0 1px 2px -1px rgba(0, 0, 0, .1);
            --tw-ring-color: rgba(17, 24, 39, 0.1);
            --tw-ring-offset-shadow: 0 0 0 0px #fff;
            --tw-ring-shadow: 0 0 0 calc(2px + 0px) var(--tw-ring-color);
            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
            outline: none;
            background-color: #fff;
            -moz-appearance: textfield;
        }

        .otp-cell:focus {
            --tw-ring-color: #295DA8;
        }

        .otp-cell.filled {
            --tw-ring-color: #295DA8;
        }

        .otp-cell.error-cell {
            --tw-ring-color: #ef4444;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-5px); }
            60%       { transform: translateX(5px); }
        }

        /* Alert */
        .alert {
            border-radius: .375rem;
            padding: 0.75rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: none;
        }
        .alert.show { display: block; }
        .alert-error   { background: #FEE2E2; color: #991B1B; }
        .alert-success { background: #DCFCE7; color: #166534; }

        /* Spinner */
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        .btn-loading .btn-text { display: none; }
        .btn-loading .spinner  { display: block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Field error */
        .field-error {
            font-size: 0.75rem;
            color: #ef4444;
            margin-top: 4px;
            display: none;
        }
        .field-error.show { display: block; }
    </style>
</head>

<body class="bg-[#F1F5F9] flex flex-col items-center justify-center min-h-screen py-[80px] font-sans">

    <div class="mb-12">
        <img src="/logo.svg" class="h-8" alt="">
    </div>

    <div class="w-full max-w-[400px]">
        <div class="bg-white text-gray-500 shadow-md rounded-lg p-8">

            <!-- Heading -->
            <h2 class="text-center text-2xl mb-6" id="header-title">
                Create Account
            </h2>

            <svg class="block mx-auto mb-6" xmlns="http://www.w3.org/2000/svg" width="100" height="2" viewBox="0 0 100 2">
                <path fill="#D8E3EC" d="M0 0h100v2H0z"></path>
            </svg>

            <!-- Global Alerts -->
            <div class="alert alert-error" id="global-alert"></div>
            <div class="alert alert-success" id="global-success"></div>

            <!-- ═══ STEP 1: Registration Form ═══ -->
            <div id="step-form" class="font-medium">

                <!-- Name -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Name</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="John Doe" autocomplete="name">
                    <div class="field-error" id="err-name"></div>
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="john@example.com" autocomplete="email">
                    <div class="field-error" id="err-email"></div>
                </div>

                <!-- Phone Number -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Phone Number</label>
                    <input type="number" id="phone" name="phone" class="form-input" placeholder="03001234567">
                    <div class="field-error" id="err-phone"></div>
                </div>

                <!-- Company Name -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-input" placeholder="Acme Corp.">
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Min 8 chars" autocomplete="new-password">
                    <div class="field-error" id="err-password"></div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label class="block text-sm mb-2">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" placeholder="Repeat password" autocomplete="new-password">
                    <div class="field-error" id="err-password_confirmation"></div>
                </div>

                <!-- Hidden ref_code -->
                <input type="hidden" id="ref_code" value="{{ $refCode ?? '' }}">

                <!-- Button -->
                <button
                    type="button"
                    id="btn-send-otp"
                    onclick="sendOtp()"
                    class="w-full bg-[#295DA8] text-sm font-bold text-white py-2 rounded hover:bg-blue-700 transition">
                    <span class="btn-text">Continue</span>
                    <div class="spinner"></div>
                </button>

            </div>

            <!-- ═══ STEP 2: OTP Verification ═══ -->
            <div id="step-otp" class="font-medium" style="display:none;">

                <!-- Back -->
                <button type="button" onclick="goBack()" class="flex items-center gap-1 text-sm text-gray-400 hover:text-[#295DA8] transition mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>

                <p class="text-sm text-center text-gray-500 mb-1">
                    We sent a 6-digit code to
                </p>
                <p class="text-sm text-center font-semibold text-[#295DA8] mb-2" id="otp-email-display"></p>

                <!-- OTP Inputs -->
                <div class="otp-inputs">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-0" oninput="otpInput(this,0)" onkeydown="otpKeydown(event,0)">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-1" oninput="otpInput(this,1)" onkeydown="otpKeydown(event,1)">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-2" oninput="otpInput(this,2)" onkeydown="otpKeydown(event,2)">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-3" oninput="otpInput(this,3)" onkeydown="otpKeydown(event,3)">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-4" oninput="otpInput(this,4)" onkeydown="otpKeydown(event,4)">
                    <input class="otp-cell" type="number" maxlength="1" id="otp-5" oninput="otpInput(this,5)" onkeydown="otpKeydown(event,5)">
                </div>

                <button
                    type="button"
                    id="btn-verify"
                    onclick="verifyOtp()"
                    class="w-full bg-[#295DA8] text-sm font-bold text-white py-2 rounded hover:bg-blue-700 transition">
                    <span class="btn-text">Verify &amp; Create Account</span>
                    <div class="spinner"></div>
                </button>

                <div class="text-center text-sm text-gray-400 mt-4">
                    Didn't receive the code?
                    <button id="resend-btn" onclick="resendOtp()" disabled
                        class="text-[#295DA8] font-semibold disabled:text-gray-400 disabled:cursor-not-allowed bg-transparent border-none cursor-pointer text-sm font-sans">
                        Resend OTP <span id="resend-timer"></span>
                    </button>
                </div>

            </div>

        </div>
    </div>

</body>

<script>
    let maskedEmail = '';
    let resendInterval = null;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function showAlert(msg, type = 'error') {
        const el    = document.getElementById(type === 'success' ? 'global-success' : 'global-alert');
        const other = document.getElementById(type === 'success' ? 'global-alert' : 'global-success');
        other.classList.remove('show');
        el.textContent = msg;
        el.classList.add('show');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlerts() {
        document.getElementById('global-alert').classList.remove('show');
        document.getElementById('global-success').classList.remove('show');
    }

    function clearFieldErrors() {
        document.querySelectorAll('.field-error').forEach(e => { e.textContent = ''; e.classList.remove('show'); });
        document.querySelectorAll('.form-input').forEach(e => e.style.removeProperty('--tw-ring-color'));
    }

    function showFieldErrors(errors) {
        Object.entries(errors).forEach(([field, messages]) => {
            const errEl = document.getElementById('err-' + field);
            const input = document.getElementById(field);
            if (errEl) { errEl.textContent = messages[0]; errEl.classList.add('show'); }
            if (input)  { input.style.setProperty('--tw-ring-color', '#ef4444'); }
        });
    }

    function setLoading(btnId, loading) {
        const btn = document.getElementById(btnId);
        btn.disabled = loading;
        btn.classList.toggle('btn-loading', loading);
    }

    /* ── Step 1: Send OTP ── */
    async function sendOtp() {
        hideAlerts();
        clearFieldErrors();
        setLoading('btn-send-otp', true);

        const payload = {
            name:                  document.getElementById('name').value.trim(),
            email:                 document.getElementById('email').value.trim(),
            phone:                 document.getElementById('phone').value.trim(),
            company_name:          document.getElementById('company_name').value.trim(),
            password:              document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value,
            ref_code:              document.getElementById('ref_code').value,
        };

        try {
            const res  = await fetch('/register/send-otp', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                if (data.errors) {
                    showFieldErrors(data.errors);
                    showAlert('Please fix the errors below.', 'error');
                } else {
                    showAlert(data.message || 'Something went wrong.', 'error');
                }
                setLoading('btn-send-otp', false);
                return;
            }

            maskedEmail = data.email;
            switchToOtp();

        } catch (e) {
            showAlert('Network error. Please try again.', 'error');
            setLoading('btn-send-otp', false);
        }
    }

    /* ── Switch to OTP step ── */
    function switchToOtp() {
        document.getElementById('step-form').style.display = 'none';
        document.getElementById('step-otp').style.display  = 'block';
        document.getElementById('otp-email-display').textContent = maskedEmail;
        document.getElementById('header-title').textContent = 'Verify Your Email';
        hideAlerts();
        setLoading('btn-send-otp', false);
        startResendTimer(60);
        setTimeout(() => document.getElementById('otp-0').focus(), 100);
    }

    function goBack() {
        document.getElementById('step-otp').style.display  = 'none';
        document.getElementById('step-form').style.display = 'block';
        document.getElementById('header-title').textContent = 'Create Account';
        clearOtpCells();
        clearInterval(resendInterval);
        hideAlerts();
    }

    /* ── OTP Cell Logic ── */
    function otpInput(el, idx) {
        const val = el.value.replace(/\D/g, '').slice(-1);
        el.value  = val;
        el.classList.toggle('filled', val !== '');

        if (val && idx < 5) document.getElementById('otp-' + (idx + 1)).focus();

        if (getOtpValue().length === 6) verifyOtp();
    }

    function otpKeydown(e, idx) {
        if (e.key === 'Backspace') {
            const el = document.getElementById('otp-' + idx);
            if (!el.value && idx > 0) {
                const prev = document.getElementById('otp-' + (idx - 1));
                prev.value = '';
                prev.classList.remove('filled');
                prev.focus();
            }
        }
    }

    function getOtpValue() {
        return [0,1,2,3,4,5].map(i => document.getElementById('otp-' + i).value).join('');
    }

    function clearOtpCells(shake = false) {
        for (let i = 0; i < 6; i++) {
            const el = document.getElementById('otp-' + i);
            el.value = '';
            el.classList.remove('filled', 'error-cell');
            if (shake) {
                el.classList.add('error-cell');
                setTimeout(() => el.classList.remove('error-cell'), 500);
            }
        }
        if (!shake) setTimeout(() => document.getElementById('otp-0').focus(), 50);
    }

    /* ── Step 2: Verify OTP ── */
    async function verifyOtp() {
        const otp = getOtpValue();
        if (otp.length < 6) { showAlert('Please enter the complete 6-digit OTP.'); return; }

        hideAlerts();
        setLoading('btn-verify', true);

        try {
            const res  = await fetch('/register/verify', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify({ otp }),
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                clearOtpCells(true);
                setLoading('btn-verify', false);

                if (data.expired) {
                    showAlert(data.message, 'error');
                    setTimeout(goBack, 2500);
                } else {
                    showAlert(data.message || 'Invalid OTP.', 'error');
                    setTimeout(() => document.getElementById('otp-0').focus(), 100);
                }
                return;
            }

            showAlert('Account created! Redirecting…', 'success');
            setTimeout(() => { window.location.href = data.redirect; }, 1200);

        } catch (e) {
            showAlert('Network error. Please try again.', 'error');
            setLoading('btn-verify', false);
        }
    }

    /* ── Resend Timer ── */
    function startResendTimer(seconds) {
        const btn   = document.getElementById('resend-btn');
        const timer = document.getElementById('resend-timer');
        btn.disabled = true;
        clearInterval(resendInterval);

        resendInterval = setInterval(() => {
            timer.textContent = '(' + seconds + 's)';
            seconds--;
            if (seconds < 0) {
                clearInterval(resendInterval);
                timer.textContent = '';
                btn.disabled = false;
            }
        }, 1000);
    }

    async function resendOtp() {
        hideAlerts();
        document.getElementById('resend-btn').disabled = true;

        try {
            const res  = await fetch('/register/resend-otp', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json();

            if (!data.success) {
                showAlert(data.message || 'Could not resend OTP.', 'error');
                if (data.message && data.message.includes('Session expired')) {
                    setTimeout(goBack, 2000);
                }
                return;
            }

            clearOtpCells();
            showAlert('New OTP sent to ' + maskedEmail, 'success');
            startResendTimer(60);

        } catch (e) {
            showAlert('Network error. Please try again.', 'error');
        }
    }
</script>

</html>