<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class CompanyUser extends Authenticatable
{
    use SoftDeletes,Notifiable;

    protected $guarded = [''];

    public function company(){
        return $this->belongsTo(Company::class,'company_id','id');
    }
}
