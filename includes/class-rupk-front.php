<?php
if (!defined('ABSPATH')) { exit; }
final class RUPK_Front {
    public static function init(): void {
        add_action('wp_enqueue_scripts',[self::class,'enqueue'],2);
        add_action('wp_head',[self::class,'early_guards'],-999);
        add_action('wp_footer',[self::class,'render'],80);
    }
    private static function live(array $c): bool {
        if (is_admin() || wp_doing_ajax() || empty($c['enabled']) || empty($c['complete'])) { return false; }
        // If a visitor-facing legal page was deleted or unpublished, avoid rendering a
        // banner with dead links or injecting managed trackers until re-publication.
        foreach (RUPK_Publisher::types($c) as $type) {
            if (!RUPK_Documents::url($type,$c)) { return false; }
        }
        return true;
    }
    public static function early_guards(): void {
        $c=RUPK_Core::config(); if (!self::live($c)) { return; }
        $ids=[];
        if (RUPK_Core::managed($c,'yandex')) { $ids['yandex']=$c['yandex_id']; }
        if (RUPK_Core::managed($c,'google')) { $ids['google']=$c['google_id']; }
        if (RUPK_Core::managed($c,'sberads')) { $ids['sberads']=$c['sberads_id']; }
        $basises=['yandex'=>RUPK_Core::basis_confirmed($c,'yandex'),'google'=>RUPK_Core::basis_confirmed($c,'google'),'sberads'=>RUPK_Core::basis_confirmed($c,'sberads')];
        $boot=['revision'=>(int)$c['revision'],'mode'=>$c['analytics_mode'],'id'=>$ids,'basis'=>$basises];
        // Synchronous guard before wp_enqueue_scripts prints managed tags, and as early as WordPress allows.
        echo '<script id="rupk-early">(function(o){try{var s=document.cookie.match(/(?:^|; )rupk_consent=([^;]*)/),v=s?JSON.parse(decodeURIComponent(s[1])):null,valid=v&&v.v===o.revision&&v.decided===true,ok=!!(valid&&v.a===true),denied=!!(valid&&v.a===false),auto=o.mode==="optout"&&!denied;window.RUPK_EARLY={choice:valid?v:null};if(o.id.yandex)window["disableYaCounter"+o.id.yandex]=!(ok||(auto&&o.basis.yandex));if(o.id.google)window["ga-disable-"+o.id.google]=!ok&&!(auto&&o.basis.google)&&!(o.mode==="advanced"&&!denied&&o.basis.google);}catch(e){if(o.id.yandex)window["disableYaCounter"+o.id.yandex]=true;if(o.id.google)window["ga-disable-"+o.id.google]=true;}})('.wp_json_encode($boot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).');</script>';
    }
    public static function enqueue(): void {
        $c=RUPK_Core::config(); if (!self::live($c)) { return; }
        wp_enqueue_style('rupk-front',RUPK_URL.'assets/css/front.css',[],RUPK_VERSION);
        $d=$c['design'];
        $tokens=[];
        foreach (['bg','text','accent'] as $k) {
            if ($d[$k] && sanitize_hex_color($d[$k])) { $tokens[]='--rupk-'.$k.':'.$d[$k]; }
        }
        $tokens[]='--rupk-radius:'.(int)$d['radius'].'px';
        $theme='';
        if ($d['theme']==='inherit' && function_exists('wp_get_global_styles')) {
            $styles=wp_get_global_styles([],['transforms'=>['resolve-variables']]);
            if (isset($styles['color']['background']) && sanitize_hex_color($styles['color']['background'])) { $tokens[]='--rupk-inherited-bg:'.$styles['color']['background']; }
            if (isset($styles['color']['text']) && sanitize_hex_color($styles['color']['text'])) { $tokens[]='--rupk-inherited-text:'.$styles['color']['text']; }
        }
        wp_add_inline_style('rupk-front','#rupk-root{'.implode(';',$tokens).'}');
        $services=[];
        foreach (['yandex','google','sberads','vk'] as $id) {
            $services[$id]=['managed'=>RUPK_Core::managed($c,$id),'id'=>$c[$id.'_id'], 'basis'=>RUPK_Core::basis_confirmed($c,$id)];
        }
        $cfg=[
            'revision'=>(int)$c['revision'], 'mode'=>$c['analytics_mode'], 'layout'=>$d['layout'],
            'days'=>(int)$d['retention_days'], 'services'=>$services, 'log'=>!empty($c['log_consent']),
            'endpoint'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('rupk_public'),
            'privacyUrl'=>RUPK_Documents::url('privacy',$c), 'cookieUrl'=>RUPK_Documents::url('cookie',$c),
            'hasAnalytics'=>(bool)($services['yandex']['managed']||$services['google']['managed']||$services['sberads']['managed']),
            'hasUnmanaged'=>count(RUPK_Core::active_services($c)) > (int)!empty($c['services']['yandex'])+(int)!empty($c['services']['google'])+(int)!empty($c['services']['sberads'])+(int)!empty($c['services']['vk']),
        ];
        wp_enqueue_script('rupk-front',RUPK_URL.'assets/js/front.js',[],RUPK_VERSION,false);
        wp_add_inline_script('rupk-front','window.RUPK_CONFIG='.wp_json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public static function render(): void {
        $c=RUPK_Core::config(); if (!self::live($c)) { return; }
        $d=$c['design'];
        $hasAnalytics=RUPK_Core::managed($c,'yandex')||RUPK_Core::managed($c,'google')||RUPK_Core::managed($c,'sberads');
        $hasMore=count(RUPK_Core::active_services($c))>0;
        $show=$hasAnalytics||$hasMore;
        $name=$d['layout']==='notice'?'Уведомление об использовании cookie':($c['analytics_mode']==='optout'?'Аналитика и файлы cookie':($c['analytics_mode']==='advanced'?'Настройки аналитики':'Настройки файлов cookie'));
        $prefix='Для работы сайта мы используем необходимые cookie.';
        if ($c['analytics_mode']==='optout') {
            $text='Аналитика посещаемости может работать до вашего выбора. Вы можете отключить её или изменить настройки. Реклама — только с разрешения.';
        } elseif ($c['analytics_mode']==='advanced') {
            $text='Некоторые сервисы могут передавать технические сигналы до вашего выбора. Полная аналитика и реклама — только с разрешения.';
        } else { $text='С вашего разрешения мы также включаем аналитику и другие необязательные технологии. Вы можете настроить свой выбор.'; }
        $theme=$d['theme'];
        echo '<div id="rupk-root" class="rupk-layout-'.esc_attr($d['layout']).' rupk-theme-'.esc_attr($theme).'" data-revision="'.(int)$c['revision'].'" hidden>';
        echo '<section class="rupk-banner" role="'.($d['layout']==='modal'?'dialog':'region').'" aria-label="'.esc_attr($name).'"'.($d['layout']==='modal'?' aria-modal="false"':'').'>';
        if ($d['layout']==='notice') {
            $policyUrl=RUPK_Documents::url('cookie',$c);
            echo '<div class="rupk-copy"><p>Для настройки персонализированных сервисов сайт использует ';
            echo $policyUrl?'<a href="'.esc_url($policyUrl).'">файлы cookie</a>':'файлы cookie';
            echo ', а также метрические и другие аналитические системы. Вы можете запретить использование cookie в настройках вашего браузера.</p></div>';
            echo '<button type="button" class="rupk-notice-close" data-rupk="dismiss" aria-label="Закрыть уведомление">×</button>';
        } elseif ($d['layout']==='micro') {
            $policyUrl=RUPK_Documents::url('cookie',$c);
            echo '<div class="rupk-copy"><p>Сайт использует ';
            echo $policyUrl?'<a href="'.esc_url($policyUrl).'">файлы cookie</a>':'файлы cookie';
            echo ' и аналитические системы для улучшения работы. Нажимая «Принять», вы разрешаете сбор статистики. Cookie можно отключить в браузере. <a href="#rupk-cookie-preferences" class="rupk-open-preferences">Настройки</a>.</p></div>';
            echo '<button type="button" class="rupk-micro-accept" data-rupk="micro_accept">Принять</button>';
        } else {
            echo '<div class="rupk-copy"><h2>'.esc_html($name).'</h2><p>'.esc_html($prefix.' '.$text).'</p><p class="rupk-links">';
        if (RUPK_Documents::url('cookie',$c)) { echo '<a href="'.esc_url(RUPK_Documents::url('cookie',$c)).'">Политика cookie</a>'; }
        if (RUPK_Documents::url('privacy',$c)) { echo ' <a href="'.esc_url(RUPK_Documents::url('privacy',$c)).'">Политика ПДн</a>'; }
        echo '</p></div><div class="rupk-actions">';
        echo '<button type="button" class="rupk-primary" data-rupk="accept">Принять все</button>';
        echo '<button type="button" data-rupk="settings">Настроить</button>';
        echo '<button type="button" data-rupk="deny">'.esc_html($c['analytics_mode']==='optout'?'Без аналитики':'Только нужные').'</button>';
        echo '</div>'; // .rupk-actions
        }
        echo '</section>';
        echo '<section class="rupk-preferences" role="dialog" aria-modal="true" aria-label="Настройки cookie" hidden><header><h2>Настройки cookie</h2><button type="button" class="rupk-close" aria-label="Закрыть настройки" data-rupk="close">×</button></header>';
        echo '<p>Выберите необязательные категории. Необходимые функции остаются включёнными. Отказ от управляемой аналитики останавливает её дальнейшую работу.</p>';
        echo '<div class="rupk-pref-row"><span><strong>Необходимые</strong><small>Работа сайта, безопасность, сохранение выбора</small></span><span class="rupk-necessary">Всегда включены</span></div>';
        foreach (['functional'=>['Функциональные','Дополнительные возможности сайта и запоминание настроек'], 'analytics'=>['Аналитические','Статистика посещаемости'], 'marketing'=>['Маркетинговые','Реклама, ретаргетинг и маркетинговые пиксели']] as $id=>$detail) {
            echo '<label class="rupk-pref-row"><span><strong>'.esc_html($detail[0]).'</strong><small>'.esc_html($detail[1]).'</small></span><input type="checkbox" id="rupk-pref-'.esc_attr($id).'" data-rupk-category="'.esc_attr($id).'"></label>';
        }
        echo '<footer class="rupk-modal-actions"><button type="button" data-rupk="deny">Отклонить необязательные</button><button type="button" class="rupk-primary" data-rupk="save">Сохранить выбор</button></footer></section>';
        if ($d['floating_link']) { echo '<button type="button" class="rupk-floating rupk-open-preferences" aria-label="Настройки cookie">⚙ <span>Настройки cookie</span></button>'; }
        echo '</div>';
        // No consent banner for a site configured with no optional services.
        echo '<script>window.RUPK_SHOW_BANNER='.($show?'true':'false').';</script>';
    }
}