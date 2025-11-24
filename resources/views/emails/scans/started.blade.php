<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scheduled scan started</title>
</head>
<body>
    <p>Hello,</p>
    <p>A new ADA compliance scan has been started for <strong>{{ $site->title ?? $site->domain }}</strong>.</p>
    <p>You can follow its progress here:</p>
    <p><a href="{{ $scanUrl }}">{{ $scanUrl }}</a></p>
    <p>Scan ID: {{ $scan->id }}</p>
    <p>Thanks,<br/>{{ config('app.name') }}</p>
</body>
</html>
