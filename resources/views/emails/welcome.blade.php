<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Welcome</title>
</head>

<body>
    <h1>Welcome, {{ $user->name }}!</h1>

    <p>Your account was created with the email address <strong>{{ $user->email }}</strong>.</p>

    @if ($user->email)
    <p>We're glad to have you on board.</p>
    @endif
</body>

</html>