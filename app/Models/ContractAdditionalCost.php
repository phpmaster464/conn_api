<?php namespace App\Models;

    class ContractAdditionalCost extends MyModel {

        protected $connection = 'mysql2';

        protected $fillable = ['contract_id', 'contract_payment_id', 'additional_fee_id', 'date_added', 'amount', 'comment', 'user', 'transaction_type'];

        public $timestamps = true;
        
        public function __construct(array $attributes=array())
        {
            parent::__construct($attributes);
        }
    }