<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use PHLAK\StrGen\Generator as StrGenerator;

class RegisterController extends Controller
{
    public function store(Request $request, Hasher $hasher)
    {
        $data=$request->validate(['email' => 'required|email', 'name' => 'required', 'password' => 'required', 'confirm_password' => 'required']);
        if(User::firstWhere('email',$data['email'])){
            return back()->withErrors(['email' => ["email"=>"Email already exists"]]);
        }
        if($data['password'] !== $data['confirm_password']) {
            return back()->withErrors(['confirm_password' => ["confirm_password"=>"Password doesn't match"]]);
        }
        $data["password"]=$hasher->hash($data['password']);
        
        User::create($data);
        $user=User::firstWhere('email',$data['email']);
        $user->login();
        return redirect('/');
    }
    public function create(){
        $generator=new StrGenerator();
        $token=$generator->alphaNumeric(32);
        session()->set('_token',$token);
        return view('auth/register',['token'=>$token]);
    }
}
