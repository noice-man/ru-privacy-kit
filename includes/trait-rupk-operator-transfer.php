<?php
if (!defined('ABSPATH')) { exit; }
trait RUPK_Operator_Transfer {
    public static function profile(array $c, bool $include_address=false, bool $include_support=false): array {
        $fields=['name','inn','ogrn','email'];
        if ($include_address) { $fields[]='address'; }
        if ($include_support) { $fields[]='support'; }
        $operator=[];
        foreach ($fields as $field) { $operator[$field]=self::optional_text($field,(string)($c['operator'][$field]??'')); }
        return [
            'format'=>self::FORMAT, 'format_version'=>self::FORMAT_VERSION,
            'operator_kind'=>$c['operator_kind'], 'operator'=>$operator,
            // Visibility is a per-site choice. Never export it as an instruction to publish a home address.
        ];
    }
    public static function validate($raw): array|WP_Error {
        if (!is_array($raw) || ($raw['format']??null)!==self::FORMAT || ($raw['format_version']??null)!==self::FORMAT_VERSION) {
            return new WP_Error('format','Это не профиль оператора RU Privacy Kit или версия формата не поддерживается.');
        }
        $kind=$raw['operator_kind']??null;
        if (!is_string($kind) || !in_array($kind,['ip','company','self','person','other'],true)) { return new WP_Error('kind','Неизвестный тип оператора.'); }
        $data=$raw['operator']??null;
        if (!is_array($data) || array_is_list($data)) { return new WP_Error('fields','Неверная структура сведений об операторе.'); }
        foreach (array_keys($data) as $key) {
            if (!in_array($key,['name','inn','ogrn','address','email','support'],true)) { return new WP_Error('unknown','В файле есть неизвестные поля.'); }
        }
        foreach (['name','email'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || trim($data[$required])==='') {
                return new WP_Error('required','В профиле должны быть указаны наименование/ФИО и email для обращений.');
            }
        }
        $output=[];
        foreach ($data as $key=>$value) {
            if (!is_string($value) || strlen($value)>1000) { return new WP_Error('value','В файле обнаружено поле с некорректным значением.'); }
            $clean=in_array($key,['email','support'],true)?sanitize_email($value):sanitize_text_field($value);
            $output[$key]=self::optional_text($key,$clean);
        }
        if (strlen(trim($output['name']))<2 || !is_email($output['email'])) {
            return new WP_Error('identity','Проверьте наименование/ФИО и email оператора в файле.');
        }
        if (!empty($output['support']) && !is_email($output['support'])) { return new WP_Error('support','Некорректный email поддержки.'); }
        if (!empty($output['inn']) && !preg_match('/^\d{10}(?:\d{2})?$/D',$output['inn'])) { return new WP_Error('inn','ИНН должен содержать 10 или 12 цифр.'); }
        if (!empty($output['ogrn']) && !preg_match('/^\d{13}(?:\d{2})?$/D',$output['ogrn'])) { return new WP_Error('ogrn','ОГРН/ОГРНИП должен содержать 13 или 15 цифр.'); }
        return ['operator_kind'=>$kind, 'operator'=>$output];
    }
    public static function export(): void {
        self::guard('rupk_profile_export');
        $cfg=RUPK_Core::config();
        $payload=self::profile($cfg,!empty($_POST['with_address']),!empty($_POST['with_support']));
        $json=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) { wp_die('Не удалось сформировать файл профиля.',500); }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ru-privacy-operator-profile.json"');
        header('Content-Length: '.strlen($json));
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    }
    public static function preview(): void {
        self::guard('rupk_profile_preview');
        $file=$_FILES['operator_file']??null;
        if (!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
            !is_uploaded_file((string)($file['tmp_name']??'')) ||
            (int)($file['size']??0)>65536 || (int)($file['size']??0)<1) {
            self::location('invalid_upload');
        }
        $raw=file_get_contents((string)$file['tmp_name'],false,null,0,65537);
        if (!is_string($raw) || strlen($raw)>65536) { self::location('invalid_upload'); }
        $decoded=json_decode($raw,true);
        $data=self::validate($decoded);
        if (is_wp_error($data)) {
            $field_errors=['inn'=>'invalid_inn','ogrn'=>'invalid_ogrn','identity'=>'invalid_identity',
                'required'=>'invalid_required','support'=>'invalid_support'];
            self::location($field_errors[$data->get_error_code()]??'invalid_format');
        }
        set_transient(self::transient_key(),$data,15*MINUTE_IN_SECONDS);
        self::location('preview');
    }
    public static function apply(): void {
        self::guard('rupk_profile_apply');
        $parsed=get_transient(self::transient_key());
        if (!is_array($parsed) || is_wp_error(self::validate(['format'=>self::FORMAT,'format_version'=>1]+$parsed))) {
            self::location('expired');
        }
        $operator=$parsed['operator'];
        // Safe defaults: address and support are never imported without independent choices.
        if (empty($_POST['import_address'])) { unset($operator['address']); }
        if (empty($_POST['import_support'])) { unset($operator['support']); }
        $v=['operator_kind'=>$parsed['operator_kind'], 'operator'=>$operator];
        if (!empty($_POST['import_visibility']) && !empty($_POST['import_address']) && !empty($parsed['operator']['address'])) {
            // Explicit authorization to show the imported address, not based on untrusted file contents.
            $v['operator_address_public']=true;
        }
        RUPK_Core::save($v,true);
        delete_transient(self::transient_key());
        self::location('imported');
    }
}