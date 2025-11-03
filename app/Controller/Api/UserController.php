<?php

namespace App\Controller\Api;

use Core\Controller;
use Core\Request;
use App\Models\User;
use Core\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 15);
        
        $users = User::orderBy('created_at', 'DESC')
            ->paginate($perPage, $page);
        
        return $this->json($users);
    }

    public function show(Request $request)
    {
        $id = $request->params('id');
        $user = User::findOrFail($id);
        
        return $this->json([
            'data' => $user->toArray()
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validate($request->all(), [
                'name' => 'required|string|min:3|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6'
            ]);

            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $user = User::create($data);

            return $this->json([
                'message' => 'User created successfully',
                'data' => $user->toArray()
            ], 201);
            
        } catch (ValidationException $e) {
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function update(Request $request)
    {
        try {
            $id = $request->params('id');
            $user = User::findOrFail($id);
            
            $data = $this->validate($request->all(), [
                'name' => 'string|min:3|max:255',
                'email' => "email|unique:users,email,{$id}"
            ]);

            $user->update($data);

            return $this->json([
                'message' => 'User updated successfully',
                'data' => $user->toArray()
            ]);
            
        } catch (ValidationException $e) {
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function destroy(Request $request)
    {
        $id = $request->params('id');
        $user = User::findOrFail($id);
        $user->delete();

        return $this->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
