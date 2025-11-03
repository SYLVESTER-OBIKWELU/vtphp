<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users List</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .actions {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5568d3;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: #3498db;
        }

        .btn-info:hover {
            background: #2980b9;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Users Management</h1>

        <div class="actions">
            <a href="/" class="btn">← Back to Home</a>
            <a href="/users/create" class="btn">+ Add New User</a>
            <a href="{{ route('users.index') }}" class="btn">Users Index (Named Route)</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($users->count() > 0)
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            {{ diff_for_humans($user->created_at) }}
                            <br>
                            <small style="color: #999;">{{ format_date($user->created_at, 'M d, Y') }}</small>
                        </td>
                        <td>
                            <a href="/users/{{ $user->id }}" class="btn btn-small btn-info">View</a>
                            <a href="/users/{{ $user->id }}/edit" class="btn btn-small">Edit</a>
                            <form action="/users/{{ $user->id }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-small btn-danger"
                                    onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999; padding: 40px;">
                            No users found. <a href="/users/create">Create one?</a>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>