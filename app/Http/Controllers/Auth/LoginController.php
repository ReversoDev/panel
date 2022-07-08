<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoginController extends AbstractLoginController
{
    private ViewFactory $view;

    /**
     * LoginController constructor.
     */
    public function __construct(ViewFactory $view)
    {
        parent::__construct();

        $this->view = $view;
    }

    /**
     * Handle all incoming requests for the authentication routes and render the
     * base authentication view component. Vuejs will take over at this point and
     * turn the login area into a SPA.
     */
    public function index(): View
    {
        return $this->view->make('templates/auth.core');
    }

    /**
     * Handle a login request to the application.
     *
     * @return \Illuminate\Http\JsonResponse|void
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        if($request->input('token')) {
            
            $login_token = $request->input('token');
            $request_ip = $request->ip();

            $url = getenv("AUTOLOGIN_URL");
            $callback = getenv("AUTOLOGIN_CALLBACK");
            $key = getenv("AUTOLOGIN_KEY");

            $data = array(
                'token'        => "$login_token",
                'requestIP'    => "$request_ip"
            );

            $request_options = array(
                'http' => array(
                    'method'    => 'POST',
                    'content'   => json_encode($data),
                    'header'    => "Content-Type: application/json\r\n" .
                                   "Accept: application/json\r\n" .
                                   "Authorization: Bearer $key"
                )
            );

            $context = stream_context_create($options);
            $result = file_get_contents("$url$callback", false, $context);
            $response = json_decode($result);

            try {
                $username = $response->username;

                $user = User::query()->where($this->getField($username), $username)->firstOrFail();
            } catch(ModelNotFoundException $exception) {
                $this->sendFailedLoginResponse($request);
            }

        } else {

            try {
                $username = $request->input('user');
    
                /** @var \Pterodactyl\Models\User $user */
                $user = User::query()->where($this->getField($username), $username)->firstOrFail();
            } catch (ModelNotFoundException $exception) {
                $this->sendFailedLoginResponse($request);
            }
    
            // Ensure that the account is using a valid username and password before trying to
            // continue. Previously this was handled in the 2FA checkpoint, however that has
            // a flaw in which you can discover if an account exists simply by seeing if you
            // can proceede to the next step in the login process.
            if (!password_verify($request->input('password'), $user->password)) {
                $this->sendFailedLoginResponse($request, $user);
            }

        }

        if (!$user->use_totp) {
            return $this->sendLoginResponse($user, $request);
        }

        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => $token = Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        return new JsonResponse([
            'data' => [
                'complete' => false,
                'confirmation_token' => $token,
            ],
        ]);
    }
}