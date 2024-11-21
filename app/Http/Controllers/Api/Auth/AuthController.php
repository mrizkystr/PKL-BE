<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user dengan username dan password.
     */
    public function login(Request $request)
    {
        try {
            $credentials = $this->validateLogin($request);

            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->errorResponse('Invalid Credentials', 401);
            }

            return $this->successResponse([
                'username' => $request->username,
                'token' => $token,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('An error occurred while processing your request.', 500);
        }
    }

    /**
     * Logout user (invalidate token).
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->successResponse(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return $this->errorResponse('An error occurred while logging out.', 500);
        }
    }

    /**
     * Dapatkan data user yang sedang login.
     */
    public function profile()
    {
        try {
            $user = JWTAuth::user();
            return $this->successResponse($user);
        } catch (\Exception $e) {
            return $this->errorResponse('An error occurred while fetching user profile.', 500);
        }
    }

    /**
     * Validate the login request.
     *
     * @param Request $request
     * @return array
     * @throws ValidationException
     */
    protected function validateLogin(Request $request)
    {
        return $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);
    }

    /**
     * Return a success response.
     *
     * @param mixed $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data, $status = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Return an error response.
     *
     * @param string $message
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($message, $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
