<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to eSewa…</title>
    <style>
        body { margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; font-family: system-ui, sans-serif; color: #374151; background: #f8fafc; }
        .box { text-align: center; }
        .spinner { width: 32px; height: 32px; margin: 0 auto 16px; border: 3px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        button { margin-top: 14px; background: #4f46e5; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <form id="esewa-form" method="POST" action="{{ $action }}">
        @foreach ($params as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <div class="box">
            <div class="spinner"></div>
            <p>Redirecting you to eSewa to complete your payment…</p>
            <button type="submit">Continue to eSewa</button>
        </div>
    </form>

    <script>
        document.getElementById('esewa-form').submit();
    </script>
</body>
</html>
