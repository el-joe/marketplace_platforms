<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.carrier_supervisor_invite.title') }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f9fafb; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #0f766e; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 36px 40px; color: #374151; line-height: 1.7; }
        .cred-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px 24px; margin: 24px 0; }
        .cred-box p { margin: 4px 0; font-size: 15px; }
        .cred-box strong { color: #065f46; }
        .footer { padding: 20px 40px; background: #f3f4f6; color: #9ca3af; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ __('mail.carrier_supervisor_invite.header', ['company' => $company->name]) }}</h1>
    </div>
    <div class="body">
        <p>{{ __('mail.carrier_supervisor_invite.greeting', ['name' => $supervisor->name]) }}</p>
        <p>
            {!! __('mail.carrier_supervisor_invite.body', ['company' => $company->name]) !!}
        </p>
        <div class="cred-box">
            <p><strong>{{ __('mail.carrier_supervisor_invite.email_label') }}</strong> {{ $supervisor->email }}</p>
            <p><strong>{{ __('mail.carrier_supervisor_invite.password_label') }}</strong> {{ $temporaryPassword }}</p>
        </div>
        <p>{{ __('mail.carrier_supervisor_invite.change_password') }}</p>
        <p>{{ __('mail.carrier_supervisor_invite.footer_disclaimer') }}</p>
    </div>
    <div class="footer">
        {!! __('mail.carrier_supervisor_invite.footer_copyright', ['year' => date('Y')]) !!}
    </div>
</div>
</body>
</html>
