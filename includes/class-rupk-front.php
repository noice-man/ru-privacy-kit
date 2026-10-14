<?php
if (!defined('ABSPATH')) { exit; }
final class RUPK_Front {
    public static function init(): void {
        add_action('wp_enqueue_scripts',[self::class,'enqueue'],2);
        add_action('wp_head',[self::class,'early_guards'],-999);
        add_action('wp_footer',[self::class,'render'],80);
    }
    private static function live(array $c): bool {
        return !is_admin() && !wp_doing_ajax() && !empty($c['enabled']) && !empty($c['complete']);
    }
    public static function early_guards(): void {
        $c=RUPK_Core::config(); if (!self::live($c)) { return; }
        $ids=[];
        if (RUPK_Core::managed($c,'yandex')) { $ids['yandex']=$c['yandex_id']; }
        if (RUPK_Core::managed($c,'google')) { $ids['google']=$c['google_id']; }
        $basises=['yandex'=>RUPK_Core::basis_confirmed($c,'yandex'),'google'=>RUPK_Core::basis_confirmed($c,'google')];
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
        foreach (['yandex','google'] as $id) {
            $services[$id]=['managed'=>RUPK_Core::managed($c,$id),'id'=>$id==='yandex'?$c['yandex_id']:$c['google_id'], 'basis'=>RUPK_Core::basis_confirmed($c,$id)];
        }
        $cfg=[
            'revision'=>(int)$c['revision'], 'mode'=>$c['analytics_mode'], 'layout'=>$d['layout'],
            'days'=>(int)$d['retention_days'], 'services'=>$services, 'log'=>!empty($c['log_consent']),
            'endpoint'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('rupk_public'),
            'privacyUrl'=>RUPK_Documents::url('privacy',$c), 'cookieUrl'=>RUPK_Documents::url('cookie',$c),
            'hasAnalytics'=>(bool)($services['yandex']['managed']||$services['google']['managed']),
            'hasUnmanaged'=>count(RUPK_Core::active_services($c)) > (int)!empty($c['services']['yandex'])+(int)!empty($c['services']['google']),
        ];
        wp_enqueue_script('rupk-front',RUPK_URL.'assets/js/front.js',[],RUPK_VERSION,false);
        wp_add_inline_script('rupk-front','window.RUPK_CONFIG='.wp_json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public static function render(): void {
        $c=RUPK_Core::config(); if (!self::live($c)) { return; }
        $d=$c['design'];
        $hasAnalytics=RUPK_Core::managed($c,'yandex')||RUPK_Core::managed($c,'google');
        $hasMore=count(RUPK_Core::active_services($c))>0;
        $show=$hasAnalytics||$hasMore;
        $name=$c['analytics_mode']==='optout'?'Аналитика и файлы cookie':($c['analytics_mode']==='advanced'?'Настройки аналитики':'Настройки файлов cookie');
        $prefix='Для работы сайта мы используем необходимые cookie.';
        if ($c['analytics_mode']==='optout') {
            $text='Аналитика посещаемости может работать до вашего выбора. Вы можете отключить её или изменить настройки. Реклама — только с разрешения.';
        } elseif ($c['analytics_mode']==='advanced') {
            $text='Некоторые сервисы могут передавать технические сигналы до вашего выбора. Полная аналитика и реклама — только с разрешения.';
        } else { $text='С вашего разрешения мы также включаем аналитику и другие необязательные технологии. Вы можете настроить свой выбор.'; }
        $theme=$d['theme'];
        echo '<div id="rupk-root" class="rupk-layout-'.esc_attr($d['layout']).' rupk-theme-'.esc_attr($theme).'" data-revision="'.(int)$c['revision'].'" hidden>';
        echo '<section class="rupk-banner" role="'.($d['layout']==='modal'?'dialog':'region').'" aria-label="'.esc_attr($name).'"'.($d['layout']==='modal'?' aria-modal="false"':'').'>
          <div class="rupk-copy"><h2>'.esc_html($name).'</h2><p>'.esc_html($prefix.' '.$text).'</p><p class="rupk-links">';
        if (RUPK_Documents::url('cookie',$c)) { echo '<a href="'.esc_url(RUPK_Documents::url('cookie',$c)).'">Политика cookie</a>'; }
        if (RUPK_Documents::url('privacy',$c)) { echo ' <a href="'.esc_url(RUPK_Documents::url('privacy',$c)).'">Политика ПДн</a>'; }
        echo '</p></div><div class="rupk-actions">';
        echo '<button type="button" class="rupk-primary" data-rupk="accept">Принять все</button>';
        echo '<button type="button" data-rupk="settings">Настроить</button>';
        echo '<button type="button" data-rupk="deny">'.esc_html($c['analytics_mode']==='optout'?'Без аналитики':'Только нужные').'</button>';
        echo '</div></section>';
        echo '<section class="rupk-preferences" role="dialog" aria-modal="true" aria-label="Настройки cookie" hidden><header><h2>Настройки cookie</h2><button type="button" class="rupk-close" aria-label="Закрыть настройки" data-rupk="close">×</button></header>';
        echo '<p>Выберите необязательные категории. Необходимые функции остаются включёнными. Отказ от управляемой аналитики останавливает её дальнейшую работу.</p>';
        echo '<div class="rupk-pref-row"><span><strong>Необходимые</strong><small>Работа сайта, безопасность, сохранение выбора</small></span><span class="rupk-necessary">Всегда включены</span></div>';
        foreach (['functional'=>['Функциональные','Дополнительные возможности сайта и запоминание настроек'], 'analytics'=>['Аналитические','Статистика посещаемости'], 'marketing'=>['Маркетинговые','Реклама, ретаргетинг и маркетинговые пиксели']] as $id=>$detail) {
            echo '<label class="rupk-pref-row"><span><strong>'.esc_html($detail[0]).'</strong><small>'.esc_html($detail[1]).'</small></span><input type="checkbox" id="rupk-pref-'.esc_attr($id).'" data-rupk-category="'.esc_attr($id).'"></label>';
        }
        echo '<footer class="rupk-modal-actions"><button type="button" data-rupk="deny">Отклонить необязательные</button><button type="button" class="rupk-primary" data-rupk="save">Сохранить выбор</button></footer></section>';
        if ($d['floating_link']) { echo '<button type="button" class="rupk-floating rupk-open-preferences" aria-label="Настройки cookie">⚙ <span>Настройки cookie</span></button>'; }
        echo '</div>';
        if (!$d['floating_link'] && $show) { echo '<button class="rupk-open-preferences rupk-footer-link" type="button">Настройки cookie</button>'; }
        // No consent banner for a site configured with no optional services.
        echo '<script>window.RUPK_SHOW_BANNER='.($show?'true':'false').';</script>';
    }
}