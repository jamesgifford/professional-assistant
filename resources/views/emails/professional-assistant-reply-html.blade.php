<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
@foreach(explode("\n\n", $responseText) as $paragraph)
    <p style="margin: 0 0 16px 0;">{{ $paragraph }}</p>
@endforeach
<p style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
    &mdash; James Gifford's AI Hiring Assistant<br>
    Chat: <a href="https://jamesgifford.ai" style="color: #6b7280;">jamesgifford.ai</a> &middot; Portfolio: <a href="https://jamesgifford.com" style="color: #6b7280;">jamesgifford.com</a>
</p>
</body>
</html>
