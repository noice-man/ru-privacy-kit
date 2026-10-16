<?php
if (!defined('ABSPATH')) { exit; }
/** Portable operator identity. Site-specific purposes, analytics, policies and secrets never cross sites. */
final class RUPK_Operator_Profile {
    public const FORMAT = 'rupk-operator-profile';
    public const FORMAT_VERSION = 1;
    public static function init(): void {
        add_action('admin_post_rupk_profile_export', [self::class,'export']);
        add_action('admin_post_rupk_profile_preview', [self::class,'preview']);
        add_action('admin_post_rupk_profile_apply', [self::class,'apply']);
    }
    private static function guard(string $action): void {
        if (!current_user_can('manage_options')) { wp_die('Недостаточно прав для изменения реквизитов оператора.',403); }
        check_admin_referer($action);
    }
    private static function location(string $code): void {
        wp_safe_redirect(add_query_arg('rupk_profile_status',$code,admin_url('admin.php?page=rupk-operator')));
        exit;
    }
    private static function transient_key(): string { return 'rupk_operator_import_'.get_current_user_id(); }
    public static function profile(array $c, bool $include_address=false, bool $include_support=false): array {
        $fields=['name','inn','ogrn','email'];
        if ($include_address) { $fields[]='address'; }
        if ($include_support) { $fields[]='support'; }
        $operator=[];
        foreach ($fields as $field) { $operator[$field]=(string)($c['operator'][$field]??''); }
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
            $output[$key]=in_array($key,['email','support'],true)?sanitize_email($value):sanitize_text_field($value);
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
        if (is_wp_error($data)) { self::location('invalid_format'); }
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
        if (!empty($_POST['import_visibility']) && !empty($_POST['import_address']) && isset($parsed['operator']['address'])) {
            // Explicit authorization to show the imported address, not based on untrusted file contents.
            $v['operator_address_public']=true;
        }
        RUPK_Core::save($v,true);
        delete_transient(self::transient_key());
        self::location('imported');
    }
    public static function screen(): void {
        if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.',403); }
        $c=RUPK_Core::config();
        $status=isset($_GET['rupk_profile_status'])?sanitize_key(wp_unslash($_GET['rupk_profile_status'])):'';
        $messages=[
            'imported'=>['success','Профиль импортирован. Публичные документы, созданные плагином, сразу отображают обновлённые реквизиты.'],
            'invalid_upload'=>['error','Выберите JSON-файл размером до 64 КБ.'],
            'invalid_format'=>['error','Файл не прошёл проверку: нужен JSON-профиль оператора RU Privacy Kit с корректными реквизитами.'],
            'expired'=>['error','Предварительный просмотр устарел. Загрузите файл заново.'],
        ];
        echo '<div class="wrap rupk-admin"><header class="rupk-admin-head"><div><h1>Профиль оператора</h1><p>Один профиль для нескольких сайтов. Счётчики, cookies, условия оферты и настройки сайтов не переносятся.</p></div><span class="rupk-version">RU Privacy Kit '.esc_html(RUPK_VERSION).'</span></header>';
        if (isset($messages[$status])) { echo '<div class="notice notice-'.esc_attr($messages[$status][0]).' inline"><p>'.esc_html($messages[$status][1]).'</p></div>'; }
        $kind=['ip'=>'Индивидуальный предприниматель','company'=>'Юридическое лицо','self'=>'Самозанятый','person'=>'Физическое лицо','other'=>'Иное'];
        echo '<div class="rupk-panel"><h2>Текущие данные</h2><p><strong>'.esc_html($kind[$c['operator_kind']]??$c['operator_kind']).'</strong> · '.esc_html($c['operator']['name']).'</p><p>Контакт для обращений: '.esc_html($c['operator']['email']).'</p>';
        echo '<p>Адрес в публичных документах: <strong>'.(!empty($c['operator_address_public'])?'показывается':'скрыт').'</strong>. Изменить видимость и реквизиты можно на <a href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=1')).'">первом шаге мастера</a>.</p></div>';
        echo '<div class="rupk-panel"><h2>Экспорт — скачать профиль</h2><p>JSON сохранится на вашем компьютере. По умолчанию домашний/почтовый адрес и email поддержки не экспортируются.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rupk_profile_export">'; wp_nonce_field('rupk_profile_export');
        self::check('with_address','Включить адрес (чувствительные сведения)',false);
        self::check('with_support','Включить email поддержки',false);
        submit_button('Скачать профиль JSON','primary','submit',false); echo '</form></div>';
        echo '<div class="rupk-panel"><h2>Импорт — загрузить профиль</h2><p>Плагин покажет сравнение до применения. Файл не загружается во внешние сервисы.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rupk_profile_preview">'; wp_nonce_field('rupk_profile_preview');
        echo '<label class="rupk-field"><span>JSON-профиль</span><input type="file" name="operator_file" accept=".json,application/json" required></label>';
        submit_button('Проверить файл','secondary','submit',false); echo '</form>';
        $import=get_transient(self::transient_key());
        if (is_array($import)) {
            $labels=['name'=>'Оператор','inn'=>'ИНН','ogrn'=>'ОГРН/ОГРНИП','email'=>'Email для обращений','address'=>'Адрес','support'=>'Email поддержки'];
            echo '<h3>Проверка перед импортом</h3><table class="widefat striped"><thead><tr><th>Поле</th><th>Сейчас</th><th>Из файла</th></tr></thead><tbody>';
            echo '<tr><td>Тип оператора</td><td>'.esc_html($kind[$c['operator_kind']]??$c['operator_kind']).'</td><td>'.esc_html($kind[$import['operator_kind']]??$import['operator_kind']).'</td></tr>';
            foreach ($import['operator'] as $k=>$value) {
                echo '<tr><td>'.esc_html($labels[$k]??$k).'</td><td>'.esc_html($c['operator'][$k]??'').'</td><td>'.esc_html($value).'</td></tr>';
            }
            echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rupk_profile_apply">'; wp_nonce_field('rupk_profile_apply');
            if (isset($import['operator']['address'])) { self::check('import_address','Импортировать адрес (по умолчанию сохраняется адрес текущего сайта)',false); self::check('import_visibility','Также разрешить публикацию импортированного адреса',false); }
            if (isset($import['operator']['support'])) { self::check('import_support','Заменить email поддержки',false); }
            echo '<p>После подтверждения обновятся только выбранные реквизиты. На опубликованных страницах, созданных плагином, изменения видны сразу. Ручные страницы не меняются.</p>';
            submit_button('Применить профиль','primary','submit',false);
            echo '</form>';
        }
        echo '</div></div>';
    }
    private static function check(string $name,string $label,bool $checked): void {
        echo '<label class="rupk-check"><input type="checkbox" name="'.esc_attr($name).'" value="1"'.checked($checked,true,false).'><span><strong>'.esc_html($label).'</strong></span></label>';
    }
}