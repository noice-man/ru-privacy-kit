<?php
if (!defined('ABSPATH')) { exit; }
final class RUPK_Admin {
    public static function init(): void {
        add_action('admin_menu',[self::class,'menu']);
        add_action('admin_notices',[self::class,'notice']);
        add_action('admin_enqueue_scripts',[self::class,'assets']);
        add_action('admin_post_rupk_save',[self::class,'save']);
        add_action('admin_post_rupk_generate',[self::class,'generate']);
        add_action('admin_post_rupk_publish',[self::class,'publish']);
        add_action('admin_post_rupk_finish',[self::class,'finish']);
        add_action('admin_post_rupk_bind',[self::class,'bind']);
        add_action('admin_post_rupk_disable',[self::class,'disable']);
    }
    public static function menu(): void {
        add_menu_page('RU Privacy Kit','RU Privacy Kit','manage_options','rupk', [self::class,'dashboard'],'dashicons-shield-alt',78);
        $pages=['rupk-wizard'=>['Мастер настройки','wizard'],'rupk-design'=>['Cookie-баннер','design'], 'rupk-services'=>['Аналитика и сервисы','services'], 'rupk-documents'=>['Документы','documents'], 'rupk-consent'=>['Журнал согласий','journal'], 'rupk-diagnostics'=>['Диагностика','diagnostics'], 'rupk-settings'=>['Настройки','settings']];
        foreach ($pages as $slug=>$val) { add_submenu_page('rupk',$val[0],$val[0],'manage_options',$slug,[self::class,$val[1]]); }
    }
    public static function assets(string $hook): void {
        if (strpos($hook,'rupk')===false) { return; }
        wp_enqueue_style('rupk-front-preview',RUPK_URL.'assets/css/front.css',[],RUPK_VERSION);
        wp_enqueue_style('rupk-admin',RUPK_URL.'assets/css/admin.css',['rupk-front-preview'],RUPK_VERSION);
        wp_enqueue_script('rupk-admin',RUPK_URL.'assets/js/admin.js',[],RUPK_VERSION,true);
    }
    public static function notice(): void {
        if (!current_user_can('manage_options')) { return; }
        $c=RUPK_Core::config();
        if ($c['complete']) { return; }
        if (isset($_GET['page']) && strpos(sanitize_key(wp_unslash($_GET['page'])),'rupk')===0) { return; }
        echo '<div class="notice notice-info"><p><strong>RU Privacy Kit:</strong> закончите подготовку документов и настройте cookie-баннер. До подтверждения документов плагин не включает новый баннер и управляемые счётчики. <a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=rupk-wizard')).'">Запустить мастер</a></p></div>';
    }
    private static function header(string $title,string $lead=''): void {
        echo '<div class="wrap rupk-admin"><header class="rupk-admin-head"><div><h1>'.esc_html($title).'</h1>'.($lead?'<p>'.esc_html($lead).'</p>':'').'</div><span class="rupk-version">RU Privacy Kit '.esc_html(RUPK_VERSION).'</span></header>';
        if (isset($_GET['rupk_msg'])) { echo '<div class="notice notice-success inline"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['rupk_msg']))).'</p></div>'; }
        if (isset($_GET['rupk_error_detail'])) {
            echo '<div class="notice notice-error inline" role="alert"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['rupk_error_detail']))).'</p></div>';
        }
        if (isset($_GET['rupk_error'])) {
            $messages = [
                'review' => 'Подтверждение получено, но публикация остановлена: остались обязательные вопросы мастера. Они перечислены ниже со ссылками «Исправить». Ваши настройки не потеряны. Галочки подтверждения после неудачной отправки необходимо отметить заново.',
                'confirm' => 'Перед публикацией подтвердите, что вы проверили тексты всех документов.',
                'missing' => 'Не все обязательные страницы привязаны. Создайте черновики или выберите существующие страницы.',
                'manual' => 'Привязанная вручную страница ещё не опубликована. Откройте её в редакторе WordPress и опубликуйте перед включением баннера.',
                'outdated' => 'Тексты созданных плагином страниц устарели после изменения настроек. Проверьте различия и подтвердите их обновление.',
                'write' => 'WordPress не смог сохранить или опубликовать одну из страниц. Повторите попытку после проверки прав на редактирование и журнала ошибок.',
            ];
            $code=sanitize_key(wp_unslash($_GET['rupk_error']));
            if (isset($messages[$code])) { echo '<div class="notice notice-error inline rupk-publish-error" role="alert"><p><strong>Публикация не выполнена.</strong> '.esc_html($messages[$code]).'</p></div>'; }
        }
    }
    private static function footer(): void { echo '</div>'; }
    private static function e(string $s): void { echo esc_html($s); }
    private static function field(string $name,string $label,$value,string $type='text',string $help='',bool $required=false): void {
        echo '<label class="rupk-field"><span>'.esc_html($label).($required?' <em>*</em>':'').'</span>';
        if ($type==='textarea') { echo '<textarea rows="3" name="'.esc_attr($name).'">'.esc_textarea((string)$value).'</textarea>'; }
        else { echo '<input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.($required?' required':'').'>'; }
        if ($help) { echo '<small>'.esc_html($help).'</small>'; } echo '</label>';
    }
    private static function select(string $name,string $label,$value,array $choices,string $help=''): void {
        echo '<label class="rupk-field"><span>'.esc_html($label).'</span><select name="'.esc_attr($name).'">';
        foreach ($choices as $k=>$v) { echo '<option value="'.esc_attr((string)$k).'"'.selected($k,$value,false).'>'.esc_html($v).'</option>'; }
        echo '</select>'.($help?'<small>'.esc_html($help).'</small>':'').'</label>';
    }
    private static function checkbox(string $name,string $label,bool $checked,string $help=''): void {
        echo '<label class="rupk-check"><input type="checkbox" name="'.esc_attr($name).'" value="1"'.checked($checked,true,false).'> <span><strong>'.esc_html($label).'</strong>'.($help?'<small>'.esc_html($help).'</small>':'').'</span></label>';
    }
    private static function form_start(int $step=0): void {
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="rupk_save"><input type="hidden" name="step" value="'.(int)$step.'">';
        wp_nonce_field('rupk_save_'.$step);
    }
    private static function form_end(int $step,string $label='Сохранить и продолжить'): void {
        echo '<div class="rupk-actions">';
        if ($step>1) { echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step='.($step-1))).'">Назад</a>'; }
        submit_button($label,'primary','submit',false);
        echo '</div></form>';
    }
    public static function wizard(): void {
        $c=RUPK_Core::config(); $step=isset($_GET['step'])?max(1,min(8,absint($_GET['step']))):max(1,min(8,(int)$c['wizard_step']));
        self::header('Мастер настройки','Все данные сохраняются на вашем сайте. Никаких внешних аккаунтов не требуется.');
        echo '<nav class="rupk-steps" aria-label="Шаги мастера">';
        foreach (['Оператор','Данные','Сервисы','Аналитика','Хранение','Оферта','Дизайн','Проверка'] as $i=>$name) {
            echo '<a class="'.($step===($i+1)?'current':'').'" href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step='.($i+1))).'">'.($i+1).'. '.esc_html($name).'</a>';
        } echo '</nav><div class="rupk-panel">';
        if ($step===1) {
            echo '<h2>1. Кто владеет сайтом?</h2><p>Реквизиты используются в документах. Проверьте сведения перед публикацией.</p>';
            self::form_start(1);
            self::select('site_kind','Тип сайта',$c['site_kind'],['information'=>'Информационный сайт','blog'=>'Блог','corporate'=>'Корпоративный сайт','landing'=>'Лендинг с заявками','catalog'=>'Каталог','shop'=>'Интернет-магазин','saas'=>'SaaS / онлайн-сервис','other'=>'Другое']);
            self::select('operator_kind','Статус оператора',$c['operator_kind'],['ip'=>'Индивидуальный предприниматель','company'=>'Юридическое лицо','self'=>'Самозанятый','person'=>'Физическое лицо','other'=>'Иное']);
            foreach (['name'=>'Полное юридическое наименование / ФИО','inn'=>'ИНН','ogrn'=>'ОГРН или ОГРНИП','address'=>'Юридический / почтовый адрес','email'=>'Email для обращений по ПДн','support'=>'Email поддержки'] as $k=>$v) {
                self::field('operator['.$k.']',$v,$c['operator'][$k],$k==='email'||$k==='support'?'email':'text','',$k==='name'||$k==='email');
            }
            self::form_end(1);
        } elseif ($step===2) {
            echo '<h2>2. Какие данные обрабатываются?</h2><p>Отметьте реальные функции сайта; каждую цель отразим в политике ПДн.</p>';
            self::form_start(2);
            foreach (RUPK_Documents::purpose_map() as $id=>$p) { self::checkbox('purposes['.$id.']',$p[0],in_array($id,$c['purposes'],true),$p[1]); }
            self::form_end(2);
        } elseif ($step===3) {
            echo '<h2>3. Какие инструменты подключены к сайту?</h2><p>Отметьте только то, что действительно используется. Если не знаете, проверьте установленные плагины и код темы. Сам выбор сервиса не означает, что RU Privacy Kit автоматически управляет его скриптами.</p>';
            self::form_start(3);
            $groups=[
                'Счётчики посещаемости'=>['yandex','google','local'],
                'Управление тегами и реклама'=>['gtm','vk'],
                'Защита и встроенный контент'=>['captcha','video','custom'],
            ];
            $registry=RUPK_Core::registry();
            foreach ($groups as $heading=>$ids) {
                echo '<section class="rupk-service-group"><h3>'.esc_html($heading).'</h3><div class="rupk-service-grid">';
                foreach ($ids as $id) {
                    $svc=$registry[$id];
                    $helper=($svc['category']==='analytics')?
                        'Можно загрузить через плагин, если указан ID счётчика и устранены дубли.':
                        'Установленный извне скрипт плагин пока не блокирует: потребуется отдельная проверка.';
                    echo '<div class="rupk-service-choice">';
                    self::checkbox('services['.$id.']',$svc['label'],!empty($c['services'][$id]),$helper);
                    echo '</div>';
                }
                echo '</div></section>';
            }
            echo '<div class="rupk-service-fields" data-rupk-service-field="yandex">';
            self::field('yandex_id','Номер счётчика Яндекс Метрики',$c['yandex_id'],'text','Найдите в интерфейсе Метрики → Настройки → Счётчик.');
            echo '</div><div class="rupk-service-fields" data-rupk-service-field="google">';
            self::field('google_id','Идентификатор потока Google Analytics 4',$c['google_id'],'text','Значение начинается с G- (не GTM-).');
            echo '</div><div class="rupk-service-fields" data-rupk-service-field="gtm">';
            self::field('gtm_id','Номер контейнера Google Tag Manager',$c['gtm_id'],'text','Вид GTM-XXXXXX. В beta.2 контейнер не загружается и не блокируется плагином.');
            echo '</div>';
            self::cookie_inventory_fields($c);
            self::form_end(3);
        } elseif ($step===4) {
            self::analytics_fields($c,4);
        } elseif ($step===5) {
            echo '<h2>5. Где хранятся данные и как защищаются?</h2>';
            self::form_start(5);
            self::select('hosting','Местонахождение основных баз ПДн',$c['data']['hosting'],['unknown'=>'Не знаю — требуется проверить','ru'=>'Россия','other'=>'За пределами РФ (требуется дополнительная оценка)']);
            self::select('crossborder','Есть ли трансграничная передача?',$c['data']['crossborder'],['unknown'=>'Не знаю','yes'=>'Да','no'=>'Нет, по подтверждённым сведениям']);
            self::field('retention','Срок / критерий хранения',$c['data']['retention'],'textarea','Укажите применимые сроки по вашим процессам.');
            self::field('security','Подтверждённые меры защиты',$c['data']['security'],'textarea','Не раскрывайте пароли и секреты, только реально применяемые меры.');
            self::form_end(5);
        } elseif ($step===6) {
            echo '<h2>6. Нужна ли публичная оферта?</h2><p>Оферта создаётся только при продаже товаров или услуг. Черновик нужно проверить юридически.</p>';
            self::form_start(6);
            self::checkbox('paid','На сайте есть платные услуги, товары или подписка',$c['commercial']['paid']);
            self::select('commercial_kind','Тип договора',$c['commercial']['kind'],['saas'=>'SaaS / доступ к сервису','services'=>'Оказание услуг','digital'=>'Цифровой продукт','goods'=>'Товары']);
            foreach (['subject'=>'Предмет договора','price'=>'Цена, тарифы, НДС и оплата','accept'=>'Какие действия считаются акцептом','delivery'=>'Когда и как исполняется договор','refund'=>'Отмена, отказ, возвраты','support'=>'Контакт поддержки'] as $k=>$v) { self::field('commercial['.$k.']',$v,$c['commercial'][$k],'textarea'); }
            self::form_end(6);
        } elseif ($step===7) {
            self::design_fields($c,7);
        } else { self::review($c); }
        echo '</div>'; self::footer();
    }
    /** Friendly inventory editor: providers are derived from step 3; exact cookies are optional. */
    private static function cookie_inventory_fields(array $c): void {
        $services=RUPK_Core::active_services($c);
        $categories=['necessary'=>'Необходимые','functional'=>'Функциональные','analytics'=>'Аналитика','marketing'=>'Маркетинг'];
        echo '<section class="rupk-inventory rupk-cookie-simple" data-rupk-cookie-editor>';
        echo '<div class="rupk-inventory-heading"><div><h3>Cookie и сервисы — почти готово</h3><p>Не нужно искать технические названия и писать таблицу вручную. Подключённые сервисы берём из вашего выбора выше, и они автоматически попадут в документы.</p></div><span class="rupk-auto-mark">Автоматически</span></div>';
        echo '<div class="rupk-provider-list" data-rupk-providers>';
        echo '<div class="rupk-provider"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span><strong>Сохранение вашего выбора cookie</strong><small>RU Privacy Kit · необходимый файл rupk_consent · '.(int)$c['design']['retention_days'].' дней</small></span></div>';
        echo '<div class="rupk-provider" data-rupk-no-services'.($services?' hidden':'').'><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span><strong>Дополнительные сервисы пока не выбраны</strong><small>Если на сайте есть счётчики, капча или рекламные пиксели, отметьте их выше.</small></span></div>';
        foreach (RUPK_Core::registry() as $id=>$service) {
            $cat=$service['category']==='analytics'?'Статистика посещаемости':'Назначение уточняется по выбранному сервису';
            echo '<div class="rupk-provider" data-rupk-service-provider="'.esc_attr($id).'"'.(empty($c['services'][$id])?' hidden':'').'><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span><strong>'.esc_html($service['label']).'</strong><small>'.esc_html($service['provider'].' · '.$cat).($service['policy']?' · <a href="'.esc_url($service['policy']).'" target="_blank" rel="noopener noreferrer">Документация поставщика ↗</a>':'').'</small></span></div>';
        }
        echo '</div>';
        echo '<div class="rupk-cookie-action"><div><strong>Хотите проверить реальные cookie автоматически?</strong><p>Нажмите кнопку — попробуем обнаружить доступные cookie этого сайта в вашем браузере. Названия подставим в таблицу, данные cookie и пароли никуда не отправляем.</p></div><button type="button" class="button button-secondary" data-rupk-scan>Найти cookie</button></div>';
        echo '<div class="rupk-cookie-scan-status" role="status" aria-live="polite" data-rupk-scan-status></div>';
        echo '<details class="rupk-expert rupk-cookie-advanced"'.($c['cookie_inventory']!==''?' open':'').' data-rupk-cookie-advanced><summary>Точные cookie и сроки — необязательно, для расширенной проверки</summary>';
        echo '<p>Этот раздел можно пропустить. Ни один браузерный сканер не гарантирует обнаружение всех сторонних или HttpOnly-cookie, а срок хранения не всегда доступен. Неизвестные значения не будут выдаваться за подтверждённые.</p>';
        echo '<div class="rupk-cookie-table-wrap"><table class="widefat striped rupk-cookie-table"><thead><tr><th>Название</th><th>Кто использует</th><th>Зачем</th><th>Тип</th><th>Срок</th><th><span class="screen-reader-text">Удалить</span></th></tr></thead><tbody data-rupk-cookie-rows>';
        foreach (preg_split('/\r?\n/',trim((string)$c['cookie_inventory'])) as $line) {
            $cols=array_map('trim',explode('|',$line));
            if (count($cols)!==5) { continue; }
            echo '<tr class="rupk-cookie-row">';
            foreach ([0,1,2] as $i) { echo '<td><input type="text" class="regular-text" data-rupk-column="'.(int)$i.'" value="'.esc_attr($cols[$i]).'" aria-label="'.esc_attr(['Название cookie','Поставщик','Цель'][$i]).'"></td>'; }
            echo '<td><select data-rupk-column="3" aria-label="Тип cookie">';
            foreach ($categories as $k=>$label) { echo '<option value="'.esc_attr($k).'"'.selected($k,$cols[3],false).'>'.esc_html($label).'</option>'; }
            echo '</select></td>';
            echo '<td><input type="text" data-rupk-column="4" value="'.esc_attr($cols[4]).'" aria-label="Срок хранения"></td>';
            echo '<td><button type="button" class="button-link-delete" data-rupk-cookie-remove aria-label="Удалить cookie">×</button></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p><button type="button" class="button button-secondary" data-rupk-cookie-add>+ Добавить вручную</button></p>';
        self::checkbox('inventory_reviewed','Я лично проверил(а) названия и сроки в таблице',$c['inventory_reviewed'],'Не отмечайте, если таблица составлена автоматически и вы её ещё не сверили. Это не мешает опубликовать документы с перечнем выбранных сервисов.');
        echo '</details>';
        echo '<textarea data-rupk-cookie-serialized name="cookie_inventory" hidden aria-hidden="true">'.esc_textarea((string)$c['cookie_inventory']).'</textarea>';
        echo '<p class="rupk-cookie-footer">Можно сразу нажать «Сохранить и продолжить». Плагин сформирует политику по выбранным сервисам, а необходимость более подробной технической проверки покажет в диагностике.</p>';
        echo '</section>';
    }

    private static function analytics_fields(array $c,int $step): void {
        echo '<h2>4. Как должна работать аналитика?</h2><p>Выберите, когда можно запускать счётчики. Для большинства сайтов безопасный вариант — «Сначала согласие». Если хотите собирать обычную статистику сайта до выбора посетителя, плагин подставит стандартный вариант «Статистика сайта и улучшение работы» — вам останется только проверить его применимость.</p>';
        self::form_start($step);
        if ($step===4 && isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page']))==='rupk-services') { echo '<input type="hidden" name="from_services" value="1">'; }
        foreach ([
            'strict'=>['Сначала согласие','Счётчики запускаются после явного разрешения. Часть посещений не попадёт в статистику, зато до разрешения управляемая аналитика не отправляет данные.','Рекомендуется'],
            'optout'=>['Аналитика до отказа','Счётчики могут запускаться до ответа посетителя. Это позволяет учитывать больше посещений, но требует самостоятельно подтвердить законность такой обработки по каждому сервису.',''],
            'advanced'=>['Расширенный режим Google','Google может передавать ограниченные сигналы без аналитических cookie до выбора пользователя. Яндекс Метрика при этом ждёт согласия. Передача сигналов также нуждается в правовом основании.',''],
        ] as $key=>$v) {
            echo '<label class="rupk-mode"><input type="radio" name="analytics_mode" value="'.esc_attr($key).'"'.checked($c['analytics_mode'],$key,false).'><span><strong>'.esc_html($v[0]).'</strong>'.($v[2]?'<span class="rupk-recommended">'.esc_html($v[2]).'</span>':'').'<small>'.esc_html($v[1]).'</small></span></label>';
        }
        $active=[];
        foreach (['yandex','google'] as $id) { if (!empty($c['services'][$id])) { $active[]=$id; } }
        if (!$active) {
            echo '<div class="rupk-helper">Вы пока не отметили ни одного управляемого счётчика. Режим сохранится, но не изменит работу аналитики до подключения сервиса на <a href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=3')).'">шаге 3</a>.</div>';
        } else {
            echo '<div class="rupk-legal-help" data-rupk-legal-mode><h3>Если хотите собирать статистику до согласия</h3><p>Ниже уже подготовлен стандартный вариант для обычной веб-аналитики сайта. Проверьте, подходит ли он вам. Если не уверены — оставьте строгий режим «Сначала согласие».</p></div>';
        }
        $hints=[
            'yandex'=>['Статистика сайта и улучшение его работы.','Обычно счётчик получает технические и сетевые данные о посещении, сведения об устройстве и браузере, просмотренных страницах и cookie-идентификаторы. Получатель — ООО «Яндекс». Проверьте фактический состав данных и ваши договорные условия.'],
            'google'=>['Статистика сайта и улучшение его работы.','Обычно счётчик получает технические и сетевые данные о посещении, событиях посещения и cookie-идентификаторы. Получатель — Google. Проверьте фактический состав данных, маршруты передачи и трансграничную передачу.'],
        ];
        foreach ($active as $id) {
            $l=$c['legal'][$id];
            $eligible=$id==='google'?['optout','advanced']:['optout'];
            echo '<fieldset class="rupk-subpanel" data-rupk-basis-panel="'.esc_attr($id).'" data-rupk-modes="'.esc_attr(implode(' ',$eligible)).'"><legend>'.esc_html(RUPK_Core::registry()[$id]['label']).'</legend>';
            echo '<p class="rupk-subtle">Этот блок нужен только если вы хотите запускать счётчик до согласия посетителя.</p>';
            $currentBasis = $l['basis']!=='' ? $l['basis'] : 'legitimate';
            self::select('basis['.$id.']','Когда можно запускать этот счётчик до согласия',$currentBasis,[
                'legitimate'=>'Статистика сайта и улучшение работы (стандартный вариант)', ''=>'Не запускать до согласия',
                'contract'=>'Это действительно нужно для исполнения договора',
                'law'=>'Этого прямо требует закон',
                'other'=>'Другое основание — укажу сам(а)',
            ]);
            echo '<details class="rupk-expert"><summary>Что именно будет описано в документах — проверить или изменить</summary>';
            echo '<p>Ниже уже подготовлена стартовая формулировка по выбранному сервису. Проверьте её и при необходимости уточните под свою фактическую настройку.</p>';
            self::field('basis_purpose['.$id.']','Зачем используется счётчик', $l['purpose']!==''?$l['purpose']:$hints[$id][0], 'textarea');
            self::field('basis_details['.$id.']','Какие данные получает сервис и кто их получает', $l['details']!==''?$l['details']:$hints[$id][1], 'textarea');
            echo '</details>';
            self::checkbox('basis_confirmed['.$id.']','Я проверил(а), что этот вариант подходит для моего сайта и соответствует фактической обработке',$l['confirmed']);
            echo '</fieldset>';
        }
        if ($active) { echo '<div class="rupk-helper" data-rupk-fallback-note>Если вы не подтвердите этот вариант, плагин автоматически оставит счётчик в режиме «Сначала согласие». Это безопасный запасной сценарий.</div>'; }
        self::form_end($step,$step===4?'Сохранить и продолжить':'Сохранить');
    }
    private static function design_fields(array $c,int $step): void {
        echo '<h2>Внешний вид cookie-баннера</h2><p>Изменение оформления не влияет на ранее выраженные согласия.</p>';
        self::form_start($step);
        if ($step===7 && isset($_GET['page']) && $_GET['page']==='rupk-design') { echo '<input type="hidden" name="from_design" value="1">'; }
        echo '<div class="rupk-layout-grid">';
        foreach (['bottom'=>'Нижняя полоса','card'=>'Плавающая карточка','modal'=>'Центральное окно','top'=>'Верхняя полоса','minimal'=>'Минималистичный'] as $id=>$label) {
            echo '<label class="rupk-layout"><input type="radio" name="layout" value="'.esc_attr($id).'"'.checked($c['design']['layout'],$id,false).'><span class="rupk-schematic rupk-schematic-'.esc_attr($id).'"><i></i></span><strong>'.esc_html($label).'</strong></label>';
        }
        echo '</div>';
        self::select('theme','Тема',$c['design']['theme'],['inherit'=>'Как на сайте (наследование темы WP)','light'=>'Светлая','dark'=>'Тёмная','system'=>'Как в системе пользователя','custom'=>'Свои цвета']);
        echo '<div class="rupk-field-row">';
        foreach (['bg'=>'Фон баннера','text'=>'Цвет текста','accent'=>'Акцент кнопки'] as $k=>$name) { self::field('color['.$k.']',$name,$c['design'][$k],'text','Цвет в формате #RRGGBB; пустое поле — наследование.'); }
        echo '</div>';
        self::field('radius','Скругление в px',$c['design']['radius'],'number');
        self::field('retention_days','Срок сохранения выбора (дней)',$c['design']['retention_days'],'number');
        self::checkbox('floating_link','Показывать постоянную кнопку настроек cookie',$c['design']['floating_link']);
        echo '<h3>Живой предпросмотр</h3><p>Шаблон автоматически обновляется при смене оформления. После сохранения дополнительно проверьте баннер на реальном сайте.</p>';
        echo '<p class="rupk-preview-switch"><button type="button" class="button button-primary" data-rupk-preview-size="desktop">Компьютер</button> <button type="button" class="button" data-rupk-preview-size="mobile">Телефон</button></p>';
        echo '<div class="rupk-preview-shell"><div class="rupk-preview-stage"><div class="rupk-preview-placeholder"><div class="rupk-preview-header"></div><div class="rupk-preview-lines"></div></div>';
        echo '<div id="rupk-root" class="rupk-layout-'.esc_attr($c['design']['layout']).' rupk-theme-'.esc_attr($c['design']['theme']).'"><section class="rupk-banner" role="region"><div class="rupk-copy"><h2>Настройки файлов cookie</h2><p>Для работы сайта мы используем необходимые cookie. С вашего разрешения мы также включаем аналитику и другие необязательные технологии.</p><p class="rupk-links"><a href="#">Политика cookie</a><a href="#">Политика ПДн</a></p></div><div class="rupk-actions"><button type="button" class="rupk-primary">Принять все</button><button type="button">Настроить</button><button type="button">Только нужные</button></div></section></div>';
        echo '</div></div><p class="description">Это предпросмотр внешнего вида, а не рабочая форма согласия.</p>';
        self::form_end($step,'Сохранить дизайн');
    }
    public static function dashboard(): void {
        $c=RUPK_Core::config(); self::header('RU Privacy Kit','Единое управление юридическими страницами, согласиями и аналитикой.');
        echo '<div class="rupk-cards">';
        foreach ([['Мастер',$c['complete']?'Завершён':'Не завершён','rupk-wizard'],['Баннер',$c['enabled']?'Включён':'Выключен','rupk-design'],['Режим аналитики',['strict'=>'Сначала согласие','optout'=>'До отказа','advanced'=>'Расширенный'][$c['analytics_mode']],'rupk-services'],['Документы',count(array_filter($c['documents'])).' привязано','rupk-documents']] as $tile) {
            echo '<a href="'.esc_url(admin_url('admin.php?page='.$tile[2])).'" class="rupk-tile"><small>'.esc_html($tile[0]).'</small><strong>'.esc_html($tile[1]).'</strong></a>';
        } echo '</div>';
        self::warnings_list($c);
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=rupk-wizard')).'">Открыть мастер</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=rupk-diagnostics')).'">Диагностика</a></p>';
        self::footer();
    }
    private static function warnings_list(array $c): void {
        $warnings=RUPK_Core::warnings($c);
        if ($warnings) { echo '<div class="rupk-panel rupk-warnings"><h2>Вопросы, которые нужно проверить</h2><ul>'; foreach ($warnings as $w) { echo '<li>'.esc_html($w).'</li>'; } echo '</ul></div>'; }
    }
    public static function review(array $c): void {
        echo '<h2>8. Проверка и завершение установки</h2><p>Проверьте введённые сведения и тексты документов. После вашего подтверждения страницы будут опубликованы, политика персональных данных назначена в настройках WordPress, а cookie-баннер включён.</p>';
        $items=RUPK_Core::review_items($c);
        $todo=array_values(array_filter($items,static fn($item)=>$item['status']==='todo'));
        $review=array_values(array_filter($items,static fn($item)=>$item['status']==='review'));
        $ready=!$todo;
        echo '<div class="rupk-review-summary">';
        echo '<div class="rupk-review-tile rupk-review-ok"><strong>Готово</strong><span>'.esc_html($c['operator']['name']?:'Оператор ещё не указан').'</span><small>Режим: '.esc_html(['strict'=>'Сначала согласие','optout'=>'Аналитика до отказа','advanced'=>'Расширенный Google'][$c['analytics_mode']]).'</small></div>';
        echo '<div class="rupk-review-tile '.($ready?'rupk-review-ok':'rupk-review-bad').'"><strong>Нужно заполнить</strong><span>'.count($todo).' '.esc_html(self::plural(count($todo),['вопрос','вопроса','вопросов'])).'</span><small>'.($ready?'Обязательные поля заполнены':'Исправьте для публикации').'</small></div>';
        echo '<div class="rupk-review-tile rupk-review-warn"><strong>Рекомендуется проверить</strong><span>'.count($review).' '.esc_html(self::plural(count($review),['вопрос','вопроса','вопросов'])).'</span><small>Не блокируют создание черновиков</small></div></div>';
        if ($todo) {
            echo '<section class="rupk-review-section"><h3>Нужно заполнить</h3><p>Эти вопросы необходимо решить до публикации документов и включения баннера.</p><div class="rupk-review-items">';
            foreach ($todo as $item) { self::review_row($item,'todo'); }
            echo '</div></section>';
        } else {
            echo '<p class="rupk-review-ready">Обязательные сведения заполнены. Ниже готовы тексты документов и кнопка завершения установки.</p>';
        }
        if ($review) {
            echo '<details class="rupk-review-section rupk-review-advice"><summary>Рекомендуется проверить ('.count($review).')</summary><p>Это не результаты автоматического сканирования. Ниже указаны внешние инструменты, выбранные в настройках, и риски их подключения.</p><div class="rupk-review-items">';
            foreach ($review as $item) { self::review_row($item,'review'); }
            echo '</div></details>';
        }
        echo '<section class="rupk-review-section"><h3>Сводка конфигурации</h3><p><strong>Сайт:</strong> '.esc_html(home_url('/')).'</p><p><strong>Владелец:</strong> '.esc_html($c['operator']['name']?:'Не заполнено').'</p><p><strong>Сервисы:</strong> ';
        $names=array_map(static fn($s)=>$s['label'],RUPK_Core::active_services($c));
        echo esc_html($names?implode(', ',$names):'Не выбраны').'.</p></section>';
        $plan=RUPK_Publisher::plan($c);
        echo '<section class="rupk-review-section"><h3>Юридические страницы</h3><p>Документы сформированы из ваших ответов. Это тексты, которые будут опубликованы на сайте; служебные подсказки в них не включаются.</p>';
        foreach (RUPK_Publisher::types($c) as $type) {
            $label=RUPK_Documents::labels()[$type];
            $job=$plan['jobs'][$type]??null;
            $status=$job?(['create'=>'Будет создана и опубликована','replace_stock'=>'Будет заменён стандартный шаблон WordPress','update_generated'=>'Будет обновлена и опубликована','publish_generated'=>'Будет опубликована','keep_manual'=>'Существующая опубликованная страница сохранится'][$job['action']]??''):'Требуется действие';
            echo '<details class="rupk-review-advice rupk-document-preview"><summary>'.esc_html($label).' — '.esc_html($status).'</summary>';
            if ($job && $job['action']==='keep_manual') {
                echo '<p>Эта страница заполнена вручную. Плагин не изменит её содержимое.</p><p><a href="'.esc_url(get_edit_post_link($job['id'])).'">Проверить страницу в WordPress</a></p>';
            } else {
                echo '<div class="rupk-document-body">'.wp_kses_post(RUPK_Documents::build($type,$c)).'</div>';
            }
            echo '</details>';
        }
        echo '</section>';
        if ($plan['errors']) {
            echo '<section class="rupk-review-section rupk-blockers"><h3>Перед публикацией требуется действие</h3><ul>';
            foreach ($plan['errors'] as $error) { echo '<li>'.esc_html($error).'</li>'; }
            echo '</ul><p><a href="'.esc_url(admin_url('admin.php?page=rupk-documents')).'">Открыть привязку страниц</a></p></section>';
        }
        if ($ready && !$plan['errors']) {
            echo '<div class="rupk-review-final"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo '<input type="hidden" name="action" value="rupk_finish">';
            wp_nonce_field('rupk_finish');
            self::checkbox('approved','Я проверил(а) сведения о владельце сайта, используемых сервисах и тексты документов. Опубликовать страницы и включить баннер.',false);
            if ($plan['refresh']) {
                echo '<p>На сайте уже есть документы, созданные предыдущей версией плагина. Новая редакция заменит их старый текст.</p>';
                self::checkbox('refresh_generated','Разрешаю обновить ранее созданные плагином страницы',false);
            }
            if ($plan['edited']) {
                echo '<p><strong>Обнаружены ручные изменения текстов</strong> на страницах, ранее созданных плагином. Их замена требует отдельного разрешения.</p>';
                self::checkbox('overwrite_edited','Я сохранил(а) нужные изменения и разрешаю заменить их новой редакцией',false);
            }
            if ($plan['stock_id'] || self::plan_has_stock($plan)) {
                echo '<p>Обнаружена стандартная политика WordPress с пометками «Предлагаемый текст». Её необходимо заменить готовой политикой либо убрать из публичного доступа.</p>';
                self::checkbox('replace_stock','Разрешаю заменить стандартный текст WordPress и убрать старую страницу-подсказку из публичного доступа, если она дублирует новую',false);
            }
            submit_button('Завершить установку и опубликовать '.count(RUPK_Publisher::types($c)).' страницы','primary','submit',false);
            echo '</form><p class="description">Не изменяем страницы, которые вы написали самостоятельно. При ошибке баннер не включится.</p></div>';
        } else {
            echo '<div class="rupk-review-final"><p>После исправления обязательных пунктов здесь появится кнопка «Завершить установку».</p></div>';
        }
    }
    private static function plan_has_stock(array $plan): bool {
        foreach ($plan['jobs'] as $job) { if ($job['action']==='replace_stock') { return true; } }
        return false;
    }
    private static function plural(int $n,array $forms): string {
        $n=abs($n)%100; $last=$n%10;
        return ($n>10 && $n<20)?$forms[2]:(($last===1)?$forms[0]:(($last>=2 && $last<=4)?$forms[1]:$forms[2]));
    }
    private static function review_row(array $item,string $kind): void {
        echo '<div class="rupk-review-row"><span class="rupk-review-symbol" aria-hidden="true">'.($kind==='todo'?'!':'i').'</span><div><strong>'.esc_html($item['title']).'</strong><p>'.esc_html($item['detail']).'</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step='.(int)$item['step'])).'">'.($kind==='todo'?'Исправить':'Проверить').'</a></div>';
    }
    public static function design(): void { self::header('Cookie-баннер'); echo '<div class="rupk-panel">'; self::design_fields(RUPK_Core::config(),7); echo '</div>'; self::footer(); }
    public static function services(): void { self::header('Аналитика и сервисы'); echo '<div class="rupk-panel">'; self::analytics_fields(RUPK_Core::config(),4); echo '<hr><p><a href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=3')).'">Изменить список счётчиков и их ID в мастере</a></p></div>'; self::footer(); }
    /** Publication requires matching checked settings and current approved documents. */
    private static function required_documents(array $c): array {
        $types=['cookie','privacy','terms'];
        if (!empty($c['commercial']['paid'])) { $types[]='offer'; }
        return $types;
    }
    private static function generated_stale(array $c): array {
        $stale=[];
        foreach (self::required_documents($c) as $type) {
            $id=(int)($c['documents'][$type]??0);
            if (!$id || !get_post($id) || get_post_type($id)!=='page') { continue; }
            $rev=get_post_meta($id,'_rupk_generated_revision',true);
            if ((string)$rev!=='' && (
                (int)$rev!==(int)$c['revision'] ||
                (int)get_post_meta($id,'_rupk_generated_template_version',true)!==RUPK_Documents::TEMPLATE_VERSION
            )) { $stale[$type]=$id; }
        }
        return $stale;
    }
    private static function document_blockers(array $c): array {
        $blocked=[];
        foreach (self::required_documents($c) as $type) {
            $id=(int)($c['documents'][$type]??0); $page=$id?get_post($id):null;
            if (!$page || get_post_type($id)!=='page') {
                $blocked[]=['title'=>'Нет страницы: '.RUPK_Documents::labels()[$type], 'detail'=>'Создайте черновик в мастере или привяжите существующую страницу WordPress.','url'=>admin_url('admin.php?page=rupk-wizard&step=8'),'action'=>'Создать'];
            } elseif ((string)get_post_meta($id,'_rupk_generated_revision',true)==='' && get_post_status($id)!=='publish') {
                $blocked[]=['title'=>'Не опубликована: '.RUPK_Documents::labels()[$type], 'detail'=>'Вы привязали собственную страницу. Плагин не публикует и не перезаписывает её автоматически.','url'=>get_edit_post_link($id),'action'=>'Открыть страницу'];
            }
        }
        return $blocked;
    }
    public static function documents(): void {
        $c=RUPK_Core::config(); self::header('Юридические документы','Существующие страницы не перезаписываются автоматически.');
        echo '<span id="rupk-publish-check" tabindex="-1"></span>';
        $todo=array_values(array_filter(RUPK_Core::review_items($c),static fn($item)=>$item['status']==='todo'));
        $plan=RUPK_Publisher::plan($c);
        $stale=self::generated_stale($c);
        if ($todo || $plan['errors']) {
            echo '<section class="rupk-panel rupk-blockers" aria-labelledby="rupk-publish-heading"><h2 id="rupk-publish-heading">Пока нельзя включить баннер</h2>';
            echo '<p>Это не ошибка ваших галочек. Не завершены '.count($todo).' '.esc_html(self::plural(count($todo),['обязательный пункт','обязательных пункта','обязательных пунктов'])).' настроек и '.count($blocked).' '.esc_html(self::plural(count($blocked),['вопрос по документу','вопроса по документам','вопросов по документам'])).'. Перейдите по ссылкам, сохраните исправления и вернитесь сюда. Черновики останутся на месте.</p>';
            echo '<div class="rupk-review-items">';
            foreach ($todo as $item) { self::review_row($item,'todo'); }
            foreach ($plan['errors'] as $reason) { echo '<div class="rupk-review-row"><span class="rupk-review-symbol">!</span><div><strong>'.esc_html($reason).'</strong></div><a class="button" href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=8')).'">Исправить</a></div>'; }
            echo '</div><p><a class="button" href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=8')).'">Открыть итоговую проверку мастера</a></p></section>';
        } else {
            echo '<div class="notice notice-success inline rupk-doc-ready"><p><strong>Настройки готовы к публикации.</strong> Проверьте тексты страниц ниже. Если черновики устарели, потребуется отдельное разрешение на обновление.</p></div>';
        }
        echo '<div class="rupk-panel"><table class="widefat striped"><thead><tr><th>Документ</th><th>Страница</th><th>Действия</th></tr></thead><tbody>';
        foreach (RUPK_Documents::labels() as $key=>$label) {
            if ($key==='offer' && !$c['commercial']['paid']) { continue; }
            $id=(int)$c['documents'][$key]; $p=$id?get_post($id):null;
            echo '<tr><td><strong>'.esc_html($label).'</strong></td><td>'.($p?esc_html($p->post_status).' · #'.$id:'Не привязана').'</td><td>';
            if ($p) {
                echo '<a href="'.esc_url(get_edit_post_link($id)).'">Редактировать</a> · <a href="'.esc_url(get_preview_post_link($p)).'" target="_blank" rel="noopener">Посмотреть</a>';
            } else { echo 'Создайте черновик либо привяжите свою страницу'; }
            if ($p && isset($stale[$key])) {
                echo '<p class="rupk-outdated"><strong>Текст устарел после изменения настроек. Перед публикацией его необходимо сверить и обновить.</strong></p>';
                if (function_exists('wp_text_diff')) {
                    $diff=wp_text_diff(wp_strip_all_tags($p->post_content), wp_strip_all_tags(RUPK_Documents::build($key,$c)), ['title'=>'Текущий текст','title_right'=>'Предлагаемая редакция']);
                    if ($diff) { echo '<details class="rupk-document-diff"><summary>Показать различия</summary>'.wp_kses_post($diff).'</details>'; }
                } else {
                    echo '<details><summary>Показать текст новой редакции</summary><textarea readonly rows="10" style="width:100%">'.esc_textarea(wp_strip_all_tags(RUPK_Documents::build($key,$c))).'</textarea></details>';
                }
            }
            echo '<form class="rupk-inline" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rupk_bind"><input type="hidden" name="type" value="'.esc_attr($key).'">'; wp_nonce_field('rupk_bind');
            echo '<select aria-label="Привязать страницу" name="page_id"><option value="0">Выбрать страницу…</option>';
            foreach (get_pages(['post_status'=>['publish','draft','private'],'sort_column'=>'post_title','hierarchical'=>false]) as $page) {
                echo '<option value="'.(int)$page->ID.'"'.selected($id,$page->ID,false).'>'.esc_html($page->post_title).' (#'.(int)$page->ID.')</option>';
            }
            echo '</select><button class="button" type="submit">Привязать</button></form></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><strong>Внимание:</strong> документы автоматически перечисляют выбранные сервисы. Точный состав cookie и сроки хранения могут требовать дополнительной проверки; автоматическая генерация не подтверждает их полноту. Проверьте также получателей, основания, локализацию и договорные условия.</p>';
        if ($todo || $plan['errors']) {
            echo '<div class="rupk-publish-pending"><strong>Публикация приостановлена.</strong> Исправьте пункты выше; галочки подтверждения появятся, когда документы и настройки будут готовы. <a href="'.esc_url(admin_url('admin.php?page=rupk-wizard&step=8')).'">Вернуться к проверке</a>.</div>';
        } else {
            echo '<form id="rupk-publish-form" action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="rupk_publish">'; wp_nonce_field('rupk_publish');
            self::checkbox('reviewed','Я проверил(а) фактические сведения и тексты всех документов',false);
            if ($plan['refresh']) {
                echo '<div class="rupk-stale-confirm"><p><strong>Есть устаревшие тексты ('.count($plan['refresh']).').</strong> Плагин обновит только созданные им страницы, в том числе черновики. Ручные изменения их текста могут быть заменены: перед подтверждением сравните редакции и при необходимости сохраните собственную копию.</p>';
                self::checkbox('refresh_generated','Я проверил(а) различия и разрешаю обновить эти страницы до публикации',false);
                echo '</div>';
            }
            if ($plan['edited']) { self::checkbox('overwrite_edited','Разрешаю заменить вручную изменённый текст в ранее созданных плагином страницах',false); }
            if ($plan['stock_id'] || self::plan_has_stock($plan)) { self::checkbox('replace_stock','Разрешаю заменить стандартную политику WordPress и убрать старую страницу-подсказку из публичного доступа',false); }
            submit_button('Опубликовать проверенные документы и включить баннер','primary');
            echo '</form>';
        }
        echo '</div>'; self::footer();
    }
    public static function journal(): void {
        $c=RUPK_Core::config(); self::header('Журнал согласий','Не содержит открытых IP и User-Agent. Не предназначен для идентификации конкретного посетителя.');
        if (empty($c['log_consent'])) { echo '<div class="rupk-panel"><p>Журнал отключён. Его можно включить в настройках.</p></div>'; self::footer(); return; }
        global $wpdb;
        $table=$wpdb->prefix.'rupk_consent_log';
        $rows=$wpdb->get_results("SELECT consent_id,revision,action,categories,created_at FROM $table ORDER BY id DESC LIMIT 100",ARRAY_A);
        echo '<div class="rupk-panel"><table class="widefat striped"><thead><tr><th>UTC</th><th>ID</th><th>Редакция</th><th>Событие</th><th>Функц. / Аналит. / Маркет.</th></tr></thead><tbody>';
        foreach ($rows as $r) { echo '<tr>'; foreach (['created_at','consent_id','revision','action','categories'] as $key) { echo '<td>'.esc_html((string)$r[$key]).'</td>'; } echo '</tr>'; }
        echo '</tbody></table><p>Последние 100 записей; автоматическое удаление по TTL.</p></div>'; self::footer();
    }
    public static function diagnostics(): void {
        $c=RUPK_Core::config(); self::header('Диагностика','Статусы определяются по настройкам плагина, а не по полноценному сетевому сканированию.');
        self::warnings_list($c);
        echo '<div class="rupk-panel"><h2>Управление сервисами</h2><table class="widefat striped"><thead><tr><th>Сервис</th><th>Статус</th><th>Эффективный режим</th></tr></thead><tbody>';
        foreach (RUPK_Core::active_services($c) as $id=>$s) {
            $managed=RUPK_Core::managed($c,$id);
            $effective='Строгий opt-in';
            if ($c['analytics_mode']==='optout' && $managed && RUPK_Core::basis_confirmed($c,$id)) { $effective=$id==='google'?'Google: до отказа — только cookieless при denied':'До отказа (подтверждено в админке)'; }
            if ($c['analytics_mode']==='advanced' && $id==='google' && $managed && RUPK_Core::basis_confirmed($c,$id)) { $effective='Google Advanced Consent Mode'; }
            if (!$managed) { $effective='Не управляется плагином'; }
            echo '<tr><td>'.esc_html($s['label']).'</td><td>'.($managed?'Скрипт плагина (нужно исключить дубли)':'Внешний / требует проверки').'</td><td>'.esc_html($effective).'</td></tr>';
        } echo '</tbody></table><p>Важно: если счётчики установлены темой, другим плагином или GTM, RU Privacy Kit не способен гарантировать их блокирование. Проверяйте Network и Cookies в DevTools.</p></div>'; self::footer();
    }
    public static function settings(): void {
        $c=RUPK_Core::config(); self::header('Настройки');
        echo '<div class="rupk-panel">'; self::form_start(9);
        self::checkbox('log_consent','Вести минимальный журнал согласий',$c['log_consent'],'Хранится локально в БД WP; по умолчанию выключен.');
        self::field('log_retention_days','Удалять записи через (дней)',$c['log_retention_days'],'number');
        self::form_end(9,'Сохранить настройки');
        echo '<hr><form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="rupk_disable">'; wp_nonce_field('rupk_disable');
        submit_button($c['enabled']?'Выключить баннер (документы останутся)':'Баннер выключен','secondary','submit',false,['disabled'=>!$c['enabled']]); echo '</form>';
        echo '<p>Удаление плагина по умолчанию не удаляет опубликованные юридические страницы. Журнал и настройки при полном удалении очищаются через uninstall.php.</p></div>'; self::footer();
    }
    private static function guard(string $action): void {
        if (!current_user_can('manage_options')) { wp_die('Недостаточно прав.',403); }
        check_admin_referer($action);
    }
    private static function redirect(string $page,string $msg='',int $step=0): void {
        $url=admin_url('admin.php?page='.$page.($step?'&step='.$step:''));
        if ($msg) { $url=add_query_arg('rupk_msg',$msg,$url); }
        wp_safe_redirect($url); exit;
    }
    public static function save(): void {
        $step=isset($_POST['step'])?absint($_POST['step']):0;
        if (!in_array($step,[1,2,3,4,5,6,7,9],true)) { wp_die('Некорректный шаг.',400); }
        self::guard('rupk_save_'.$step);
        $p=wp_unslash($_POST); $c=RUPK_Core::config(); $v=[];
        $text=static fn($name,$fallback='')=>sanitize_text_field((string)($p[$name]??$fallback));
        $set=static fn($val,$allowed,$default)=>in_array($val,$allowed,true)?$val:$default;
        if ($step===1) {
            $v['site_kind']=$set($text('site_kind'),['information','blog','corporate','landing','catalog','shop','saas','other'],'information');
            $v['operator_kind']=$set($text('operator_kind'),['ip','company','self','person','other'],'ip');
            $op=[];
            foreach ($c['operator'] as $k=>$old) { $raw=isset($p['operator'][$k])?$p['operator'][$k]:''; $op[$k]=in_array($k,['email','support'],true)?sanitize_email($raw):sanitize_text_field($raw); }
            $v['operator']=$op;
        } elseif ($step===2) {
            $v['purposes']=[];
            foreach (RUPK_Documents::purpose_map() as $id=>$_) { if (!empty($p['purposes'][$id])) { $v['purposes'][]=$id; } }
        } elseif ($step===3) {
            $v['services']=[];
            foreach (RUPK_Core::registry() as $id=>$_) { $v['services'][$id]=!empty($p['services'][$id]); }
            $v['yandex_id']=$text('yandex_id'); $v['google_id']=strtoupper($text('google_id')); $v['gtm_id']=strtoupper($text('gtm_id'));
            $v['cookie_inventory']=sanitize_textarea_field((string)($p['cookie_inventory']??''));
            $v['inventory_reviewed']=!empty($p['inventory_reviewed']);
        } elseif ($step===4) {
            $v['analytics_mode']=$set($text('analytics_mode'),['strict','optout','advanced'],'strict');
            foreach (['yandex','google'] as $id) {
                // Hidden panels, strict mode, and deselected services retain previous legal details.
                $needed=$v['analytics_mode']==='optout' || ($v['analytics_mode']==='advanced' && $id==='google');
                if (!$needed || empty($c['services'][$id]) || !isset($p['basis'][$id])) { continue; }
                $basis=sanitize_key((string)$p['basis'][$id]);
                $v['legal'][$id]=[
                    'basis'=>$set($basis,['','contract','legitimate','law','other'],''),
                    'purpose'=>sanitize_textarea_field((string)($p['basis_purpose'][$id]??$c['legal'][$id]['purpose'])),
                    'details'=>sanitize_textarea_field((string)($p['basis_details'][$id]??$c['legal'][$id]['details'])),
                    'confirmed'=>!empty($p['basis_confirmed'][$id]),
                ];
            }
        } elseif ($step===5) {
            $v['data']=['hosting'=>$set($text('hosting'),['unknown','ru','other'],'unknown'), 'crossborder'=>$set($text('crossborder'),['unknown','yes','no'],'unknown'),
                'retention'=>sanitize_textarea_field((string)($p['retention']??'')),'security'=>sanitize_textarea_field((string)($p['security']??''))];
        } elseif ($step===6) {
            $v['commercial']=['paid'=>!empty($p['paid']),'kind'=>$set($text('commercial_kind'),['saas','services','digital','goods'],'saas')];
            foreach (['subject','price','accept','delivery','refund','support'] as $k) { $v['commercial'][$k]=sanitize_textarea_field((string)($p['commercial'][$k]??'')); }
        } elseif ($step===7) {
            $colors=[];
            foreach (['bg','text','accent'] as $color) { $colors[$color]=sanitize_hex_color((string)($p['color'][$color]??''))?:''; }
            $v['design']=array_merge($colors,['layout'=>$set($text('layout'),['bottom','card','modal','top','minimal'],'bottom'), 'theme'=>$set($text('theme'),['inherit','light','dark','system','custom'],'inherit'),
                'radius'=>max(0,min(32,absint($p['radius']??14))), 'retention_days'=>max(1,min(365,absint($p['retention_days']??180))), 'floating_link'=>!empty($p['floating_link'])]);
        } elseif ($step===9) {
            $v['log_consent']=!empty($p['log_consent']); $v['log_retention_days']=max(1,min(365,absint($p['log_retention_days']??180)));
        }
        if ($step!==9) { $v['wizard_step']=min(8,$step+1); }
        RUPK_Core::save($v,$step!==7&&$step!==9);
        if ($step===9) { self::redirect('rupk-settings','Настройки сохранены.'); }
        if ($step===7 && isset($p['from_design'])) { self::redirect('rupk-design','Дизайн сохранён.'); }
        if ($step===4 && isset($p['from_services'])) { self::redirect('rupk-services','Режим сохранён.'); }
        self::redirect('rupk-wizard','Шаг сохранён.',min(8,$step+1));
    }
    public static function generate(): void {
        self::guard('rupk_generate'); $c=RUPK_Core::config();
        RUPK_Documents::create_drafts($c);
        self::redirect('rupk-documents','Черновики подготовлены; проверьте их и подтвердите публикацию.');
    }
    public static function bind(): void {
        self::guard('rupk_bind');
        $type=isset($_POST['type'])?sanitize_key(wp_unslash($_POST['type'])):'';
        $id=isset($_POST['page_id'])?absint($_POST['page_id']):0;
        if (!isset(RUPK_Documents::labels()[$type]) || !$id || get_post_type($id)!=='page') { self::redirect('rupk-documents','Выберите существующую страницу WordPress.'); }
        RUPK_Core::save(['documents'=>[$type=>$id],'documents_review_required'=>true,'enabled'=>false,'complete'=>false]);
        self::redirect('rupk-documents','Страница привязана без изменения её содержимого.');
    }
    public static function publish(): void {
        self::guard('rupk_publish');
        self::finish_action('rupk-documents', !empty($_POST['reviewed']));
    }
    public static function finish(): void {
        self::guard('rupk_finish');
        self::finish_action('rupk-wizard&step=8', !empty($_POST['approved']));
    }
    private static function finish_action(string $target,bool $approved): void {
        $c=RUPK_Core::config();
        $options=[
            'approved'=>$approved,
            'refresh'=>!empty($_POST['refresh_generated']),
            'overwrite_edited'=>!empty($_POST['overwrite_edited']),
            'replace_stock'=>!empty($_POST['replace_stock']),
        ];
        $result=RUPK_Publisher::finish($c,$options);
        if (is_wp_error($result)) {
            $url=add_query_arg('rupk_error_detail',$result->get_error_message(),admin_url('admin.php?page='.$target));
            wp_safe_redirect($url); exit;
        }
        self::redirect('rupk','Установка завершена: документы опубликованы, политика назначена в WordPress, cookie-баннер включён.');
    }
    private static function redirect_error(string $reason): void {
        wp_safe_redirect(add_query_arg('rupk_error', $reason, admin_url('admin.php?page=rupk-documents')).'#rupk-publish-check');
        exit;
    }
    public static function disable(): void { self::guard('rupk_disable'); RUPK_Core::save(['enabled'=>false]); self::redirect('rupk-settings','Баннер выключен.'); }
}