<?php

namespace App\Controller;

use Core\Controller;
use Core\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('created_at', 'DESC')->get();
        return $this->view('users.index', compact('users'));
    }

    public function show(Request $request)
    {
        $id = $request->params('id');
        $user = User::findOrFail($id);
        
        return $this->view('users.show', compact('user'));
    }

    public function store(Request $request)
    {
        $data = $this->validate($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        User::create($data);
        
        return $this->redirect('/users');
    }

    public function update(Request $request)
    {
        $id = $request->params('id');
        $user = User::findOrFail($id);
        
        $data = $this->validate($request->all(), [
            'name' => 'string|min:3|max:255',
            'email' => "email|unique:users,email,{$id}"
        ]);

        $user->update($data);
        
        return $this->redirect('/users/' . $id);
    }

    public function destroy(Request $request)
    {
        $id = $request->params('id');
        $user = User::findOrFail($id);
        $user->delete();
        
        return $this->redirect('/users');
    }
}
