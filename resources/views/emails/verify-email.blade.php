<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Verify your email address</title>
</head>

<body>
    <h1>Hi {{ $user->name }},</h1>

    <p>Please click the button below to verify your email address.</p>

    <p><a href="{{ $url }}">Verify Email Address</a></p>

    <p>If you did not create an account, no further action is required.</p>
</body>

</html>