<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    // No constructor needed for middleware, it's handled in api.php

    // 1. List all users
    public function index()
    {
        return response()->json(User::all());
    }

    // 2. Show a single user
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    // 3. Create a new user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'is_seller' => 'nullable|boolean'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_seller' => $request->is_seller ?? false
        ]);

        return response()->json($user, 201);
    }

    // 4. Update an existing user
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'is_seller' => 'nullable|boolean'
        ]);

        if ($request->name) $user->name = $request->name;
        if ($request->email) $user->email = $request->email;
        if ($request->password) $user->password = Hash::make($request->password);
        if (!is_null($request->is_seller)) $user->is_seller = $request->is_seller;

        $user->save();

        return response()->json($user);
    }

    // 5. Delete a user
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
