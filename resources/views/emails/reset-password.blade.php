<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
</head>

<body>
    <h1>Hi {{ $user->name }},</h1>

    <p>You are receiving this email because we received a password reset request for your account.</p>

    <p><a href="{{ $url }}">Reset Password</a></p>

    <p>This password reset link will expire in 60 minutes.</p>

    <p>If you did not request a password reset, no further action is required.</p>
</body>

</html>