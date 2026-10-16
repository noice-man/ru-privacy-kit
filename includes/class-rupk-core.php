<?php
if (!defined('ABSPATH')) { exit; }
final class RUPK_Core {
    public const OPTION = 'rupk_config';
    public static function defaults(): array {
        return [
            'schema' => 1, 'complete' => false, 'enabled' => false, 'revision' => 1,
            'analytics_mode' => 'strict', 'mode_changed_at' => '', 'mode_changed_by' => 0,
            'site_kind' => 'information', 'operator_kind' => 'ip',
            'operator' => ['name'=>'','inn'=>'','ogrn'=>'','address'=>'','email'=>get_option('admin_email',''), 'support'=>get_option('admin_email','')],
            'purposes' => ['feedback'],
            'data' => ['hosting'=>'unknown','retention'=>'До достижения целей обработки либо в сроки, предусмотренные законодательством РФ','security'=>'','crossborder'=>'unknown'],
            'services' => ['yandex'=>false,'google'=>false,'sberads'=>false,'gtm'=>false,'vk'=>false,'local'=>false,'captcha'=>false,'video'=>false,'custom'=>false],
            'yandex_id'=>'', 'google_id'=>'', 'sberads_id'=>'', 'vk_id'=>'', 'gtm_id'=>'', 'cookie_inventory'=>'', 'inventory_reviewed'=>false,
            'legal' => ['yandex'=>['basis'=>'','purpose'=>'','details'=>'','confirmed'=>false], 'google'=>['basis'=>'','purpose'=>'','details'=>'','confirmed'=>false], 'sberads'=>['basis'=>'','purpose'=>'','details'=>'','confirmed'=>false]],
            'commercial'=>['paid'=>false,'kind'=>'saas','subject'=>'','price'=>'','accept'=>'','delivery'=>'','refund'=>'','support'=>''],
            'design'=>['layout'=>'bottom','theme'=>'inherit','bg'=>'','text'=>'','accent'=>'','radius'=>14,'floating_link'=>true,'retention_days'=>180],
            'documents'=>['cookie'=>0,'privacy'=>0,'terms'=>0,'offer'=>0],
            'document_revision'=>0, 'documents_review_required'=>true,
            'log_consent'=>false,'log_retention_days'=>180,
            'wizard_step'=>1,
        ];
    }
    public static function config(): array {
        $saved = get_option(self::OPTION, []);
        return self::merge(self::defaults(), is_array($saved) ? $saved : []);
    }
    public static function merge(array $left, array $right): array {
        foreach ($right as $k=>$v) {
            if (!array_key_exists($k,$left)) { continue; }
            $left[$k] = is_array($v) && is_array($left[$k]) ? (array_is_list($left[$k]) ? array_values($v) : self::merge($left[$k],$v)) : $v;
        }
        return $left;
    }
    public static function save(array $new, bool $material = false): void {
        $old = self::config();
        $updated = self::merge($old,$new);
        if ($material) {
            $changed = self::relevant($old) !== self::relevant($updated);
            if ($changed) {
                $updated['revision'] = (int)$old['revision'] + 1;
                $updated['documents_review_required'] = true;
                if (!empty($old['complete'])) { $updated['complete'] = false; $updated['enabled'] = false; }
                if ($old['analytics_mode'] !== $updated['analytics_mode']) {
                    $updated['mode_changed_at'] = current_time('mysql',true);
                    $updated['mode_changed_by'] = get_current_user_id();
                }
            }
        }
        update_option(self::OPTION,$updated,false);
    }
    private static function relevant(array $cfg): string {
        $keys=['analytics_mode','operator_kind','operator','site_kind','purposes','data','services','yandex_id','google_id','sberads_id','vk_id','gtm_id','cookie_inventory','inventory_reviewed','legal','commercial'];
        $out=[];
        foreach ($keys as $k) { $out[$k] = $cfg[$k] ?? null; }
        return wp_json_encode($out);
    }
    public static function active_services(array $c): array {
        $registry=self::registry(); $out=[];
        foreach ($c['services'] as $id=>$on) { if ($on && isset($registry[$id])) { $out[$id]=$registry[$id]; } }
        return $out;
    }
    public static function registry(): array {
        return [
            'yandex'=>['label'=>'Яндекс Метрика','provider'=>'ООО «Яндекс»','category'=>'analytics','policy'=>'https://yandex.ru/legal/confidential/'],
            'google'=>['label'=>'Google Analytics 4','provider'=>'Google','category'=>'analytics','policy'=>'https://policies.google.com/privacy'],
            'sberads'=>['label'=>'Статистика от SberAds (Рамблер/Топ-100)','provider'=>'ООО «РА «Индекс 20»','category'=>'analytics','policy'=>'https://github.com/stat-sberads/documentation'],
            'gtm'=>['label'=>'Google Tag Manager (внешний контейнер)','provider'=>'Google','category'=>'unmanaged','policy'=>'https://policies.google.com/privacy'],
            'vk'=>['label'=>'Пиксель VK Рекламы','provider'=>'VK','category'=>'marketing','policy'=>'https://vk.com/privacy'],
            'local'=>['label'=>'Локальная статистика','provider'=>'Владелец сайта / установленный плагин','category'=>'unmanaged','policy'=>''],
            'captcha'=>['label'=>'Капча / антиспам','provider'=>'Зависит от настройки','category'=>'unmanaged','policy'=>''],
            'video'=>['label'=>'Встраиваемое видео / карты','provider'=>'Зависит от настройки','category'=>'unmanaged','policy'=>''],
            'custom'=>['label'=>'Другие сторонние теги','provider'=>'Зависит от настройки','category'=>'unmanaged','policy'=>''],
        ];
    }
    public static function managed(array $c,string $id): bool {
        if (empty($c['services'][$id])) { return false; }
        if ($id==='yandex') { return preg_match('/^\d{4,12}$/D',(string)$c['yandex_id']) === 1; }
        if ($id==='google') { return preg_match('/^G-[A-Z0-9]{5,20}$/D',(string)$c['google_id']) === 1; }
        if ($id==='sberads') { return preg_match('/^[0-9]{1,20}$/D',(string)$c['sberads_id']) === 1; }
        if ($id==='vk') { return preg_match('/^[0-9]{1,20}$/D',(string)($c['vk_id']??'')) === 1; }
        return false;
    }
    public static function basis_confirmed(array $c,string $id): bool {
        $l=$c['legal'][$id]??[];
        return !empty($l['confirmed']) && in_array($l['basis']??'', ['contract','legitimate','law','other'],true)
            && strlen(trim($l['purpose']??''))>=8 && strlen(trim($l['details']??''))>=12;
    }
    /**
     * Shared validation model for wizard, diagnostics and publication gates.
     * Only "todo" items block publication; "review" items are advisory.
     */
    public static function review_items(array $c): array {
        $items = [];
        $push = static function (string $status, string $title, string $detail, int $step) use (&$items): void {
            $items[] = ['status'=>$status, 'title'=>$title, 'detail'=>$detail, 'step'=>$step];
        };
        if (trim((string)$c['operator']['name'])==='') {
            $push('todo','Укажите владельца сайта','Нужно полное юридическое наименование или ФИО оператора.',1);
        }
        if (!is_email((string)$c['operator']['email'])) {
            $push('todo','Укажите контакт для обращений по ПДн','Нужен действующий адрес электронной почты.',1);
        }
        foreach (['yandex','google','sberads'] as $id) {
            if (empty($c['services'][$id])) { continue; }
            $label=self::registry()[$id]['label'];
            if (!self::managed($c,$id)) {
                $push('todo','Укажите ID: '.$label,'Без корректного ID плагин не сможет загрузить этот счётчик.',3);
                continue;
            }
            if (($c['analytics_mode']==='optout' || ($c['analytics_mode']==='advanced' && $id==='google')) && !self::basis_confirmed($c,$id)) {
                $push('todo','Подтвердите условия запуска: '.$label,
                    'Выбран запуск до согласия. Пока нет проверенного правового основания, действует строгий режим. Можно подтвердить основание или выбрать режим «Сначала согласие».',4);
            }
        }
        if (!empty($c['services']['vk']) && !self::managed($c,'vk')) {
            $push('review','Пиксель VK Рекламы пока не установлен плагином',
                'Чтобы RU Privacy Kit устанавливал его сам, укажите ID пикселя в разделе «Счётчики и пиксели». Ранее установленный через тему или GTM код необходимо проверять отдельно.',3);
        }
        $active=self::active_services($c);
        if ($active && (!self::valid_inventory((string)$c['cookie_inventory']) || empty($c['inventory_reviewed']))) {
            $push('review','Не все точные cookie и сроки подтверждены',
                'Провайдеры и цели уже включены в документы по вашему выбору на шаге 3. Проверка отдельных cookie и сроков полезна для повышения точности, но не обязательна для продолжения.',3);
        }
        if (trim((string)$c['data']['security'])==='') {
            $push('todo','Опишите реальные меры защиты','Укажите только меры, которые фактически используются на сайте.',5);
        }
        if (trim((string)$c['data']['retention'])==='') {
            $push('todo','Укажите сроки хранения','Укажите реальные сроки либо критерии прекращения обработки.',5);
        }
        if ($c['data']['hosting']!=='ru') {
            $push('todo','Уточните размещение базы персональных данных',
                $c['data']['hosting']==='other'?'Указано размещение за пределами РФ. Проверьте требования локализации и измените конфигурацию после проверки.':'Место первичной записи и хранения персональных данных пока неизвестно.',5);
        }
        if ($c['data']['crossborder']==='unknown') {
            $push('todo','Уточните трансграничную передачу','Проверьте, получают ли иностранные организации персональные данные пользователей.',5);
        }
        if (!empty($c['commercial']['paid'])) {
            $kind = $c['operator_kind'] ?? 'other';
            if (in_array($kind,['ip','company','self'],true) && !preg_match('/^(?:\d{10}|\d{12})$/D',(string)($c['operator']['inn']??''))) {
                $push('todo','Укажите ИНН владельца для оферты','В публичной оферте должны использоваться действительные реквизиты продавца или исполнителя.',1);
            }
            if (in_array($kind,['ip','company'],true) && !preg_match('/^(?:\d{13}|\d{15})$/D',(string)($c['operator']['ogrn']??''))) {
                $push('todo','Укажите ОГРН или ОГРНИП для оферты','Без регистрационного номера невозможно корректно сформировать реквизиты.',1);
            }
            foreach (['subject'=>'предмет договора','price'=>'условия цены и оплаты','accept'=>'порядок акцепта','delivery'=>'порядок исполнения','refund'=>'условия отказа и возврата'] as $key=>$label) {
                if (trim((string)$c['commercial'][$key])==='') {
                    $push('todo','Заполните оферту: '.$label,'Для договора необходимы конкретные условия вашего предложения.',6);
                }
            }
        }
        $unmanaged=[];
        foreach ($active as $id=>$service) {
            if ($service['category']==='unmanaged') { $unmanaged[]=$service['label']; }
        }
        if ($unmanaged) {
            $push('review','Внешние сервисы требуют ручной проверки',
                'Вы выбрали: '.implode(', ',$unmanaged).'. Плагин не может гарантировать блокировку скриптов, добавленных темой, другим плагином или контейнером тегов.',3);
        }
        if (!empty($c['services']['yandex']) || !empty($c['services']['google']) || !empty($c['services']['sberads'])) {
            $push('review','Проверьте отсутствие дублирующих счётчиков',
                'Убедитесь через DevTools → Network, что тот же счётчик не запускается дополнительно через тему, GTM или другой плагин.',3);
        }
        return $items;
    }
    public static function warnings(array $c): array {
        return array_map(static fn(array $item): string => $item['title'].'. '.$item['detail'],
            array_filter(self::review_items($c),static fn(array $item): bool => $item['status']!=='ok'));
    }

    public static function valid_inventory(string $text): bool {
        $lines=array_filter(array_map('trim',preg_split('/\r?\n/', $text)));
        if (!$lines) { return false; }
        foreach ($lines as $line) {
            $cells=array_map('trim',explode('|',$line));
            if (count($cells)!==5) { return false; }
            foreach ($cells as $cell) { if ($cell==='') { return false; } }
        }
        return true;
    }
    public static function ready(array $c): bool {
        foreach (self::review_items($c) as $item) {
            if ($item['status']==='todo') { return false; }
        }
        return true;
    }
    public static function activate(): void {
        if (!get_option(self::OPTION)) { add_option(self::OPTION,self::defaults(),'','no'); }
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=$wpdb->prefix.'rupk_consent_log';
        $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            consent_id varchar(64) NOT NULL,
            revision bigint(20) unsigned NOT NULL,
            action varchar(24) NOT NULL,
            categories varchar(120) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY consent_id (consent_id),
            KEY created_at (created_at)
        ) $charset;");
        if (!wp_next_scheduled('rupk_daily_cleanup')) { wp_schedule_event(time()+DAY_IN_SECONDS,'daily','rupk_daily_cleanup'); }
    }
    public static function deactivate(): void { wp_clear_scheduled_hook('rupk_daily_cleanup'); }
    public static function init(): void {
        add_action('wp_ajax_nopriv_rupk_log',['RUPK_Core','log']);
        add_action('wp_ajax_rupk_log',['RUPK_Core','log']);
        add_action('rupk_daily_cleanup',['RUPK_Core','cleanup']);
        add_shortcode('rupk_cookie_settings',static function () { return '<button type="button" class="rupk-open-preferences">'.esc_html__('Настройки cookie','ru-privacy-kit').'</button>'; });
    }
    public static function log(): void {
        $c=self::config();
        if (empty($c['log_consent'])) { wp_send_json_error(['message'=>'disabled'],403); }
        if (!check_ajax_referer('rupk_public','nonce',false)) { wp_send_json_error(['message'=>'nonce expired'],403); }
        $id=isset($_POST['id'])?sanitize_text_field(wp_unslash($_POST['id'])):'';
        $action=isset($_POST['event'])?sanitize_key(wp_unslash($_POST['event'])):'';
        $cats=isset($_POST['categories'])?sanitize_text_field(wp_unslash($_POST['categories'])):'';
        $rev=isset($_POST['revision'])?absint($_POST['revision']):0;
        // Short-lived HMAC prevents the optional public journal endpoint from being used as an unrestricted write target.
        $remote=(string)($_SERVER['REMOTE_ADDR']??'');
        $limiter='rupk_rl_'.substr(hash_hmac('sha256',$remote,wp_salt('nonce')),0,24);
        $attempts=(int)get_transient($limiter);
        if ($attempts>=40) { wp_send_json_error(['message'=>'rate limit'],429); }
        set_transient($limiter,$attempts+1,MINUTE_IN_SECONDS);
        if (!preg_match('/^[0-9a-f-]{36}$/D',$id) || !in_array($action,['grant','deny','change','revoke'],true) || !preg_match('/^[01]{3}$/D',$cats) || $rev !== (int)$c['revision']) {
            wp_send_json_error(['message'=>'invalid'],400);
        }
        // Journal is optional. No IP, raw user-agent, or identifying session attributes.
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'rupk_consent_log', ['consent_id'=>$id,'revision'=>$rev,'action'=>$action,'categories'=>$cats,'created_at'=>current_time('mysql',true)],['%s','%d','%s','%s','%s']);
        wp_send_json_success(['saved'=>true]);
    }
    public static function cleanup(): void {
        global $wpdb;
        $days=max(1,min(365,(int)self::config()['log_retention_days']));
        $cutoff=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}rupk_consent_log WHERE created_at < %s",$cutoff));
    }
}