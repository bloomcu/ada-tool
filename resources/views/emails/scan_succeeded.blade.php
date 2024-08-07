<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{$scan->site->organization->title}}: ADA Scan of {{$scan->site->domain}}</title>
</head>
<body>
    <h1>Scan Succeeded</h1>
    <p>{{$scan->site->organization->title}} ADA Scan of {{$scan->site->domain}} Succeeded! <a href="{{$scan->organization->slug}}">View Results</a> </p>
</body>
</html>