<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Country;
use App\Models\Region;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\RegistrationToken;
use App\Models\Document;
use App\Models\CompanyDocument;
use App\Models\CompanyActivity;
use App\Models\Category;
use App\Models\SubCategory;
use Session;

class RegisterController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
       
    }

    public function registration(Request $request)
    {
        $request->validate([  
            'name' => 'required',
            'pobox' => 'required',
            'country_id' => 'required',
            'region_id' => 'required',
            'email' =>  'required|unique:company_users,email',
            'phone' => 'required|unique:company_users,mobile|numeric',
        ]);
        $otp = rand(100000, 999999);
        session()->put('registation', [
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->phone,
            'country_id' => $request->country_id,
            'region_id' => $request->region_id,
            'pobox' => $request->pobox,
            'otp' => $otp,
        ]);
        $details = [
            'name' =>  $request->name,
            'otp'=> $otp,
            'welcome'	=>'Welcome to  DialectB2B. ',
            'body' => 'Thank you for being part of our family. Please find the login details for your app',
             
        ];
        try{
           \Mail::to($request->email)->send(new \App\Mail\RegistrationOtpMail($details));
           return redirect()->route('registration.otp');
        }
        catch(Throwable $e){
            return redirect('/');
        }
        
    }

    public function registrationOtp(Request $request){
        $registation = session('registation');
        return view('otp',compact('registation'));
    }

    public function verifyOtp(Request $request){
        $request->validate([  
            'digit1' => 'required|numeric',
            'digit2' => 'required|numeric',
            'digit3' => 'required|numeric',
            'digit4' => 'required|numeric',
            'digit5' => 'required|numeric',
            'digit6' => 'required|numeric',
        ]);
        $count = $request->session()->get('count') ? session()->get('count') : 0; 
        $request->session()->put('count',$count);
        $response = array();
        $registation = session('registation');
        $enteredOtp = $request->digit1.$request->digit2.$request->digit3.$request->digit4.$request->digit5.$request->digit6;
        $OTP = $request->session()->get('OTP');
        if($registation['otp'] == $enteredOtp){
            try{
                \DB::beginTransaction();
                $company = new Company();
                $company->country_id = $registation['country_id'];
                $company->region_id = $registation['region_id'];
                $company->pobox = $registation['pobox'];
                $company->email = $registation['email'];
                $company->phone = $registation['mobile'];
                $company->save();

                $companyuser = new CompanyUser();
                $companyuser->company_id    = $company->id;
                $companyuser->name          = $registation['name'];
                $companyuser->mobile        = $registation['mobile'];
                $companyuser->designation   = "Admin";
                $companyuser->email         = $registation['email'];
                $companyuser->status        = 0;
                $companyuser->save();
                
                Session::forget('count');
                Session::forget('registation');
                $this->otpPassed($company);
                \DB::commit();
                return redirect()->route('registration.success');
            }
            catch (Throwable $e) {
                \DB::rollback();
                return redirect('/')->with('success','Registration failed, Try Again');
            }
        }else{
            $count = $request->session()->get('count') + 1;
            $request->session()->put('count', $count);
            if($count >=3){
                Session::forget('count');
                Session::forget('registation');
                return redirect('/')->with('success','Registration failed, Try Again');
            }
            return redirect()->route('registration.otp')->with('success','Incorrect OTP.Try again!');
        }
       
    }

    public function otpPassed($company)
    {
        $plaintext = Str::random(32);
        $token = new RegistrationToken;
        $token->company_id = $company->id;
        $token->token = hash('sha256', $plaintext);
        $token->expire_at = now()->addDays(7);
        $token->save();
        Session::put('comp_id',$company->id);
        $details = [
            'name' => $company->name ?? 'User',
            'welcome'	=>'Welcome to  DialectB2B. ',
            'body' => 'Thank you for being part of our family. Please find the login details for your app',
            'link'	=> 'registration/'.$token->token,
            'expiry' => $token->expire_at   
       ];
        try{
            \Mail::to($company->email)->send(new \App\Mail\RegistrationMail($details));
        }
        catch(Throwable $e){
            
        }
        //return redirect()->route('registration.otp');
    }

    public function success(){
        return view('success');
    }

    public function registrationProcess($token){
        $data = RegistrationToken::where('token', $token)->firstOrFail();
        if(!$data){
            return redirect('/');
        }
        Session::put('comp_id',$data->company_id);
        return redirect()->route('registration.companyInfo');
    }

    public function companyInfo(Request $request){
        if(!$request->session()->has('comp_id')){
            return redirect('/');
        }
        $id = $request->session()->get('comp_id');
        $company  = Company::find($id);
        return view('step-zero',compact('company'));
    }

    public function saveCompanyInfo(Request $request){
        $company_id = $request->session()->get('comp_id');
        $request->validate([  
            'name' => 'required',
            'address' => 'required',
            'zone' => 'required',
            'street' => 'required',
            'building' => 'required',
            'unit' => 'nullable',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'domain' => 'nullable|unique:companies,domain,'.$company_id,
            'fax' => 'nullable|numeric|unique:companies,fax,'.$company_id,
        ]);

        $imageUrl  = '';
        if($request->hasFile('logo')){
            $imageName = time().'.'.$request->logo->extension();  
            $request->logo->move(public_path('uploads/company_logo'), $imageName);
            $path = asset('uploads/company_logo/');
            $imageUrl = $path.'/'.$imageName;
        }
    
        $company = Company::find($company_id);
        $company->name = $request->name;
        $company->address = $request->address;
        $company->zone = $request->zone;
        $company->street = $request->street;
        $company->building = $request->building;
        $company->unit = $request->unit;
        $company->fax = $request->fax;
        $company->domain = $request->domain;
        $company->logo = $imageUrl;
        $company->status = 0;
        $company->save();

        return redirect()->route('registration.documentUpload')->with('success','Saved!');
    }

    public function documentUpload(Request $request){
        if(!$request->session()->has('comp_id')){
            return redirect('/');
        }
        $id = $request->session()->get('comp_id');
        $company  = Company::find($id);
        $documents  = Document::where('country_id',$company->country_id)->get();
        $companyDocuments = CompanyDocument::with('document')->where('company_id',$id)->get();
        return view('step-two',compact('company','documents','companyDocuments'));
    } 

    public function saveDocument(Request $request){
        $request->validate([  
            'doc_type' =>'required',
            'doc_number' => 'required',
            'doc_file' => 'required|mimes:pdf,jpeg,png,jpg|max:5000',
         ]);
       
         $imageUrl  = '';
         if($request->hasFile('doc_file')){
             $imageName = time().'.'.$request->doc_file->extension();  
             $request->doc_file->move(public_path('uploads/company_document'), $imageName);
             $path = asset('uploads/company_document/');
             $imageUrl = $path.'/'.$imageName;
         }
         
         
         $company_id = $request->session()->get('comp_id');
         $document = new CompanyDocument();
         $document->company_id = $company_id;
         $document->doc_type = $request->doc_type;
         $document->doc_file = $imageUrl;
         $document->expiry_date = $request->expiry_date;
         $document->doc_number = $request->doc_number;
         $document->status = 1;
         $document->save();
         return redirect()->route('registration.selectService');
    }

    public function selectService(Request $request){
        if(!$request->session()->has('comp_id')){
            return redirect('/');
        }
        $id = $request->session()->get('comp_id');
        $company  = Company::find($id);
        $categories  = Category::all();
        $companyActivities = CompanyActivity::where('company_id',$id)->get();
        session()->put('selected', []);
        return view('step-three',compact('company','companyActivities','categories'));
    }

    public function saveCategory(Request $request){

        $company_id = $request->session()->get('comp_id');
        $subcat_id = $request->subcat_id;
        if (session()->has('selected')){
            session()->push('selected', $subcat_id);
        }
        $selected = SubCategory::whereIn('id',session('selected'))->get();
        return response()->json($selected);
    }

    public function companyActivity(Request $request){
        $id = $request->session()->get('comp_id');
        $company  = Company::find($id);
        $companyActivities = SubCategory::whereIn('id',session('selected'))->get();
        return view('company-activities',compact('company','companyActivities'));
    }

    public function saveService(Request $request){
        $id = $request->session()->get('comp_id');
        $companyActivities = SubCategory::whereIn('id',session('selected'))->get();
        foreach($companyActivities as $ca){
            $companyServicesExist = CompanyActivity::where('service_id',$ca)->where('company_id',$id)->first();
            if(!$companyServicesExist){
                $service = new CompanyActivity();
                $service->service_id = $subcategory;
                $service->company_id = $id;
                $service->save();
            }
        }
        $companyServices = CompanyActivity::where('company_id',$id)->get();
        return redirect()->route('registration.paymentUpload');
    }

    public function getCountryById(Request $request){
        $country = Country::where("id",$request->country_id)->first();
        return response()->json($country);
    }

    public function getRegionsByCountryId(Request $request){
        $regions = Region::where("country_id",$request->country_id)
                        ->pluck("name","id");
        return response()->json($regions);
    }

    public function searchCategory(Request $request){
        if($request->keyword != ''){
        $cat = Category::where("name",'like','%'.$request->keyword.'%')->get();
        }
        else{
            $cat = Category::all();
        }
        return $cat->toArray();
    } 
    public function searchAlphaCategory(Request $request){
        if($request->keyword != ''){
        $cat = Category::where("name",'like',$request->keyword.'%')->get();
        }
        else{
            $cat = Category::all();
        }
        return $cat->toArray();
    } 
    public function getSubCategory(Request $request){
        $sql = "SELECT * FROM `sub_categories` WHERE id IN (SELECT subcategory_id FROM category_sub_categories WHERE category_id = $request->cat_id)";
        $services = \DB::select($sql);
        return response()->json($services);
    }


    


}
