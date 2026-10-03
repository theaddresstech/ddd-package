<?php

namespace Src\Domain\User\Http\Controllers\Auth;

use Src\Domain\User\Http\Resources\User\UserResource;
use Src\Infrastructure\Http\AbstractControllers\BaseController as Controller;
use theaddresstechnology\DDD\Traits\Responder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    use Responder;
    /**
     * View Path.
     *
     * @var string
     */
    protected $viewPath = 'user';

    /**
     * Resource Route.
     *
     * @var string
     */
    protected $resourceRoute = 'users';

    /**
     * Domain Alias.
     *
     * @var string
     */
    protected $domainAlias = 'users';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function __invoke(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $key = 'ddd-login:'.hash('sha256', strtolower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts.'], 429)
                ->header('Retry-After', RateLimiter::availableIn($key));
        }

        try{
            $guard = Auth::guard('web');
            $authenticated = $request->hasSession()
                ? $guard->attempt($credentials)
                : $guard->once($credentials);
            if (!$authenticated) {
                RateLimiter::hit($key, 60);
                return response()->json(['message' => 'email or password incorrect!',], 401);
            }

            RateLimiter::clear($key);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
            $user = $guard->user();

            $this->setData('data', $user);

            $this->useCollection(UserResource::class, 'data');
            if ($request->wantsJson()) {
                $this->setData('meta', [
                    'token' => $user->createToken('admin_token')->accessToken, //todo add function to generate token
                ]);
            }
        }
        catch(\Exception $exception){
            report($exception);
            $this->setApiResponse(fn() => response(['message' => 'The request could not be completed.'],Response::HTTP_CONFLICT));
        }
        return $this->response();
    }
}
