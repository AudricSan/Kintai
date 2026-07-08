<?php

declare(strict_types=1);

namespace kintai\Core\Validation;

use kintai\Core\Repositories\LanguageRepositoryInterface;

final class StoreValidator implements ValidatorInterface
{
    public function __construct(
        private readonly LanguageRepositoryInterface $languages,
    ) {}

    public function validate(array $data): ValidationResult
    {
        $errors = [];
        $fields = [
            'code' => $data['code'] ?? '',
            'name' => $data['name'] ?? '',
        ];

        $code = trim($fields['code']);
        $name = trim($fields['name']);

        if ($code === '') {
            $errors[] = __('val_store_code_required');
        } elseif (!preg_match('/^[A-Z0-9_-]{2,20}$/', $code)) {
            $errors[] = __('val_store_code_format');
        }

        if ($name === '') {
            $errors[] = __('val_store_name_required');
        } elseif (mb_strlen($name) > 200) {
            $errors[] = __('val_name_max_length_200');
        }

        $validTypes = ['retail', 'restaurant', 'office', 'warehouse', 'other'];
        $type = $data['type'] ?? 'retail';
        if (!in_array($type, $validTypes, true)) {
            $errors[] = __('val_invalid_store_type');
        }

        $locale = $data['locale'] ?? 'en';
        $activeCodes = array_column($this->languages->findAllActive(), 'code');
        if (!in_array($locale, $activeCodes, true)) {
            $errors[] = __('val_invalid_locale');
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('val_invalid_email');
        }

        if (!empty($data['phone']) && !preg_match('/^[+\-\s\d()]{6,20}$/', $data['phone'])) {
            $errors[] = __('val_invalid_phone');
        }

        $validCurrencies = ['EUR', 'USD', 'JPY', 'GBP', 'CHF'];
        $currency = strtoupper(trim($data['currency'] ?? ''));
        if ($currency !== '' && !in_array($currency, $validCurrencies, true)) {
            $errors[] = __('val_invalid_currency');
        }

        return new ValidationResult($errors === [], $errors);
    }

    public function validateRole(string $role, array $allowedRoles): ValidationResult
    {
        $errors = [];
        if (!array_key_exists($role, $allowedRoles)) {
            $errors[] = __('val_invalid_role_short');
        }
        return new ValidationResult($errors === [], $errors);
    }
}
