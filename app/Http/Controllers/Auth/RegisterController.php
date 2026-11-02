<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AuthServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Rules\AllowedEmailDomains;
use App\Rules\IsEmailDelivrable;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rahul900day\Captcha\Facades\Captcha;
use Rahul900day\Captcha\Rules\Captcha as CaptchaRule;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $redirectRoute = route('feed');
        if(getSetting('site.redirect_page_after_register') && getSetting('site.redirect_page_after_register') == 'settings'){
            $redirectRoute = route('my.settings');
        }
        $this->redirectTo = $redirectRoute;
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {

        $additionalRules = [];
        if(getSetting('security.captcha_driver') !== 'none'){
            $additionalRules = [
                Captcha::getResponseName() => [
                    'required',
                    new CaptchaRule(),
                ],
            ];
        }

        $emailValidationRule = ['required', 'string', 'email', 'max:255', 'unique:users', new AllowedEmailDomains];
        if(getSetting('security.enforce_email_valid_check') && getSetting('security.email_abstract_api_key')){
            $emailValidationRule[] = new IsEmailDelivrable;
        }

        // If abstract api enabled, check if email is delivrable
        return Validator::make(
            $data,
            array_merge([
            'name' => ['required', 'string', 'max:100'],
            'email' => $emailValidationRule,
            'password' => ['min:8', 'required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'min:8'],
            'terms' => ['required'],
            ], $additionalRules)
        );
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     * @return \App\Model\User
     */
    protected function create(array $data)
    {
        return AuthServiceProvider::createUser($data);
    }

    /**
     * The user has been registered.
     *
     * @param  Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        $profileUrl = $request->session()->pull('registrationProfileUrl');
        if ($profileUrl && getSetting('site.enforce_email_validation') && !$user->hasVerifiedEmail()) {
            $request->session()->put('verificationProfileUrl', [
                'user_id' => $user->id,
                'url' => $profileUrl,
            ]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Register successful.']);
        }

        if ($profileUrl) {
            return redirect($profileUrl);
        }
    }
}
