<?php

namespace App\Http\Controllers;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\User;
//use App\Mail\ChangeEmailVarification;
use Illuminate\Support\Facades\Mail;
use phpDocumentor\Reflection\Types\Null_;

class SettingController extends Controller
{

     /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function frontend()
    {
        return view('settings');
    }

    public function sendVerificationLink(Request $request){
        $this->validate($request, [   
            'current_password' => 'required|min:6|max:20',        
            'email'=>'required|email|max:255|unique:users,email',    
        ]);
        if (!(Hash::check($request->current_password, Auth::user()->password))) {
            $result=array(
                'status'=>false,
                'message'=>'Your current password does not matches with the password you provided. Please try again.'
            );
        }
        else{
            $token=$this->createNewToken();
            $user = Auth::user();
            $user->new_email = $request->email;
            $user->new_email_token = $token;
            if($user->save()){
                if($this->_sendEmail($request->email,$token)){
                    $result=array(
                        'status'=>true,
                        'message'=>'Email varification link has been successfully send.'
                    );
                  }
                  else{
                    $result=array(
                        'status'=>false,
                        'message'=>'Error to email send.'
                    );
                  }   
            }
            else{
                $result=array(
                    'status'=>false,
                    'message'=>'Error Occured.'
                );
            }
           }
        return response()->json($result);
    }

    public function emailUpdate(Request $request,$userid,$token){
        $user = User::findOrFail($userid);
        if ($user->new_email_token==$token && $userid==Auth::user()->id) {
            $user->email = $user->new_email;
            $user->new_email = Null;
            $user->new_email_token = Null;
            if($user->save()){
                $result=array(
                    'status'=>true,
                    'message'=>'Email has been successfully updated.'
                );
                $request->session()->flash('status', $result['message']);
                return redirect('settings');
            }
        }
        else{
            return abort(403,'Invalid or expired Link');
        }

    }
    
    public function changePassword(Request $request){
        $this->validate($request, [   
            'current_password' => 'required|min:6|max:20',        
            'new_password' => 'required|min:6|max:20',
            'confirm_password' => 'required|same:new_password',
        ]);
        if (!(Hash::check($request->current_password, Auth::user()->password))) {
            $result=array(
                'status'=>false,
                'message'=>'Your current password does not matches with the password you provided. Please try again.'
            );
        }
        else{
            $user = Auth::user();
            $user->password = bcrypt($request->new_password);
            if( $user->save()){
                $result=array(
                    'status'=>true,
                    'message'=>"Password changed successfully !",
                    'user'=>Auth::user(),
                );
            }
        }
        return response()->json($result);
    }

    public function _sendEmail($email,$token)
    {
        $details = [
            'new_email'=>$email,
            'token'=>$token,
            'user'=>Auth::user()
         ];
        //  dd($details);
        try {
            //  Mail::to($email)->send(new \App\Mail\ChangeEmailVarification($objEmail));
            $beautymail = app()->make(\Snowfire\Beautymail\Beautymail::class);
            $beautymail->send('emails.new_email_verify', $details, function($message) use ($details)
            {
                $message
                    ->to($details['new_email'])
                    ->subject('Verify your new email address');
            });
             return true; 
        } catch(\Exception $e){
            dd($e->getMessage());
        }
    }

     /**
     * Create a new token for the user.
     *
     * @return string
     */
    public function createNewToken()
    {
        return hash_hmac('sha256',Str::random(40),Auth::user()->password);
    }

}
