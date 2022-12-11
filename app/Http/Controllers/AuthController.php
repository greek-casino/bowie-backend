<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Bowie\API\ResponseTrait;
use App\Http\Controllers\Bowie\Games\GamesKernel;

class AuthController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'login', 'logout']]);
        $this->game_kernel = new GamesKernel;
    }
    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return Response
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|string|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);

        $credentials = $request->only(['email', 'password']);

        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['status' => 'error', 'code' => 401, 'message' => 'Incorrect or non-existing credentials entered.'], 401);
        }

        return $this->respondWithToken($token);
    }

     /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
    * Get the balances of user.
    *
    * @return \Illuminate\Http\JsonResponse
    */
    public function balance()
    {
        $user = auth()->user();
        $data = $this->extended_response($user);
        return response()->json($data, 200);
    }


    /**
    * Transfer all inplay balance (external third party) back to internal wallet database
    *
    * @return \Illuminate\Http\JsonResponse
    */
    public function transferToWallet(Request $request)
    {
        $this->game_kernel->transfer_to_wallet($request->currencyId);
        $user = auth()->user();
        $data = $this->extended_response($user);
        return response()->json($data, 200);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['status' => 'success', 'code' => 200, 'message' => 'Successfully logged out.'], 200);
    }

    /**
     * Register new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
     public function register(Request $request)
    {
      //if(User::count() > 0) {
      //   abort(500, "Registration is closed for new registrations. You can re-run the database seed if you need to generate another account.");
      //}

      $this->validate($request, [
          'email' => 'required|email|unique:users|min:1|max:255',
          'password' => 'required|string|min:8|max:255',
      ]);
      try {
          $user = new User;
          $user->name = $request->input('email');
          $user->email = $request->input('email');
          $plainPassword = $request->input('password');
          $user->usd = config('bowie.player_options.start_balance');
          $user->password = app('hash')->make($plainPassword);
          $user->save();
          return response()->json(['status' => 'success', 'code' => 200, 'message' => 'Created user.'], 200);
      } catch (\Exception $e) {
          return response()->json(['status' => 'success', 'code' => 409, 'message' => 'Registration failed. ' . $e->getMessage()], 409);
      }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth()->user(),
            'expires_in' => (config('jwt.ttl') * 60)
        ]);
    }
}
