<?php
if (!defined('ABSPATH')) { exit; }
final class RUPK_Documents {
    public const TEMPLATE_VERSION = 3;
    public static function labels(): array { return ['cookie'=>'Политика использования файлов cookie','privacy'=>'Политика в отношении обработки персональных данных','terms'=>'Условия использования сайта','offer'=>'Публичная оферта']; }
    public static function slug(string $type): string { return ['cookie'=>'cookie-policy','privacy'=>'privacy-policy','terms'=>'terms-of-use','offer'=>'public-offer'][$type]??''; }
    public static function url(string $type,array $c): string { $id=(int)($c['documents'][$type]??0); return $id && get_post_status($id)==='publish' ? get_permalink($id) : ''; }
    public static function build(string $type,array $c): string {
        if (!isset(self::labels()[$type])) { return ''; }
        $site=esc_html(home_url('/')); $name=esc_html($c['operator']['name']);
        $email=esc_html($c['operator']['email']); $address=esc_html($c['operator']['address']);
        $date=esc_html(wp_date('d.m.Y')); $mode=esc_html(self::mode_text($c));
        $cookie=esc_url(self::url('cookie',$c) ?: home_url('/cookie-policy/'));
        $privacy=esc_url(self::url('privacy',$c) ?: home_url('/privacy-policy/'));
        $start='<p><strong>Редакция от '.$date.'.</strong> Оператор: '.$name.'. Сайт: '.$site.'.</p>';
        $contact='<p>Обращения по вопросам обработки данных направляются по адресу электронной почты <a href="mailto:'.esc_attr($c['operator']['email']).'">'.$email.'</a>'.($address?', либо по почтовому адресу: '.$address:'').'.</p>';
        if ($type==='cookie') {
            $t='<h2>1. Общие сведения</h2><p>Сайт может использовать cookie, localStorage и аналогичные технологии для сохранения настроек, обеспечения работы функций сайта и, в зависимости от конфигурации, статистики посещаемости. Идентификаторы и технические сведения в некоторых случаях могут относиться к персональным данным.</p>';
            $t.='<h2>2. Используемые категории</h2><p><strong>Необходимые:</strong> поддержание работы сайта, безопасности и сохранение выбора посетителя. Они не предназначены для рекламного профилирования.</p>';
            $services=self::service_rows($c);
            $t.='<p>На сайте применяются следующие дополнительные технологии и сервисы:</p>'.$services;
            $t.='<h2>3. Порядок использования аналитики</h2><p>'.$mode.'</p>';
            $t.='<p>Отсутствие ответа либо закрытие уведомления не считается согласием. Информация о правовых основаниях обработки персональных данных приведена в <a href="'.$privacy.'">Политике обработки персональных данных</a>.</p>';
            $t.='<h2>4. Реестр cookie и сроки</h2><table><thead><tr><th>Технология</th><th>Поставщик</th><th>Цель</th><th>Срок действия</th></tr></thead><tbody>';
            $t.='<tr><td>rupk_consent</td><td>Сайт</td><td>Сохранение выбора категорий</td><td>'.(int)$c['design']['retention_days'].' дней</td></tr>';
            if (!empty($c['inventory_reviewed']) && RUPK_Core::valid_inventory((string)$c['cookie_inventory'])) {
                foreach (preg_split('/\r?\n/',trim($c['cookie_inventory'])) as $line) {
                    $cells=array_map('trim',explode('|',$line));
                    if ($cells[0]==='rupk_consent') { continue; } // Already listed with its configured TTL.
                    $t.='<tr><td>'.esc_html($cells[0]).'</td><td>'.esc_html($cells[1]).'</td><td>'.esc_html($cells[2]).' ('.esc_html($cells[3]).')</td><td>'.esc_html($cells[4]).'</td></tr>';
                }
            }
            $t.='</tbody></table>';
            if (!empty($c['inventory_reviewed']) && RUPK_Core::valid_inventory((string)$c['cookie_inventory'])) {
                $t.='<p>Сроки действия указанных файлов cookie определяются их назначением и настройками соответствующих сервисов.</p>';
            } else {
                $t.='<p>Помимо необходимых cookie, перечисленных в таблице, сторонние сервисы могут использовать собственные файлы cookie. Их наименования и сроки действия определяются настройками и условиями соответствующих сервисов; дополнительные сведения приведены в документации поставщиков, указанных выше.</p>';
            }

            $t.='<h2>5. Управление и отзыв</h2><p>Вы можете изменить решение в любой момент через <a href="#rupk-cookie-preferences" class="rupk-open-preferences">настройки cookie</a>. Отказ прекращает дальнейшую работу скриптов, которыми технически управляет сайт; ранее отправленные внешним поставщикам данные не могут быть автоматически удалены таким отказом. Удалить файлы cookie можно также через настройки браузера.</p>';
            $t.='<h2>6. Передача данных и изменения</h2><p>Сведения могут передаваться поставщикам сервисов, указанным в настоящей Политике, в пределах, необходимых для предоставления соответствующих функций. При изменении перечня сервисов или условий их использования на сайте публикуется новая редакция Политики.</p>'.$contact;
            return $start.$t;
        }
        if ($type==='privacy') {
            $t='<h2>1. Общие положения</h2><p>Настоящая Политика описывает порядок обработки персональных данных посетителей сайта '.$site.' оператором '.$name.' в соответствии с Федеральным законом № 152-ФЗ «О персональных данных».</p>';
            $t.='<h2>2. Цели, категории и основания обработки</h2><p>Персональные данные обрабатываются для перечисленных ниже целей с учётом характера взаимодействия посетителя с сайтом и применимых правовых оснований.</p>';
            $t.='<table><thead><tr><th>Цель</th><th>Категории данных и субъектов</th><th>Правовые основания</th><th>Срок/критерий</th></tr></thead><tbody>';
            foreach (self::purpose_map() as $id=>$v) { if (in_array($id,$c['purposes'],true)) {
                $t.='<tr><td>'.esc_html($v[0]).'</td><td>'.esc_html($v[1]).'</td><td>'.esc_html($v[2]).'</td><td>'.esc_html($c['data']['retention']).'</td></tr>';
            } }
            if (!empty($c['services']['yandex']) || !empty($c['services']['google'])) {
                $t.='<tr><td>Анализ посещаемости</td><td>Посетители; сетевые, технические сведения и идентификаторы (в зависимости от сервиса)</td><td>'.esc_html($c['analytics_mode']==='strict'?'Согласие посетителя': self::analytics_basis_text($c)).'</td><td>'.esc_html($c['data']['retention']).'</td></tr>';
            }
            $t.='</tbody></table>';
            $t.='<h2>3. Источники и действия с данными</h2><p>Данные могут поступать непосредственно от посетителя при отправке форм и использовании функций сайта, а также автоматически от браузера при обращении к веб-ресурсам. В зависимости от цели могут выполняться сбор, запись, систематизация, хранение, использование, предоставление уполномоченным получателям, удаление и уничтожение данных.</p>';
            $t.='<h2>4. Получатели и аналитика</h2>'.self::service_rows($c).'<p><strong>Порядок использования аналитики:</strong> '.$mode.'</p><p>Полный состав используемых cookie и способы управления ими описаны в <a href="'.$cookie.'">Политике cookie</a>.</p>';
            $hosting=['ru'=>'Основная база персональных данных располагается на территории Российской Федерации.','other'=>'При обработке данных используется инфраструктура за пределами Российской Федерации. Трансграничная передача производится в случаях и порядке, предусмотренных применимым законодательством.','unknown'=>'Персональные данные обрабатываются с соблюдением применимых требований российского законодательства.'];
            $cross=['yes'=>'При использовании иностранных сервисов возможна трансграничная передача персональных данных в соответствии с требованиями законодательства РФ.','no'=>'Трансграничная передача персональных данных оператором не осуществляется.','unknown'=>'Условия передачи данных определяются используемыми сервисами и требованиями действующего законодательства.'];
            $t.='<h2>5. Инфраструктура и передача</h2><p>'.esc_html($hosting[$c['data']['hosting']]).' '.esc_html($cross[$c['data']['crossborder']]).'</p>';
            $t.='<h2>6. Организационные и технические меры</h2><p>'.($c['data']['security']?nl2br(esc_html($c['data']['security'])):'Оператор принимает необходимые правовые, организационные и технические меры по защите персональных данных.').'</p>';
            $t.='<h2>7. Права субъекта персональных данных</h2><p>Посетитель вправе обратиться с запросом о получении предусмотренной законом информации об обработке его данных, об их уточнении, блокировании или уничтожении в предусмотренных случаях, а также отозвать согласие. Оператор рассматривает обращения в сроки и порядке, установленные законодательством. Для защиты данных может проводиться проверка полномочий заявителя.</p>';
            $t.='<h2>8. Сроки и прекращение обработки</h2><p>'.esc_html($c['data']['retention']).'. При наступлении установленных оснований данные удаляются или уничтожаются в установленном порядке. В отдельных случаях законодательство может требовать их дальнейшего хранения.</p>';
            $t.='<h2>9. Контакты и обновление политики</h2>'.$contact.'<p>Актуальная редакция Политики размещается по настоящему адресу.</p>';
            return $start.$t;
        }
        if ($type==='terms') {
            return $start.'<h2>1. Использование сайта</h2><p>Сайт '.$site.' предоставляет посетителю доступ к размещённым материалам и функциональным возможностям в пределах, предусмотренных владельцем сайта. Использование сервиса должно соответствовать законодательству РФ и правам третьих лиц.</p>'
                .'<h2>2. Материалы и интеллектуальные права</h2><p>Размещённые материалы и элементы сайта могут охраняться законодательством об интеллектуальной собственности. Копирование и использование за пределами установленных законом исключений допускаются с согласия правообладателя.</p>'
                .'<h2>3. Аккаунты и взаимодействие</h2><p>При наличии личных кабинетов посетитель отвечает за сохранность средств доступа. Запрещены неправомерный доступ, нарушение работоспособности сайта, рассылка вредоносных материалов и вмешательство в работу сервера.</p>'
                .'<h2>4. Дополнительные договорные условия</h2><p>Если сайт предлагает платные услуги или товары, отношения сторон могут регулироваться отдельной публичной офертой, применимыми обязательными нормами и индивидуальными договорами.</p>'
                .'<h2>5. Данные и ограничения</h2><p>Обработка персональных данных регулируется <a href="'.$privacy.'">Политикой обработки персональных данных</a>. Ограничения ответственности применяются только в пределах, допускаемых законодательством РФ, включая обязательные правила защиты прав потребителей.</p>'
                .'<h2>6. Контакт и редакция</h2>'.$contact;
        }
        if ($type==='offer') {
            if (empty($c['commercial']['paid'])) { return ''; }
            $o=$c['commercial'];
            $requisites=[];
            if (!empty($c['operator']['inn'])) { $requisites[]='ИНН '.esc_html($c['operator']['inn']); }
            if (!empty($c['operator']['ogrn'])) { $requisites[]='ОГРН/ОГРНИП '.esc_html($c['operator']['ogrn']); }
            $requisites_text=$requisites ? ' Реквизиты: '.implode(', ',$requisites).'.' : '';
            $t='';
            $sections=[
                '1. Оферент и общие положения'=>'Настоящее предложение исходит от '.$name.'.'.$requisites_text,
                '2. Предмет договора'=>$o['subject'],
                '3. Стоимость и расчёты'=>$o['price'],
                '4. Порядок акцепта'=>$o['accept'],
                '5. Порядок и сроки исполнения'=>$o['delivery'],
                '6. Отказ, возврат и обязательные права потребителей'=>$o['refund'],
                '7. Персональные данные'=>'Обработка персональных данных в связи с договором осуществляется в соответствии с <a href="'.$privacy.'">политикой обработки персональных данных</a> и применимым законодательством.',
                '8. Обращения и споры'=>'Обращения направляются по адресу: '.$email.'. Правила разрешения споров определяются законодательством РФ. Не ограничиваются права, которые нельзя исключить соглашением сторон.',
            ];
            foreach ($sections as $h=>$content) { $t.='<h2>'.esc_html($h).'</h2><p>'.($h==='7. Персональные данные'?$content:esc_html($content)).'</p>'; }
            return $start.$t.$contact;
        }
        return '';
    }
    public static function purpose_map(): array {
        return [
            'feedback'=>['Ответы на обращения и заявки','Заявители: имя, контакты и содержание обращения','Согласие либо действия до заключения договора'],
            'accounts'=>['Регистрация и авторизация','Зарегистрированные лица: email, имя, технические идентификаторы','Договор либо согласие'],
            'orders'=>['Оформление заказов и исполнение договора','Покупатели: контакты, сведения о заказе и оплате','Исполнение договора и закон'],
            'newsletter'=>['Информационные и рекламные рассылки','Подписчики: адрес электронной почты или номер телефона','Отдельное согласие'],
            'comments'=>['Комментарии и обратная связь','Комментаторы: имя, контакт, текст и технические сведения','Согласие либо иное предусмотренное основание'],
        ];
    }
    private static function analytics_basis_text(array $c): string {
        $bases = [];
        foreach (['yandex','google'] as $id) {
            if (empty($c['services'][$id])) { continue; }
            if (($c['analytics_mode'] === 'optout' || ($c['analytics_mode'] === 'advanced' && $id === 'google')) && RUPK_Core::basis_confirmed($c,$id)) {
                $basis = $c['legal'][$id]['basis'] ?? '';
                $map = [
                    'legitimate' => 'законный интерес оператора в анализе посещаемости и совершенствовании сайта',
                    'contract' => 'исполнение договора',
                    'law' => 'исполнение обязанностей, возложенных законодательством',
                    'other' => 'иное предусмотренное законом основание',
                ];
                $bases[] = $map[$basis] ?? 'согласие посетителя';
            } else { $bases[] = 'согласие посетителя'; }
        }
        return implode('; ',array_unique($bases)) ?: 'согласие посетителя';
    }
    private static function mode_text(array $c): string {
        if ($c['analytics_mode'] === 'optout') {
            return 'Для анализа посещаемости сайта могут использоваться аналитические технологии на указанных в политике обработки персональных данных правовых основаниях. Посетитель вправе отказаться от дальнейшего аналитического отслеживания через настройки cookie. Рекламные технологии включаются только после отдельного разрешения.';
        }
        if ($c['analytics_mode'] === 'advanced') {
            return 'При использовании совместимых аналитических сервисов до получения разрешения могут передаваться ограниченные технические сигналы без аналитических cookie. После разрешения посетителя включается полноценная аналитика. Посетитель вправе отказаться от дальнейшего отслеживания в настройках cookie.';
        }
        return 'Необязательные аналитические и маркетинговые технологии включаются после разрешения посетителя на соответствующую категорию. Посетитель может изменить своё решение в любое время через настройки cookie.';
    }
    private static function service_rows(array $c): string {
        $active=RUPK_Core::active_services($c);
        if (!$active) { return '<p>Для работы сайта используются необходимые технологии. Дополнительные аналитические и рекламные сервисы в настройках сайта не указаны.</p>'; }
        $descriptions = [
            'yandex'=>'анализ посещаемости', 'google'=>'анализ посещаемости',
            'gtm'=>'управление кодами внешних сервисов', 'vk'=>'рекламная аналитика',
            'local'=>'статистика посещаемости', 'captcha'=>'защита форм от автоматических обращений',
            'video'=>'отображение встраиваемого содержимого', 'custom'=>'дополнительные функции сайта',
        ];
        $out='<ul>';
        foreach ($active as $key=>$service) {
            $out.='<li>'.esc_html($service['label']).' — '.esc_html($descriptions[$key] ?? 'обеспечение работы сайта');
            if ($service['provider'] !== 'Зависит от настройки') { $out.=', поставщик: '.esc_html($service['provider']); }
            if ($service['policy']) { $out.=' (<a rel="noopener noreferrer" href="'.esc_url($service['policy']).'">условия конфиденциальности поставщика</a>)'; }
            $out.='</li>';
        }
        return $out.'</ul>';
    }
    /** Recognize WP's stock privacy-policy suggestion, never a generic user-authored policy. */
    public static function is_wp_suggested_policy(string $body): bool {
        $plain=wp_strip_all_tags($body);
        $patterns=['Предлагаемый текст:', 'Suggested text:', 'Наш адрес сайта:', 'Our website address is:', 'An anonymized string created from your email address'];
        $hits=0;
        foreach ($patterns as $pattern) { if (stripos($plain,$pattern)!==false) { $hits++; } }
        $core_sections=(stripos($plain,'Комментарии')!==false && stripos($plain,'Медиафайлы')!==false) || (stripos($plain,'Comments')!==false && stripos($plain,'Media')!==false);
        return $hits >= 1 && $core_sections;
    }
    public static function is_generated(int $id): bool {
        return (string)get_post_meta($id,'_rupk_generated_revision',true)!=='';
    }
    public static function content_was_edited(int $id): bool {
        if (!self::is_generated($id)) { return false; }
        $hash=(string)get_post_meta($id,'_rupk_content_sha256',true);
        return $hash !== '' && !hash_equals($hash,hash('sha256',(string)get_post_field('post_content',$id)));
    }
    public static function track_generated(int $id,array $c): void {
        update_post_meta($id,'_rupk_generated_revision',(int)$c['revision']);
        update_post_meta($id,'_rupk_generated_template_version',self::TEMPLATE_VERSION);
        update_post_meta($id,'_rupk_content_sha256',hash('sha256',(string)get_post_field('post_content',$id)));
    }
    public static function create_drafts(array $c): array {
        $docs=$c['documents']; $created=[];
        foreach (self::labels() as $key=>$label) {
            if ($key==='offer' && empty($c['commercial']['paid'])) { continue; }
            $id=(int)$docs[$key];
            if ($id && get_post($id)) { continue; }
            // Never replace an existing page with the same slug.
            $existing=get_page_by_path(self::slug($key));
            if ($existing) { $created[$key]='Существующая страница не изменена. Её можно привязать вручную.'; continue; }
            $insert=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>$label,'post_name'=>self::slug($key),'post_content'=>self::build($key,$c),'post_author'=>get_current_user_id()],true);
            if (!is_wp_error($insert)) { $docs[$key]=$insert; self::track_generated((int)$insert,$c); $created[$key]='Создан черновик #'.$insert; }
            else { $created[$key]=$insert->get_error_message(); }
        }
        RUPK_Core::save(['documents'=>$docs]);
        return $created;
    }
}