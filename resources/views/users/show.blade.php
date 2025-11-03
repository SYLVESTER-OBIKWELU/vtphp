<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .field {
            margin-bottom: 20px;
        }
        .field label {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .field .value {
            font-size: 18px;
            color: #2c3e50;
        }
        .actions {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>User Details</h1>
            
            <div class="field">
                <label>ID</label>
                <div class="value">{{ $user->id }}</div>
            </div>

            <div class="field">
                <label>Name</label>
                <div class="value">{{ $user->name }}</div>
            </div>

            <div class="field">
                <label>Email</label>
                <div class="value">{{ $user->email }}</div>
            </div>

            <div class="field">
                <label>Created At</label>
                <div class="value">{{ $user->created_at }}</div>
            </div>

            <div class="field">
                <label>Updated At</label>
                <div class="value">{{ $user->updated_at }}</div>
            </div>

            <div class="actions">
                <a href="/users" class="btn">← Back to List</a>
                <a href="/users/{{ $user->id }}/edit" class="btn">Edit User</a>
                <form action="/users/{{ $user->id }}" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
