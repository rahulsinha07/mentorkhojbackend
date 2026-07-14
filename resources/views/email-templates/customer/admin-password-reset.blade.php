<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password reset</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Hi {{ $name ?: 'there' }},</p>
    <p>An administrator reset your MentorKhoj account password.</p>
    <p><strong>New password:</strong> {{ $password }}</p>
    <p>Please sign in and change your password from your profile settings as soon as possible.</p>
    <p>— MentorKhoj Team</p>
</body>
</html>
