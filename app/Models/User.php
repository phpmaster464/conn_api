<?php namespace App\Models;

    use Illuminate\Notifications\Notifiable;
    use Illuminate\Foundation\Auth\User as Authenticatable;
    use App\Models\ModelTrait as ModelTrait;

    class User extends Authenticatable
    {
        use Notifiable, ModelTrait;

        public $timestamps = true;

        /**
         * The attributes that are mass assignable.
         *
         * @var array
         */
        protected $fillable = [
            'name', 'email', 'password',
        ];

        /**
         * The attributes that should be hidden for arrays.
         *
         * @var array
         */
        protected $hidden = [
            'password', 'remember_token',
        ];

        public function isVip()
        {
            return $this->is_vip;
        }
    }
