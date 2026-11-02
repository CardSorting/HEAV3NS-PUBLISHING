<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AuthServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Model\User;
use App\Services\TwoFactorAuthenticationService;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Session;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    private TwoFactorAuthenticationService $twoFactorAuthenticationService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(TwoFactorAuthenticationService $twoFactorAuthenticationService)
    {
        $this->twoFactorAuthenticationService = $twoFactorAuthenticationService;
        $this->redirectTo = route('feed');
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, User $user)
    {
        $this->twoFactorAuthenticationService->initialize($user);

        // Updating last active at column if setting is enabled
        if(getSetting('profiles.record_users_last_activity_time')) {
            $user->last_active_at = Carbon::now();
            $user->save();
        }

        if(getSetting('profiles.record_users_last_ip_address')){
            $user->last_ip = request()->ip();
            $user->save();
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Logged in successfully.']);
        }

        if ($user->isBanned()) {
            return redirect()->route('account.suspension');
        }
    }

    /**
     * Redirect the user to the Facebook authentication page.
     */
    public function redirectToProvider(Request $request)
    {
        return Socialite::driver($request->route('provider'))->redirect();
    }

    /**
     * Obtain the user information from Facebook.
     */
    public function handleProviderCallback(Request $request)
    {
        $provider = $request->route('provider');

        try {
            $user = Socialite::driver($provider)->user();
        } catch (RequestException $e) {
            throw new \ErrorException($e->getMessage());
        }
        $providerUserId = $user->getId();

        // Creating the user & Logging in the user
        $userCheck = User::where('auth_provider_id', $providerUserId)->first();
        if($userCheck){
            $authUser = $userCheck;
        }
        else{
            try {
                $authUser = AuthServiceProvider::createUser([
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'auth_provider' => $provider,
                    'auth_provider_id' => $providerUserId,
                ]);
            }
            catch (\Exception $exception) {
                // Redirect to homepage with error
                return redirect(route('home'))->with('error', $exception->getMessage());
            }

        }

        Auth::login($authUser, true);
        $requiresTwoFactorAuthentication = $this->twoFactorAuthenticationService->initialize($authUser);

        if ($requiresTwoFactorAuthentication) {
            return redirect()->route('2fa.index');
        }

        if ($authUser->isBanned()) {
            return redirect()->route('account.suspension');
        }

        $redirectTo = route('feed');
        if (Session::has('lastProfileUrl')) {
            $redirectTo = Session::get('lastProfileUrl');
        }
        return redirect($redirectTo);

    }
}
