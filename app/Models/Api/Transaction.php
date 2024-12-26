<?php

namespace App\Models\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['account_id', 'user_id', 'type', 'amount', 'target_user_id', 'reference_id'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function recipientAccount()
    {
        return $this->belongsTo(Account::class, 'target_user_id', 'user_id');
    }
}
