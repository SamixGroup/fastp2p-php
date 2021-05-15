<?php

class ERRORS
{

    const THIS_CARD_IS_NOT_SERVICED = -31900;
    const AMOUNT_OF_PAYMENT_IS_LESS_THAN_MINIMUM = -31611;
    const INCORRECT_CARD_NUMBER = -31630;
    const INCORRECT_MY_CARD_NUMBER = -31101;
    const INCORRECT_SMS_CODE = -31103;
    const PROCEDURAL_CENTER_NOT_AVAILABLE = -31624;
    const PAYMENT_IS_OUT_OF_DATE = -31640;
    const NETWORK_ERROR = -50001;
    const JSON_PARSE_ERROR = -32700;
    const INVALID_PARAMS = -32602;

    const MESSAGE = [
        self::THIS_CARD_IS_NOT_SERVICED => "Ushbu karta raqami bilan amaliyot qilib bo'lmaydi",
        self::AMOUNT_OF_PAYMENT_IS_LESS_THAN_MINIMUM => "O'tkazmaning eng kam miqdori 1000 so'm bo'lishi kerak",
        self::INCORRECT_CARD_NUMBER => "Karta raqami noto'g'ri yoki hisobda o'tkazma uchun mablag' yetarli emas.",
        self::INCORRECT_MY_CARD_NUMBER => "Qabul qiluvchining karta raqami xato kiritilgan.",
        self::INCORRECT_SMS_CODE => "SMS kod noto'g'ri kiritildi.",
        self::PROCEDURAL_CENTER_NOT_AVAILABLE => "Protsessing markazi mavjud emas.",
        self::PAYMENT_IS_OUT_OF_DATE => "O'tkazma muddati eskirgan, qayta urinib ko'ring.",
        self::NETWORK_ERROR => "So'rov yuborishdagi xatolik.",
        self::JSON_PARSE_ERROR=>'JSON\'ni tahlil qilishda xatolik',
        self::INVALID_PARAMS=>"Noto'g'ri ma'lumotlar kiritilgan"
    ];
}

class FastP2PError extends Exception
{
    public function __construct($code, $message = null)
    {
        $this->code = $code;
        $this->message = $message ?: ERRORS::MESSAGE[$code];
    }

}


class Cheque
{
    function __construct(string $_id, int $amount)
    {
        $this->id = $_id;
        $this->amount = $amount / 100;
    }
}

class FastP2P
{
    const base_url = 'https://payme.uz/api/fast_p2p.';

    function __construct(string $card_number)
    {
        $this->my_card_number = $card_number;
    }

    protected function post($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);
        if (curl_error($ch)) {
            var_dump(curl_error($ch));
        } else {
            return json_decode($res,true);
        }
        return json_decode($res,true);
    }

    function _make_request($method_url, $params)
    {
        $json = [
            "method" => "fast_p2p." . $method_url,
            "params" => $params
        ];
        $request_url = self::base_url . $method_url;
        try {
            $response = $this->post(
                $request_url,
                $json
            );
            if ($response and isset($response['error'])) throw new FastP2PERROR($response['error']['code'],ERRORS::MESSAGE[$response['error']['code']]?:$response['error']);
            else return $response['result'];

        } catch (Exception $e) {
            throw new FastP2PError(ERRORS:: NETWORK_ERROR, $e->getMessage());
        }
    }

    /**
     * @throws FastP2PError
     */
    function create(int $amount, string $card_number, string $expire): Cheque
    {
        $response = $this->_make_request(
            'create',
            [
                "amount" => $amount * 100,
                "number" => $this->my_card_number,
                "pay_card" => [
                    "number" => $card_number,
                    "expire" => $expire
                ]
            ]
        );
        return new Cheque(
            $response['cheque']['_id'],
            $response['cheque']['amount']
        );
    }

    /**
     * @throws FastP2PError
     */
    function get_pay_code(string $cheque_id): string
    {
        $response = $this->_make_request(
            'get_pay_code',
            [
                "id" => $cheque_id
            ]
        );
        return $response['phone'];
    }

    function pay(string $cheque_id, string $code):Cheque
    {

        $response = $this->_make_request(
            'pay',
            [
                "id" => $cheque_id,
                "code" => $code
            ]
        );
        return new Cheque(
            $response['cheque']['_id'],
            $response['cheque']['amount']
        );
    }
}
