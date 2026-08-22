<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.vendor_team_member_invite.title') }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f9fafb;
            margin: 0;
            padding: 0;
            direction: rtl;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .header {
            background: #4f46e5;
            padding: 32px 40px;
            text-align: center;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 22px;
        }

        .body {
            padding: 40px;
            color: #374151;
            line-height: 1.7;
        }

        .body p {
            margin: 0 0 16px;
        }

        .cred-box {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 24px 0;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .cred-label {
            color: #6b7280;
        }

        .cred-value {
            font-weight: bold;
            color: #111827;
            letter-spacing: .5px;
        }

        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            margin: 8px 0;
        }

        .footer {
            padding: 24px 40px;
            background: #f9fafb;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }

        .warning {
            background: #fef3c7;
            border-right: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            color: #92400e;
            margin: 16px 0;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ __('mail.vendor_team_member_invite.header') }}</h1>
        </div>

        <div class="body">
            <p>{{ __('mail.vendor_team_member_invite.greeting', ['name' => $member->name]) }}</p>
            <p>
                {{ __('mail.vendor_team_member_invite.body', ['role' => $member->role === 'vendor_manager' ? __('mail.vendor_team_member_invite.role_manager') : __('mail.vendor_team_member_invite.role_staff')]) }}
            </p>

            <div class="cred-box">
                <div class="cred-row">
                    <span class="cred-label">{{ __('mail.vendor_team_member_invite.email_label') }}</span>
                    <span class="cred-value">{{ $member->email }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">{{ __('mail.vendor_team_member_invite.password_label') }}</span>
                    <span class="cred-value">{{ $temporaryPassword }}</span>
                </div>
            </div>

            <div class="warning">
                {{ __('mail.vendor_team_member_invite.warning') }}
            </div>

            <p style="text-align:center">
                <a href="{{ url(config('app.partner_url', 'https://partner.' . config('app.domain', 'localhost'))) . '/login' }}"
                    class="btn">
                    {{ __('mail.vendor_team_member_invite.login_button') }}
                </a>
            </p>
        </div>

        <div class="footer">
            {{ __('mail.vendor_team_member_invite.footer_disclaimer') }}
            <br>{{ __('mail.vendor_team_member_invite.footer_copyright', ['year' => now()->year]) }}
        </div>
    </div>
</body>

</html>