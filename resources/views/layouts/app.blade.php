<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PHP Framework')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        header {
            background: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        nav h1 {
            font-size: 1.5rem;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
        }
        nav a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }
        nav a:hover {
            color: #3498db;
        }
        main {
            min-height: calc(100vh - 200px);
            padding: 2rem 0;
        }
        footer {
            background: #34495e;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
    @yield('styles')
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <h1>{{ config('app.name', 'PHP Framework') }}</h1>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/users">Users</a></li>
                    @auth
                        <li><a href="/logout">Logout</a></li>
                    @endauth
                    @guest
                        <li><a href="/login">Login</a></li>
                    @endguest
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
