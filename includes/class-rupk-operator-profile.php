<?php
if (!defined('ABSPATH')) { exit; }
/** Portable operator identity. Site-specific purposes, analytics, policies and secrets never cross sites. */
require_once __DIR__ . '/trait-rupk-operator-address.php';
require_once __DIR__ . '/trait-rupk-operator-transfer.php';
final class RUPK_Operator_Profile {
    use RUPK_Operator_Address, RUPK_Operator_Transfer;
    public const FORMAT = 'rupk-operator-profile';
    public const FORMAT_VERSION = 1;
    public static function init(): void {
        add_action('admin_post_rupk_profile_export', [self::class,'export']);
        add_action('admin_post_rupk_profile_preview', [self::class,'preview']);
        add_action('admin_post_rupk_profile_apply', [self::class,'apply']);
        add_action('admin_post_rupk_profile_address', [self::class,'save_address']);
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
    /** Common placeholders from exported documents are absence of optional data, not bad IDs. */
    public static function screen(): void {
        if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.',403); }
        $c=RUPK_Core::config();
        $status=isset($_GET['rupk_profile_status'])?sanitize_key(wp_unslash($_GET['rupk_profile_status'])):'';
        $messages=[
            'imported'=>['success','Профиль импортирован. Публичные документы, созданные плагином, сразу отображают обновлённые реквизиты.'],
            'invalid_upload'=>['error','Выберите JSON-файл размером до 64 КБ.'],
            'invalid_format'=>['error','Файл не прошёл проверку: нужен JSON-профиль оператора RU Privacy Kit с корректными реквизитами.'],
            'expired'=>['error','Предварительный просмотр устарел. Загрузите файл заново.'],
            'invalid_inn'=>['error','Некорректный ИНН: требуется 10 или 12 цифр. Если ИНН нет, оставьте поле пустым или укажите прочерк.'],
            'invalid_ogrn'=>['error','Некорректный ОГРН/ОГРНИП: требуется 13 или 15 цифр. Если номер не применяется, оставьте поле пустым или укажите прочерк.'],
            'invalid_identity'=>['error','Проверьте ФИО/наименование и email оператора в JSON.'],
            'invalid_required'=>['error','В JSON должны быть заполнены ФИО/наименование и email для обращений.'],
            'invalid_support'=>['error','В JSON некорректный email поддержки.'],
            'address_saved'=>['success','Адрес и его видимость сохранены. Автоматически созданные документы показывают актуальные сведения без перепубликации.'],
            'address_required'=>['error','Чтобы публиковать адрес, сначала заполните его.'],
            'address_too_long'=>['error','Адрес слишком длинный. Укажите адрес длиной до 500 символов.'],
        ];
        echo '<div class="wrap rupk-admin"><header class="rupk-admin-head"><div><h1>Профиль оператора</h1><p>Один профиль для нескольких сайтов. Счётчики, cookies, условия оферты и настройки сайтов не переносятся.</p></div><span class="rupk-version">RU Privacy Kit '.esc_html(RUPK_VERSION).'</span></header>';
        if (isset($messages[$status])) { echo '<div class="notice notice-'.esc_attr($messages[$status][0]).' inline"><p>'.esc_html($messages[$status][1]).'</p></div>'; }
        $kind=['ip'=>'Индивидуальный предприниматель','company'=>'Юридическое лицо','self'=>'Самозанятый','person'=>'Физическое лицо','other'=>'Иное'];
        echo '<div class="rupk-panel"><h2>Текущие данные</h2><p><strong>'.esc_html($kind[$c['operator_kind']]??$c['operator_kind']).'</strong> · '.esc_html($c['operator']['name']).'</p><p>Контакт для обращений: '.esc_html($c['operator']['email']).'</p>';
        echo '<p>Публикация адреса: <strong>'.(!empty($c['operator_address_public'])?'включена':'выключена').'</strong>. Остальные реквизиты можно изменить на <a href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=1')).'">первом шаге мастера</a>.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="rupk_profile_address">';
        wp_nonce_field('rupk_profile_address');
        echo '<label class="rupk-field"><span>Адрес оператора</span><input type="text" name="operator_address" value="'.esc_attr(self::optional_text('address',(string)$c['operator']['address'])).'" maxlength="500" autocomplete="street-address"></label>';
        self::check('operator_address_public','Публиковать адрес оператора в юридических документах',!empty($c['operator_address_public']));
        echo '<p class="description">Для физлица и ИП по умолчанию выключено. Если включить, адрес появится на автоматически созданных страницах сразу после сохранения, без перепубликации. Условия коммерческих сайтов могут требовать отдельной проверки.</p>';
        submit_button('Сохранить адрес и видимость','primary','submit',false);
        echo '</form></div>';
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