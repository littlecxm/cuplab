<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Validator;

class AuthenticateController extends ApiController
{
    use ValidatesRequests, RegistersUsers;

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $isSuccess = $this->validator($credentials);
        if ($isSuccess->fails()) {
            //return response()->json(['error' => ['message' => $isSuccess->errors()], 'code' => 400], 400);
            return $this->failed($isSuccess->errors()->first(), 400);
        }
        try {
            // attempt to verify the credentials and create a token for the user
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->failed('验证失败', 401);
            }
        } catch (\Exception $e) {
            // something went wrong whilst attempting to encode the token
            return $this->failed('参数错误', 500);
        }
        // all good so return the token
        return $this->success([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 // config value is in minutes
        ]);
//        return response()->json([
//            'token' => $token,
//            'token_type' => 'bearer',
//            'expires_in' => config('jwt.ttl') * 60 // config value is in minutes
//        ]);

    }

    public function postRegister(Request $request)
    {
        //校验POST数据
        $this->validate($request, [
            'username' => 'required|max:255|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $credentials = request(['username', 'email', 'password']);
        $user = $this->create($credentials);
        //$user->uuid = $this->uuid($user);
        //event(new Registered($user));

        return $this->registered($request, $user) ?: $this->success("激活邮件已发送,\n请先点击邮件内的链接进行账号激活后再登录本系统。");
    }

    private function create(array $data)
    {
        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
        return $user;
    }

    public function refreshToken()
    {
        $token = JWTAuth::getToken();
        if (!$token) {
            //throw new BadRequestHttpException('Token not provided');
            return $this->failed('Token未提供', 401);
        }
        try {
            $token = JWTAuth::refresh($token);
        } catch (TokenInvalidException $e) {
            //throw new AccessDeniedHttpException('The token is invalid');
            return $this->failed('Token无效', 401);
        }
        return $this->success([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 // config value is in minutes
        ]);
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' => 'required|email|max:255',
            'password' => 'required|min:6',
        ]);
    }

}