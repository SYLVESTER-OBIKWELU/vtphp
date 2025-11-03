# API Documentation

## Authentication

### Register User

**POST** `/api/register`

**Request Body:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response:** `201 Created`

```json
{
  "message": "User registered successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-01 12:00:00"
  }
}
```

### Login

**POST** `/api/login`

**Request Body:**

```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

**Response:** `200 OK`

```json
{
  "message": "Login successful",
  "token": "abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

## Users API

### Get All Users

**GET** `/api/users`

**Query Parameters:**

- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 15)

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2024-01-01 12:00:00"
    }
  ],
  "total": 100,
  "per_page": 15,
  "current_page": 1,
  "last_page": 7,
  "from": 1,
  "to": 15
}
```

### Get Single User

**GET** `/api/users/{id}`

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-01 12:00:00",
    "updated_at": "2024-01-01 12:00:00"
  }
}
```

### Create User

**POST** `/api/users`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secret123"
}
```

**Response:** `201 Created`

```json
{
  "message": "User created successfully",
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2024-01-01 12:00:00"
  }
}
```

### Update User

**PUT** `/api/users/{id}`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

```json
{
  "name": "Jane Smith",
  "email": "jane.smith@example.com"
}
```

**Response:** `200 OK`

```json
{
  "message": "User updated successfully",
  "data": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane.smith@example.com",
    "updated_at": "2024-01-01 13:00:00"
  }
}
```

### Delete User

**DELETE** `/api/users/{id}`

**Headers:**

```
Authorization: Bearer {token}
```

**Response:** `200 OK`

```json
{
  "message": "User deleted successfully"
}
```

## Error Responses

### Validation Error

**Status:** `422 Unprocessable Entity`

```json
{
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required.",
      "The email must be a valid email address."
    ],
    "password": ["The password must be at least 6 characters."]
  }
}
```

### Not Found

**Status:** `404 Not Found`

```json
{
  "message": "Resource not found",
  "code": 404
}
```

### Unauthorized

**Status:** `401 Unauthorized`

```json
{
  "error": "Unauthorized"
}
```

### Server Error

**Status:** `500 Internal Server Error`

```json
{
  "message": "Internal server error",
  "code": 500
}
```

## Testing with cURL

### Register a new user

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
  }'
```

### Get all users

```bash
curl http://localhost:8000/api/users
```

### Get specific user

```bash
curl http://localhost:8000/api/users/1
```

### Create user (authenticated)

```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "secret123"
  }'
```

### Update user

```bash
curl -X PUT http://localhost:8000/api/users/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "John Smith"
  }'
```

### Delete user

```bash
curl -X DELETE http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Rate Limiting

Currently not implemented. To add rate limiting, create a `RateLimitMiddleware` and apply it to your API routes.

## CORS

CORS is already configured via `CorsMiddleware`. All API routes in `routes/api.php` have CORS enabled by default.

To customize CORS settings, edit `app/Middleware/CorsMiddleware.php`.

## Pagination

All list endpoints support pagination:

- `page`: Current page number
- `per_page`: Items per page (default: 15, max: 100)

Example:

```
GET /api/users?page=2&per_page=20
```

## Filtering & Sorting

You can implement custom filtering in your controllers:

```php
public function index(Request $request)
{
    $query = User::query();

    if ($request->query('status')) {
        $query->where('status', $request->query('status'));
    }

    if ($request->query('sort')) {
        $direction = $request->query('order', 'ASC');
        $query->orderBy($request->query('sort'), $direction);
    }

    $users = $query->paginate(
        $request->query('per_page', 15),
        $request->query('page', 1)
    );

    return $this->json($users);
}
```

Usage:

```
GET /api/users?status=active&sort=created_at&order=DESC&page=1
```
