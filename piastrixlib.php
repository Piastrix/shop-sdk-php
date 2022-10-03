<?php

class PiastrixClientException extends Exception {
    public function __construct($message, $error_code, Exception $previous = null) {
        $this->error_code = $error_code;
        parent::__construct($message, $error_code, $previous);
    }
}

class PiastrixErrorCode {
    static $ExtraFieldsError = 1000;
    static $IpError = 1001;
    static $SignError = 1002;
    static $ShopAmountError = 1003;
    static $ShopCurrencyError = 1004;
    static $StatusError = 1005;
    static $LanguageError = 1006;
    static $AmountTypeError = 1007;
}

class PiastrixTransferAmountType {
    static $ReceiveAmountType = 'receive_amount';
    static $WriteoffAmountType = 'writeoff_amount';
}

class PiastrixWithdrawAmountType {
    static $PsAmountType = 'ps_amount';
    static $ShopAmountType = 'shop_amount';
}

class PiastrixClient {
    function __construct($shop_id, $secret_key, $timeout=10, $url='https://core.piastrix.com/') {
        $this->timeout = $timeout;
        $this->secret_key = $secret_key;
        $this->shop_id = $shop_id;
        $this->url = $url;
    }

    private function sign(&$data, $required_fields) {
        sort($required_fields);
        $sorted_data = array_map(function ($req_field) use ($data) {
            return $data[$req_field];
        }, $required_fields);
        $signed_data = join(":", $sorted_data) . $this->secret_key;
        $data['sign'] = hash('sha256', $signed_data);
    }

    private function post($endpoint, $req_dict) {
        $ch = curl_init(rtrim($this->url, '/' ) . '/' . ltrim( $endpoint, '/' ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_dict));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        return curl_exec($ch);
    }

    private function check_extra_fields_keys($extra_fields, $req_dict){
        foreach($extra_fields as $key => $value) {
            if(array_key_exists($key, $req_dict)) {
                throw new PiastrixClientException("Wrong key in extra_fields. Don't use the same keys as req_dict",
                PiastrixErrorCode::$ExtraFieldsError);
            }
        }
    }


    /**
	 * Check callback method  for invoice and bill
     * @link https://piastrix.docs.apiary.io/#reference/bill,-invoice-callback 
	 * @param array $request_data
     * @param string $remote_ip_address
     * @param float $shop_amount
	 * @param int $shop_currency
	 * @return null
	 * @throws PiastrixClientException if something is wrong
	 */
    public function check_callback($request_data, $remote_ip_address, $shop_amount, $shop_currency) {
        $allowed_ip_address = [
            '87.98.145.206',
            '51.68.53.104',
            '51.68.53.105',
            '51.68.53.106',
            '51.68.53.107',
            '91.121.216.63',
            '37.48.108.180',
            '37.48.108.181'
        ];
        $sign = $request_data['sign'];
        unset($request_data['sign']);
        if (!in_array($remote_ip_address, $allowed_ip_address)){
            throw new PiastrixClientException('IP address is not in allowed IP addresses',
            PiastrixErrorCode::$IpError);
        }
        $required_fields = array_keys(array_filter($request_data, function($v, $k) {
            return !in_array($v, ['', NULL]);
        }, ARRAY_FILTER_USE_BOTH));
        $this->sign($request_data, $required_fields);
        if ($sign !== $request_data['sign']) {
            throw new PiastrixClientException('Wrong sign', PiastrixErrorCode::$SignError);
        }
        if ($shop_amount !== $request_data['shop_amount']) {
            throw new PiastrixClientException('Wrong shop_amount', PiastrixErrorCode::$ShopAmountError);
        }
        if ($shop_currency !== $request_data['shop_currency']) {
            throw new PiastrixClientException('Wrong shop_currency', PiastrixErrorCode::$ShopCurrencyError);
        }
        if ($request_data['status'] !== 'success') {
            throw new PiastrixClientException('Wrong status', PiastrixErrorCode::$StatusError);
        }
    }


    /**
	 * Check shop balance method
     * @link https://piastrix.docs.apiary.io/#reference/1/1
	 * @return array with keys: shop_id, balances, available, currency, hold, frozen
	 */
    public function check_balance() {
        $req_fields = ['shop_id', 'now'];
        $req_dict = [
            'shop_id' => $this->shop_id,
            'now' => (new DateTime())->format('Y-m-d h:i:s.u')
        ];
        $this->sign($req_dict, $req_fields);
        return $this->post('shop_balance', $req_dict);
    }


    /**
	 * Billing for payment - bill method
     * @link https://piastrix.docs.apiary.io/#reference/0/-piastix-bill/0
	 * @param int $payer_currency
	 * @param float $shop_amount
	 * @param int $shop_currency
     * @param string $shop_order_id
	 * @param array|null $extra_fields influencing keys: description, payer_account, failed_url,
     *                                                   success_url, callback_url
	 * @return array with keys: created, id, lifetime, payer_account,
     *               payer_currency, payer_price, shop_amount, shop_currency,
     *               shop_id, shop_order_id, shop_refund, url
	 */
    public function bill($payer_currency, $shop_amount, $shop_currency, $shop_order_id, $extra_fields=NULL) {
        $req_fields = ['payer_currency', 'shop_amount', 'shop_currency', 'shop_id', 'shop_order_id'];
        $req_dict = [
            'payer_currency' => $payer_currency,
            'shop_amount' => $shop_amount,
            'shop_currency' => $shop_currency,
            'shop_id' => $this->shop_id,
            'shop_order_id' => $shop_order_id
        ];
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $req_dict);
            $req_dict = array_merge($req_dict, $extra_fields);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('bill/create', $req_dict);
    }


    /**
	 * Invoice advance calculation - invoice / try method
     * @link https://piastrix.docs.apiary.io/#reference/api-invoice/-invoicetry
	 * @param float $amount
	 * @param int $currency
     * @param string $shop_order_id
     * @param string $payway
	 * @param array|null $extra_fields influencing keys: description
	 * @return array with keys: add_ons_config, email, manual, payer_price,
     *                          paymethod_id, paymethod_name, ps_currency
	 */
    public function invoice_try($amount, $currency, $shop_order_id, $payway, $extra_fields=NULL) {
        $req_fields = ['amount', 'currency', 'shop_id', 'shop_order_id', 'payway'];
        $req_dict = [
            'amount' => $amount,
            'currency' => $currency,
            'payway' => $payway,
            'shop_id' => $this->shop_id,
            'shop_order_id' => $shop_order_id
        ];
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $req_dict);
            $req_dict = array_merge($req_dict, $extra_fields);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('invoice/try', $req_dict);
    }


    /**
	 * Billing for other currencies - invoice method
     * @link https://piastrix.docs.apiary.io/#reference/api-invoice/-invoice
	 * @param float $amount
	 * @param int $currency
     * @param string $shop_order_id
     * @param string $payway
	 * @param array|null $extra_fields influencing keys: description, phone, failed_url,
     *                                                   success_url, callback_url
	 * @return array with keys: data(customerNumber, orderNumber, paymentType, scid,
     *               shopArticleId, shopFailURL, shopId, shopSuccessURL, sum), id, method, url
	 */
    public function invoice($amount, $currency, $shop_order_id, $payway, $extra_fields=NULL) {
        $req_fields = ['amount', 'currency', 'shop_id', 'shop_order_id', 'payway'];
        $req_dict = [
            'amount' => $amount,
            'currency' => $currency,
            'payway' => $payway,
            'shop_id' => $this->shop_id,
            'shop_order_id' => $shop_order_id
        ];
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $req_dict);
            $req_dict = array_merge($req_dict, $extra_fields);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('invoice/create', $req_dict);
    }


    /**
	 * Transfer status request method by shop payment number
     * @link https://piastrix.docs.apiary.io/#reference/piastrix-transfer/transfer
	 * @param string $shop_payment_id
	 * @return array with keys: id, payee_currency, receive_amount,
     *                          shop_currency, shop_payment_id, write_off_amount
	 */
    public function transfer_status($shop_payment_id) {
        $req_fields = ['shop_id', 'now', 'shop_payment_id'];
        $req_dict = [
            'shop_id' => $this->shop_id,
            'now' => (new DateTime())->format('Y-m-d h:i:s.u'),
            'shop_payment_id' => $shop_payment_id
        ];
        $this->sign($req_dict, $req_fields);
        return $this->post('transfer/shop_payment_status', $req_dict);
    }


    /**
	 * Transfer funds from the balance of the store to the user Piastrix - transfer
     * @link https://piastrix.docs.apiary.io/#reference/piastrix-transfer
     * @param float|int $amount (depends on amount_type)
     * @param string $amount_type ("receive_amount" or "writeoff_amount")
     * @param int|string $payee_account (wallet number or email)
     * @param int $payee_currency
     * @param int $shop_currency
     * @param string $shop_payment_id unique payment identifier on the store side
	 * @param array|null $extra_fields influencing keys: description
	 * @return array with keys: balance, id, payee_account, payee_amount, payee_currency,
     *                          shop, shop_currency, write_off_amount
	 * @throws PiastrixClientException if invalid $amount_type for transfer
	 */
    public function transfer($amount, $amount_type, $payee_account, $payee_currency, $shop_currency, $shop_payment_id, $extra_fields=NULL) {
        $req_fields = ['amount', 'amount_type', 'payee_account', 'payee_currency', 'shop_currency', 'shop_id', 'shop_payment_id'];
        $req_dict = [
            'amount' => $amount,
            'amount_type' => $amount_type,
            'payee_account' => $payee_account,
            'payee_currency' => $payee_currency,
            'shop_id' => $this->shop_id,
            'shop_currency' => $shop_currency,
            'shop_payment_id' => $shop_payment_id
        ];
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $req_dict);
            $req_dict = array_merge($req_dict, $extra_fields);
        }
        if (!in_array($amount_type, [PiastrixTransferAmountType::$ReceiveAmountType,
        PiastrixTransferAmountType::$WriteoffAmountType])){
            throw new PiastrixClientException('Wrong amount_type', PiastrixErrorCode::$AmountTypeError);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('transfer/create', $req_dict);
    }


    /**
	 * Preliminary calculation of withdraw / try payout method
     * @link https://piastrix.docs.apiary.io/#reference/-withdraw/withdrawtry
     * @param float|int $amount (depends on amount_type)
     * @param string $amount_type ("ps_amount" or "shop_amount")
     * @param string $payway
	 * @param int $shop_currency
	 * @return array with keys: account_info_config, info, payee_receive,
     *                          ps_currency, shop_currency, shop_write_off
	 * @throws PiastrixClientException if invalid $amount_type for withdraw
	 */
    public function withdraw_try($amount, $amount_type, $payway, $shop_currency) {
        $req_fields = ['amount', 'amount_type', 'payway', 'shop_currency', 'shop_id'];
        $req_dict = [
            'amount' => $amount,
            'amount_type' => $amount_type,
            'payway' => $payway,
            'shop_currency' => $shop_currency,
            'shop_id' => $this->shop_id
        ];
        if (!in_array($amount_type, [PiastrixWithdrawAmountType::$PsAmountType,
        PiastrixWithdrawAmountType::$ShopAmountType])){
            throw new PiastrixClientException('Wrong amount_type', PiastrixErrorCode::$AmountTypeError);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('withdraw/try', $req_dict);
    }


    /**
	 * Withdraw funds in other currencies with the shop balance - withdraw method
     * @link https://piastrix.docs.apiary.io/#reference/-withdraw/-withdraw
     * @param int|string $account 
     * @param float|int $amount depends on amount_type
     * @param string $amount_type ("ps_amount" or "shop_amount")
     * @param string $payway
     * @param int  $shop_currency
     * @param string $shop_payment_id (unique payment identifier on the store side)
     * @param array|null $account_details depends on payway
	 * @param array|null $extra_fields influencing keys: description
	 * @return array with keys: balance, id, payee_receive, ps_currency, shop_currency,
     *                          shop_payment_id, shop_write_off, status
	 * @throws PiastrixClientException if invalid $amount_type for withdraw
	 */
    public function withdraw($account, $amount, $amount_type, $payway, $shop_currency,
     $shop_payment_id, $account_details=NULL, $extra_fields=NULL) {
        $req_fields = ['account', 'amount', 'amount_type', 'payway',
        'shop_currency', 'shop_id', 'shop_payment_id'];
        $req_dict = [
            'account' => $account,
            'amount' => $amount,
            'amount_type' => $amount_type,
            'payway' => $payway,
            'shop_currency' => $shop_currency,
            'shop_id' => $this->shop_id,
            'shop_payment_id' => $shop_payment_id
        ];
        if ($account_details !== NULL) {
            $req_dict['account_details'] = $account_details;
        }
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $req_dict);
            $req_dict = array_merge($req_dict, $extra_fields);
        }
        if (!in_array($amount_type, [PiastrixWithdrawAmountType::$PsAmountType,
        PiastrixWithdrawAmountType::$ShopAmountType])){
            throw new PiastrixClientException('Wrong amount_type', PiastrixErrorCode::$AmountTypeError);
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('withdraw/create', $req_dict);
    }


    /**
	 * Check account method
     * @link https://piastrix.docs.apiary.io/#reference/-withdraw/-checkaccount
	 * @param int|string $account
     * @param float $amount
     * @param string $payway
	 * @param array|null $account_details
	 * @return array with keys: account_info, verified, provider_status, result
	 */
    public function check_account($account, $amount, $payway, $account_details=NULL) {
        $req_fields = ['account', 'amount', 'payway', 'shop_id'];
        $req_dict = [
            'account' => $account,
            'amount' => $amount,
            'payway' => $payway,
            'shop_id' => $this->shop_id
        ];
        if ($account_details !== NULL) {
            $req_dict['account_details'] = $account_details;
        }
        $this->sign($req_dict, $req_fields);
        return $this->post('check_account', $req_dict);
    }


    /**
	 * Payment status request by id - withdraw_id method
     * @link https://piastrix.docs.apiary.io/#reference/-withdraw/id-withdrawid
	 * @param int $withdraw_id
	 * @return array with keys: id, payee_receive, ps_currency, shop_currency,
     *                              shop_payment_id, shop_write_off, status
	 */
    public function withdraw_id($withdraw_id) {
        $req_fields = ['now', 'shop_id', 'withdraw_id'];
        $req_dict = [
            'shop_id' => $this->shop_id,
            'now' => (new DateTime())->format('Y-m-d h:i:s.u'),
            'withdraw_id' => $withdraw_id
        ];
        $this->sign($req_dict, $req_fields);
        return $this->post('withdraw/status', $req_dict);
    }


    /**
	 * Payment status request by shop payment number - shop_payment_id
     * @link  https://piastrix.docs.apiary.io/#reference/-withdraw/-shoppaymentid
	 * @param string $shop_payment_id
	 * @return array with keys: id, payee_receive, ps_currency, shop_currency,
     *                          shop_payment_id, shop_write_off, status
	 */
    public function shop_payment_id($shop_payment_id) {
        $req_fields = ['now', 'shop_id', 'shop_payment_id'];
        $req_dict = [
            'shop_id' => $this->shop_id,
            'now' => (new DateTime())->format('Y-m-d h:i:s.u'),
            'shop_payment_id' => $shop_payment_id
        ];
        $this->sign($req_dict, $req_fields);
        return $this->post('withdraw/shop_payment_status', $req_dict);
    }


    /**
	 * Billing for payment with pay method
     * @link https://piastrix.docs.apiary.io/#introduction/pay/pay
	 * @param float $amount
     * @param int $currency
     * @param string $shop_order_id
     * @param array|null $extra_fields influencing keys: description, payway, payer_account,
     *                                     failed_url, success_url, callback_url
	 * @param string $lang ('ru' or 'en', default='ru')
	 * @return array ($form_data(with keys: amount, shop_id, currency, shop_order_id,
     *              description, sign) and $url)
	 */
    public function pay($amount, $currency, $shop_order_id, $extra_fields=NULL, $lang='ru') {
        if (!in_array($lang, ['ru', 'en'])) {
            throw new PiastrixClientException("$lang is not valid language", PiastrixErrorCode::$LanguageError);
        }
        $req_fields = ['amount', 'currency', 'shop_id', 'shop_order_id'];
        $form_data = [
            'amount' => $amount,
            'currency' => $currency,
            'shop_id' => $this->shop_id,
            'shop_order_id' => $shop_order_id
        ];
        if ($extra_fields !== NULL) {
            $this->check_extra_fields_keys($extra_fields, $form_data);
            $form_data = array_merge($form_data, $extra_fields);
        }
        $this->sign($form_data, $req_fields);
        $url = "https://pay.piastrix.com/$lang/pay";
        return [$form_data, $url];
    }
}

?>
