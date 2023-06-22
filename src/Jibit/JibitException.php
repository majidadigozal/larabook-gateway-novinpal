<?php

namespace Larabookir\Gateway\Jibit;
use Larabookir\Gateway\Exceptions\BankException;

class JibitException extends BankException
{
    protected $errorCode;

    public function __construct($errorCode)
    {
        $this->errorCode = @self::ERROR_CODES[$errorCode];

        parent::__construct(@self::ERROR_MESSAGES[$errorCode], $this->errorCode);
    }

    public const ERROR_CODES = array(
        'failed'  => 0,
        'verification_failed' => 1,
        'client.not_active' => 2,
        'amount.is_required' => 3,
        'amount.not_enough' => 4,
        'wage.is_invalid' => 5,
        'amount_plus_wage.max_value_exceeded' => 6,
        'amount_plus_wage.permitted_value_exceeded' => 7,
        'currency.is_required' => 8,
        'callbackUrl.is_required' => 9,
        'callbackUrl.is_invalid' => 10,
        'callbackUrl.max_length' => 11,
        'clientReferenceNumber.is_required' => 12,
        'clientReferenceNumber.duplicated' => 13,
        'payerCardNumber.is_invalid' => 14,
        'payerNationalCode.is_invalid' => 15,
        'userIdentifier.max_length' => 16,
        'payerMobileNumber.is_invalid' => 17,
        'payerMobileNumber.in_blacklist' => 18,
        'payerNationalCode_and_payerMobileNumber.are_required' => 19,
        'description.max_length' => 20,
        'ip.not_trusted' => 21,
        'security.auth_required' => 22,
        'token.verification_failed' => 23,
        'web.invalid_or_missing_body' => 24,
        'server.error' => 25,
        'purchase.not_found' => 26,
        'purchase.invalid_state' => 27,
        'purchase.already_reversed' => 28,
        'purchase.forbidden_card_number' => 29,
    );

    public const ERROR_MESSAGES = array(
        'failed'  => 'تراکنش با خطا مواجه شده است',
        'verification_failed' => 'تایید تراکنش با خطا مواجه شده است',
        'client.not_active' => 'مشتری فعال نیست.',
        'amount.is_required' => 'مقدار خالی است.',
        'amount.not_enough' => 'مقدار نامعتبر است. حداقل مقدار 5000 ریال است.',
        'wage.is_invalid' => 'کارمزد نامعتبر است. حداقل مقدار آن 0 است.',
        'amount_plus_wage.max_value_exceeded' => 'مجموع مقدار و کارمزد بیشتر از 1000000000 ریال است.',
        'amount_plus_wage.permitted_value_exceeded' => 'مجموع مقدار و کارمزد بیشتر از حداکثر مقدار مجاز برای مشتری شما است.',
        'currency.is_required' => 'واحد پول خالی است.',
        'callbackUrl.is_required' => 'callbackUrl خالی است.',
        'callbackUrl.is_invalid' => 'callbackUrl نامعتبر است.',
        'callbackUrl.max_length' => 'callbackUrl از طول مجاز خود عبور میکند. حداکثر طول آن 1024 کاراکتر است.',
        'clientReferenceNumber.is_required' => 'clientReferenceNumber خالی است.',
        'clientReferenceNumber.duplicated' => 'clientReferenceNumber تکراری است.',
        'payerCardNumber.is_invalid' => 'payerCardNumber نامعتبر است.',
        'payerNationalCode.is_invalid' => 'payerNationalCode نامعتبر است.',
        'userIdentifier.max_length' => 'userIdentifier از طول مجاز خود عبور میکند. حداکثر طول آن 50 کاراکتر است.',
        'payerMobileNumber.is_invalid' => 'payerMobileNumber نامعتبر است.',
        'payerMobileNumber.in_blacklist' => 'شماره موبایل پرداخت کننده در لیست سیاه قرار دارد.',
        'payerNationalCode_and_payerMobileNumber.are_required' => 'checkPayerNationalCode برابر true است و payerNationalCode یا payerMobileNumber خالی هستند.',
        'description.max_length' => 'توضیحات از طول مجاز خود عبور میکند. حداکثر طول آن 256 کاراکتر است.',
        'ip.not_trusted' => 'آدرس IP مشتری قابل اعتماد نیست.',
        'security.auth_required' => 'توکن JWT bearer در درخواست حاضر در هدر Authorization وجود ندارد.',
        'token.verification_failed' => 'توکن دسترسی نامعتبر یا منقضی شده است.',
        'web.invalid_or_missing_body' => 'بدنه درخواست یک JSON معتبر نیست.',
        'server.error' => 'خطای داخلی سرور. لطفا مقدار fingerprint را به ما ارسال کنید تا بتوانیم مشکل دقیق را درونی پیگیری کنیم.',
        'purchase.not_found' => 'سفارش یافت نشد.',
        'purchase.invalid_state' => 'وضعیت سفارش نامعتبر است',
        'purchase.already_reversed' => 'سفارش قبلاً برگردانده شده است.',
        'purchase.forbidden_card_number' => 'کارت پرداخت کننده غیر مجاز است',
    );
}
