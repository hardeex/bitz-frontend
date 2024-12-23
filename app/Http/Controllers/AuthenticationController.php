<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AuthenticationController extends Controller
{
    public function register()
    {
        return view('user.register');
    }

    public function userRegisterSubmit(Request $request)
    {
        // Validate incoming data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'role' => 'nullable|string|in:user,admin',
        ]);

        // Prepare the data for the API request
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_confirmation' => $validated['password_confirmation'],
            'role' => $validated['role'] ?? 'user',
        ];

        // Build the full API URL dynamically using the config
        $apiUrl = config('api.base_url') . '/register';
        Log::info('Connecting to API URL: ' . $apiUrl);

        try {
            // Send the POST request to the external API
            $response = Http::post($apiUrl, $data);

            // Log the response for debugging purposes
            Log::info('API Response Status: ' . $response->status());
            Log::info('API Response Body: ' . $response->body());

            // Check if the API request was successful
            if ($response->successful()) {
                $responseData = $response->json();

                // Flash a success message to the session and redirect the user
                return redirect()->route('user.login')->with('success', $responseData['message']);
            } else {
                // Flash an error message to the session if the API failed
                Log::error('API registration failed with status code: ' . $response->status());
                Log::error('API Error Details: ' . $response->body());
                return back()->with('api_error', 'API registration failed. Please try again.');
            }
        } catch (\Exception $e) {
            // Log the exception and return an error message to the user
            Log::error('Exception occurred during API registration: ' . $e->getMessage());
            return back()->with('api_error', 'An error occurred while communicating with the registration service: ' . $e->getMessage());
        }
    }


    // public function redirectToGoogle() {
    //     Log::info('The redirectToGoogle method is called .....');

    //       // Build the full API URL dynamically using the config
    //       $apiUrl = config('api.base_url') . '/auth/google';
    //       Log::info('Connecting to API URL: ' . $apiUrl);
    // }


    public function login()
    {
        return view('user.login');
    }



    // Handle the login submission and connect to the external API
    public function userLoginSubmit(Request $request)
    {
        // Validate the login form data
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:8',
        ]);

        // Prepare the data to send to the API
        $data = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        // Build the full API URL dynamically using the config
        $apiUrl = config('api.base_url') . '/login'; // The /login endpoint of the external API
        Log::info('Connecting to API URL: ' . $apiUrl);

        try {
            // Send the POST request to the external API
            $response = Http::post($apiUrl, $data);

            // Log the response for debugging purposes
            Log::info('API Response Status: ' . $response->status());
            Log::info('API Response Body: ' . $response->body());

            // Check if the API request was successful
            if ($response->successful()) {
                $responseData = $response->json();

                // Assuming the API returns a token and user data
                session([
                    'api_token' => $responseData['data']['authorization']['token'],
                    'user' => $responseData['data']['user'],
                ]);

                // Redirect to the dashboard with a success message
                return redirect()->route('dashboard')->with('success', 'Login successful! Welcome back.');
            } else {
                // If API login failed, show the error message
                return back()->withErrors(['login_error' => $response->json()['message']]);
            }
        } catch (\Exception $e) {
            // Handle any exceptions that occur during the API request
            Log::error('Exception occurred during API login: ' . $e->getMessage());
            return back()->withErrors(['login_error' => 'An error occurred while communicating with the login service: ' . $e->getMessage()]);
        }
    }


    public function logout(Request $request)
    {
        try {
            $token = session('api_token');  // Retrieve the token from the session

            // Check if token exists in session
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid token found'
                ], 400); // Return error if token is missing
            }

            // API URL to invalidate the JWT
            $apiUrl = config('api.base_url') . '/logout';

            // Send the POST request to the external API to invalidate the token
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token // Pass the token as Bearer token
            ])->post($apiUrl);

            Log::info('API Logout Response Status: ' . $response->status());
            Log::info('API Logout Response Body: ' . $response->body());

            if ($response->successful()) {
                // If the API responds with a success status, log out the user locally
                session()->forget('api_token');
                session()->forget('user');

                return redirect()->route('user.login')->with('success', 'Successfully logged out.');
            } else {
                // If the API returns an error, return an appropriate response
                return redirect()->route('dashboard')->withErrors(['logout_error' => 'Logout failed on API side']);
            }
        } catch (\Exception $e) {
            Log::error('Error during logout: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error during logout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }


    public function forgotPassword()
    {
        return view('user.forgot-password');
    }
}
