<?php

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/cadastro', function(Request $request){
    $data = $request->all();

    $validated = Validator::make($data, [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:6|confirmed',
    ]);

    if($validated->fails()){
      return $validated->errors();
    }

    $user = User::create([
          'name' => $data['name'],
          'email' => $data['email'],
          'password' => bcrypt($data['password']),
      ]);
      $user->token = $user->createToken($user->email)->accessToken;
      return $user;
});

Route::post('/login', function(Request $request){
    $data = $request->all();

    $validated = Validator::make($data, [
        'email' => 'required|string|email|max:255',
        'password' => 'required|string'
    ]);

    if($validated->fails()){
      return $validated->errors();
    }

    $dataAttempt = array(
            'email' => $data['email'],
            'password' => $data['password']
        );

    if(Auth::attempt($dataAttempt)){
      $user = auth()->user();
      $user->token = $user->createToken($user->email)->accessToken;
      $user->imagem = asset($user->imagem);
      return $user;
    } else {
      return ['status'=> false];
    }

});

Route::middleware('auth:api')->get('/usuario', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->put('/perfil', function (Request $request) {
    $user = $request->user();
    $data = $request->all();

    if(isset($data['password'])){
      $validated = Validator::make($data, [
          'name' => 'required|string|max:255',
          'email' => ['required','string','email','max:255',Rule::unique('users')->ignore($user->id)],
          'password' => 'required|string|min:6|confirmed',
      ]);
      if($validated->fails()){
        return $validated->errors();
      }
      $user->password = bcrypt($data['password']);

    } else {
      $validated = Validator::make($data, [
          'name' => 'required|string|max:255',
          'email' => ['required','string','email','max:255',Rule::unique('users')->ignore($user->id)]
      ]);
      if($validated->fails()){
        return $validated->errors();
      }
      $user->name = $data['name'];
      $user->email = $data['email'];
    }

    if (isset($data['imagem'])){

        Validator::extend('base64image', function($attribute, $value, $parameters, $validator){
            $explode = explode(',', $value);
            $allow = ['png', 'jpg', 'svg', 'jpeg'];
            $format = str_replace(
                [
                    'data:image/',
                    ';',
                    'base64',
                ],
                [
                    '', '', '',
                ],
                $explode[0]
            );
            // check file format
            if (!in_array($format, $allow)) {
                return false;
            }

            //check base64 format
            if (!preg_match('`^[a-zA-Z0-9+/]+={0,2}$`', $explode[1])) {
                return false;
            }

            return true;

        });

        $validated = Validator::make($data, [
            'imagem' => 'base64image',
        ], ['base64image'=> 'Imagem inválida!']);

        if($validated->fails()){
          return $validated->errors();
        }

        $time = time();
        $diretorioPai = 'perfils';
        $diretorioImagem = $diretorioPai.DIRECTORY_SEPARATOR.'perfil_id'.$user->id;
        $ext = substr($data['imagem'], 11, strpos($data['imagem'], ';') - 11);
        $urlImagem = $diretorioImagem.DIRECTORY_SEPARATOR.$time.'.'.$ext;

        $file = str_replace('data:image/'.$ext.';base64,', '', $data['imagem']);
        $file = base64_decode($file);

        if(!file_exists($diretorioPai)){
            mkdir($diretorioPai, 777);
        }

        if($user->imagem) {
            if(file_exists($user->imagem)){
                unlink($user->imagem);
            }
        }

        if(!file_exists($diretorioImagem)){
            mkdir($diretorioImagem, 777);
        }

        file_put_contents($urlImagem, $file);

        $user->imagem = $urlImagem;

    }

    $user->save();

    $user->imagem = asset($user->imagem);
    $user->token = $user->createToken($user->email)->accessToken;
    return $user;
});
