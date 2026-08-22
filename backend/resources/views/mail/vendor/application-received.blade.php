<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.vendor_application_received.title') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            direction: rtl;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: #f0b429;
            padding: 32px 40px;
            text-align: center;
        }

        .header img {
            height: 40px;
        }

        .header h1 {
            color: #1a1a1a;
            font-size: 22px;
            margin: 12px 0 0;
        }

        .body {
            padding: 36px 40px;
            color: #333;
            font-size: 15px;
            line-height: 1.8;
        }

        .store-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 16px;
        }

        .highlight-box {
            background: #fffbf0;
            border: 1px solid #f0b429;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .steps {
            padding: 0;
            margin: 20px 0;
            list-style: none;
        }

        .steps li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .steps li:last-child {
            border-bottom: none;
        }

        .step-num {
            background: #f0b429;
            color: #1a1a1a;
            font-weight: 700;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
        }

        .footer {
            background: #f9f9f9;
            padding: 24px 40px;
            text-align: center;
            font-size: 13px;
            color: #999;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ __('mail.vendor_application_received.header') }}</h1>
        </div>
        <div class="body">
            <p class="store-name">{{ __('mail.vendor_application_received.greeting', ['name' => $vendor->name]) }}</p>

            <p>{!! __('mail.vendor_application_received.intro') !!}</p>

            <div class="highlight-box">
                <strong>{{ __('mail.vendor_application_received.details_title') }}</strong><br>
                {{ __('mail.vendor_application_received.store_name_label') }}: <strong>{{ $vendor->store_name }}</strong><br>
                {{ __('mail.vendor_application_received.email_label') }}: <strong>{{ $vendor->email }}</strong><br>
                {{ __('mail.vendor_application_received.submitted_at_label') }}: <strong>{{ now()->format('Y-m-d') }}</strong>
            </div>

            <p>{{ __('mail.vendor_application_received.next_title') }}</p>
            <ul class="steps">
                <li>
                    <span class="step-num">١</span>
                    <span>{!! __('mail.vendor_application_received.step_1') !!}</span>
                </li>
                <li>
                    <span class="step-num">٢</span>
                    <span>{{ __('mail.vendor_application_received.step_2') }}</span>
                </li>
                <li>
                    <span class="step-num">٣</span>
                    <span>{{ __('mail.vendor_application_received.step_3') }}</span>
                </li>
            </ul>

            <p>{{ __('mail.vendor_application_received.contact') }} <a href="mailto:vendors@noon.com"
                    style="color:#f0b429;">vendors@noon.com</a></p>

            <p style="margin-top:24px;">{!! __('mail.vendor_application_received.closing') !!}</p>
        </div>
        <div class="footer">
            {!! __('mail.vendor_application_received.footer_copyright', ['year' => date('Y')]) !!}
        </div>
    </div>
</body>

</html>