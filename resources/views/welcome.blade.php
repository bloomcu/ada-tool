<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel Starter</title>
        <link rel="stylesheet" href="/css/frontend/style.min.css">
    </head>
    <body class="antialiased">


        
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            @if (Route::has('login'))
                <div class="hidden fixed top-0 right-0 px-6 py-4 sm:block">
                    @auth
                        <a href="{{ url('/home') }}" class="text-sm text-gray-700 underline">Home</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-gray-700 underline">Log in</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ml-4 text-sm text-gray-700 underline">Register</a>
                        @endif
                    @endauth
                </div>
            @endif
            <table class="table table--expanded@xs position-relative z-index-1 width-100% text-unit-em text-sm js-table" aria-label="Table Example">
                <thead class="table__header">
                  <tr class="table__row">
                    <th class="table__cell text-left" scope="col">Name</th>
                    <th class="table__cell text-left" scope="col">Job</th>
                    <th class="table__cell text-right" scope="col">Salary</th>
                  </tr>
                </thead>
                
                <tbody class="table__body">
                  @foreach($site as $test)
                    @foreach($test->evaluations[0]->pages as $page)
                    <tr class="table__row">
                      <td class="table__cell" role="cell">
                        <span class="table__label" aria-hidden="true">Name:</span> {{$page->title}}
                      </td>
                      <td class="table__cell" role="cell">
                        <span class="table__label" aria-hidden="true">Job:</span> {{$page->results['eval_url']}}
                      </td>
                      <td class="table__cell" role="cell">
                        {{-- ruleset_title
                        [5] => ruleset_abbrev
                        [6] => ruleset_version
                        [7] => eval_url_encoded
                        [8] => markup_information --}}
                        <span class="table__label" aria-hidden="true">Job:</span> {{$page->results['ruleset_id']}}
                      </td>
                      <td class="table__cell" role="cell">
                        <br>{{$page->results['ruleset_title']}}<br>
                        @foreach($page->results['rule_results'] as $result)
                          <table class="table table--expanded@xs position-relative z-index-1 width-100% text-unit-em text-sm js-table" >
                            <tr class="table__row">
                              <td class="table__cell" role="cell">Rule ID</td>
                              <td class="table__cell" role="cell">Pass</td>
                              <td class="table__cell" role="cell">Fail</td>
                              <td class="table__cell" role="cell">Warning</td>
                              <td class="table__cell" role="cell">Hidden</td>
                            </tr>
                            <tr class="table__row">
                              <td class="table__cell" role="cell">{{$result['rule_id']}}</td>
                              <td class="table__cell" role="cell">{{$result['elements_passed']}}</td>
                              <td class="table__cell" role="cell">{{$result['elements_failure']}}</td>
                              <td class="table__cell" role="cell">{{$result['elements_warning']}}</td>
                              <td class="table__cell" role="cell">{{$result['elements_hidden']}}</td>
                            </tr>
                          </table>
                        @endforeach
                        
                      </td>
                      <td class="table__cell" role="cell">
                        <pre>{{print_r(array_keys($page->results))}}</pre>
                        {{-- <span class="table__label" aria-hidden="true">Job:</span>  --}}
                      </td>
                
                  
                    </tr>
                      
                      
                    @endforeach
                  @endforeach
        </div>
    </body>
    <script src="/js/frontend/scripts.js"></script>
</html>
