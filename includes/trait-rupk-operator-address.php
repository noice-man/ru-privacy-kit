<?php
if (!defined('ABSPATH')) { exit; }
trait RUPK_Operator_Address {
    private static function optional_text(string $key, string $value): string {
        $trimmed=trim($value);
        if (in_array($key,['inn','ogrn','address'],true) &&
            ($trimmed==='' || in_array($trimmed,['-','—','–'],true))) {
            return '';
        }
        return $value;
    }
    public static function save_address(): void {
        self::guard('rupk_profile_address');
        $cfg=RUPK_Core::config();
        $raw=isset($_POST['operator_address']) && is_string($_POST['operator_address'])
            ? wp_unslash($_POST['operator_address']) : '';
        $address=self::optional_text('address',sanitize_text_field($raw));
        if (strlen($address)>500) { self::location('address_too_long'); }
        $visible=!empty($_POST['operator_address_public']);
        if ($visible && $address==='') { self::location('address_required'); }
        RUPK_Core::save([
            'operator'=>['address'=>$address],
            'operator_address_public'=>$visible,
        ],true);
        self::location('address_saved');
    }
}