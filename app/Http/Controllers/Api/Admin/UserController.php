<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Register a new user by admin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUser($request);

        // Hash the password before saving
        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return $this->successResponse('User  berhasil ditambahkan!', $user);
    }

    /**
     * Import users from an Excel file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $this->validateImportRequest($request);

        Excel::import(new UsersImport, $request->file('file'));

        return $this->successResponse('Data users berhasil diimpor!');
    }

    /**
     * Validate user registration data.
     *
     * @param Request $request
     * @return array
     */
    protected function validateUser(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username|max:255',
            'password' => 'required|string|min:6',
        ]);
    }

    /**
     * Validate the import request.
     *
     * @param Request $request
     * @return void
     */
    protected function validateImportRequest(Request $request): void
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);
    }

    /**
     * Return a success response.
     *
     * @param string $message
     * @param mixed $data
     * @return JsonResponse
     */
    protected function successResponse(string $message, $data = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 201);
    }
}
