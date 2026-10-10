<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.new_vendor_application_admin.title') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
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
            background: #1a1a2e;
            padding: 32px 40px;
            text-align: center;
        }

        .header h1 {
            color: #f0b429;
            font-size: 20px;
            margin: 0;
        }

        .body {
            padding: 36px 40px;
            color: #333;
            font-size: 15px;
            line-height: 1.8;
        }

        .highlight-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .highlight-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .highlight-box td {
            padding: 6px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .highlight-box td:last-child {
            border-bottom: none;
        }

        .highlight-box td:first-child {
            color: #666;
            width: 40%;
        }

        .highlight-box td:last-child {
            font-weight: 600;
            color: #1a1a1a;
        }

        .cta {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 28px;
            background: #f0b429;
            color: #1a1a1a;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
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
            <h1>{{ __('mail.new_vendor_application_admin.header') }}</h1>
        </div>
        <div class="body">
            <p>{{ __('mail.new_vendor_application_admin.intro') }}</p>

            <div class="highlight-box">
                <table>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.store_name_label') }}</td>
                        <td>{{ $vendor->store_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.business_name_label') }}</td>
                        <td>{{ $vendor->business_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.contact_name_label') }}</td>
                        <td>{{ $vendor->name }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.email_label') }}</td>
                        <td>{{ $vendor->email }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.phone_label') }}</td>
                        <td>{{ $vendor->phone }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('mail.new_vendor_application_admin.submitted_at_label') }}</td>
                        <td>{{ now()->format('Y-m-d H:i') }}</td>
                    </tr>
                </table>
            </div>

            <p>{{ __('mail.new_vendor_application_admin.cta_text') }}</p>
            <a href="{{ route('admin.vendor-applications.show', $vendor->id) }}" class="cta">
                {{ __('mail.new_vendor_application_admin.cta_button') }}
            </a>
        </div>
        <div class="footer">
            {!! __('mail.new_vendor_application_admin.footer_copyright', ['year' => date('Y')]) !!}
        </div>
    </div>
</body>

</html>
