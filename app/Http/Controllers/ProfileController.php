<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CompanyActivity;
use App\Models\SubCategory;
use Hash;
use Auth;

class ProfileController extends Controller
{
    public function profile(){
        $company = Company::where('id',auth()->user()->company_id)->first();
        return view('profile',compact('company'));
    }

    public function profileEdit(){
        return view('profile-edit');
    }

    public function profileSave(Request $request){
        $company = Company::where('id',auth()->user()->company_id)->first();
        $companyuser = CompanyUser::find($request->user_id);
        $companyuser->company_id    = $company->id;
        $companyuser->name          = $request->name;
        $companyuser->role          = $request->designation;
        $companyuser->mobile        = $request->mobile;
        $companyuser->landline      = $request->landline;
        $companyuser->email         = $request->email;
        $companyuser->status        = 1;
        $companyuser->save();
        return redirect()->route('profile')->with('success','Profile has been updated!');
    }

    public function changeDp(){
        $company = Company::where('id',auth()->user()->company_id)->first();
        return view('change-dp',compact('company'));
    } 

    public function changePassword(){
        $company = Company::where('id',auth()->user()->company_id)->first();
        return view('change-password',compact('company'));
    }

    public function updatePassword(Request $request){
        if (!(Hash::check($request->get('current-password'), Auth::user()->password))) {
            // The passwords matches
            return redirect()->back()->with("error","Your current password does not matches with the password.");
        }

        if(strcmp($request->get('current-password'), $request->get('new-password')) == 0){
            // Current password and new password same
            return redirect()->back()->with("error","New Password cannot be same as your current password.");
        }

        $validatedData = $request->validate([
            'current-password' => 'required',
            'new-password' => 'required|string|min:8|confirmed',
        ]);

        //Change Password
        $user = Auth::user();
        $user->password = bcrypt($request->get('new-password'));
        $user->save();

        return redirect()->back()->with("success","Password successfully changed!");
    }

    public function profileCategories(){
        $company = Company::where('id',auth()->user()->company_id)->first();
        $categories = CompanyActivity::where('company_id',$company->id)->pluck('service_id')->toArray();
        $subcategories = SubCategory::whereIn('id',$categories)->get();
        return view('categories',compact('company','subcategories'));
    }


}