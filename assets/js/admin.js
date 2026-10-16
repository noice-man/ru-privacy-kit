(function () {
  'use strict';
  function refresh() {
    var paid=document.querySelector('input[name="paid"]');
    if (paid) {
      ['commercial_kind','commercial[subject]','commercial[price]','commercial[accept]','commercial[delivery]','commercial[refund]','commercial[support]']
        .forEach(function(name){var f=document.querySelector('[name="'+name+'"]');if(f&&f.closest('.rupk-field'))f.closest('.rupk-field').hidden=!paid.checked;});
    }
    document.querySelectorAll('[data-rupk-service-field]').forEach(function(block){
      var id=block.getAttribute('data-rupk-service-field');
      var checkbox=document.querySelector('input[name="services['+id+']"]');
      block.hidden=!!checkbox && !checkbox.checked;
    });
    document.querySelectorAll('[data-rupk-tracker-id]').forEach(function(box){
      var id=box.getAttribute('data-rupk-tracker-id');
      var field=document.querySelector('input[name="services['+id+']"]');
      box.hidden=!!field && !field.checked;
    });
    var mode=document.querySelector('input[name="analytics_mode"]:checked');
    var active=0;
    document.querySelectorAll('[data-rupk-basis-panel]').forEach(function(fieldset){
      var modes=fieldset.getAttribute('data-rupk-modes').split(' ');
      var visible=!!mode && modes.indexOf(mode.value)!==-1;
      fieldset.hidden=!visible;
      if(visible) active++;
      // Keep inactive fields out of POST so stored legal data survives mode switches.
      fieldset.querySelectorAll('input,select,textarea').forEach(function(input){input.disabled=!visible;});
    });
    document.querySelectorAll('[data-rupk-legal-mode],[data-rupk-fallback-note]').forEach(function(el){el.hidden=active===0;});
  }
  function preview() {
    var root=document.querySelector('.rupk-preview-shell #rupk-root');
    if (!root) return;
    var layout=document.querySelector('input[name="layout"]:checked');
    var theme=document.querySelector('select[name="theme"]');
    root.className='rupk-layout-'+(layout?layout.value:'bottom')+' rupk-theme-'+(theme?theme.value:'inherit');
    ['bg','text','accent'].forEach(function(key) {
      var input=document.querySelector('input[name="color['+key+']"]');
      root.style.setProperty('--rupk-'+key,input&&/^#[0-9a-f]{6}$/i.test(input.value)?input.value:'');
    });
    var rad=document.querySelector('[name="radius"]');
    root.style.setProperty('--rupk-radius',rad?Math.max(0,Math.min(32,Number(rad.value)||0))+'px':'14px');
  }
  function invalidateReview(target) {
    if (target.matches('[name="cookie_inventory"],[name^="services["]')) {
      var reviewed=document.querySelector('input[name="inventory_reviewed"]');
      if (reviewed) reviewed.checked=false;
    }
    if (target.matches('[name^="basis["],[name^="basis_purpose["],[name^="basis_details["]')) {
      var panel=target.closest('[data-rupk-basis-panel]');
      var confirmed=panel && panel.querySelector('input[name^="basis_confirmed["]');
      if (confirmed) confirmed.checked=false;
    }
  }
  document.addEventListener('click',function(e) {
    var btn=e.target.closest('[data-rupk-preview-size]');if(!btn)return;
    var shell=document.querySelector('.rupk-preview-shell');if(!shell)return;
    var mobile=btn.getAttribute('data-rupk-preview-size')==='mobile';
    shell.classList.toggle('is-mobile',mobile);
    document.querySelectorAll('[data-rupk-preview-size]').forEach(function(b){b.classList.toggle('button-primary',b===btn);});
  });
  document.addEventListener('input',function(e){
    if (e.target.matches('[name="cookie_inventory"],[name^="basis_purpose["],[name^="basis_details["]')) invalidateReview(e.target);
    preview();
  });
  document.addEventListener('change',function(e){
    if (e.target.matches('[name^="services["],[name^="basis["]')) invalidateReview(e.target);
    refresh();preview();
  });
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){refresh();preview();});
  else {refresh();preview();}
})();

/* Beginner cookie inventory: never read, submit, or log cookie values.
 * Browser APIs cannot see HttpOnly, every path, or arbitrary third-party cookies.
 * Provider detection is a hint only and does not constitute legal verification. */
(function () {
  'use strict';
  var editor = document.querySelector('[data-rupk-cookie-editor]');
  if (!editor) {
    document.addEventListener('DOMContentLoaded', init);
  } else init();

  function init() {
    var panel=document.querySelector('[data-rupk-cookie-editor]');
    if (!panel || panel.dataset.rupkEditorReady) return;
    panel.dataset.rupkEditorReady='1';
    var tbody=panel.querySelector('[data-rupk-cookie-rows]');
    var hidden=panel.querySelector('[data-rupk-cookie-serialized]');
    var reviewed=panel.querySelector('input[name="inventory_reviewed"]');
    var details=panel.querySelector('[data-rupk-cookie-advanced]');
    var status=panel.querySelector('[data-rupk-scan-status]');
    var scanButton=panel.querySelector('[data-rupk-scan]');
    var columns=['Название cookie','Поставщик','Цель','Тип','Срок'];
    function refreshProviders() {
      var any=false;
      panel.querySelectorAll('[data-rupk-service-provider]').forEach(function(card){
        var id=card.getAttribute('data-rupk-service-provider');
        var field=document.querySelector('input[name="services['+id+']"]');
        var selected=field ? field.checked : !card.hidden;
        card.hidden=!selected;
        if(selected) any=true;
      });
      var empty=panel.querySelector('[data-rupk-no-services]');
      if(empty) empty.hidden=any;
    }
    document.addEventListener('change',function(ev){
      if(ev.target.matches('[name^="services["]')) refreshProviders();
    });
    refreshProviders();
    var types={necessary:'Необходимые',functional:'Функциональные',analytics:'Аналитика',marketing:'Маркетинг'};

    function input(value,col) {
      var el=document.createElement('input');
      el.type='text';el.dataset.rupkColumn=String(col);el.value=value || '';
      el.setAttribute('aria-label',columns[col]);
      return el;
    }
    function putCell(row,el) { var td=document.createElement('td');td.appendChild(el);row.appendChild(td); }
    function add(values) {
      var row=document.createElement('tr');row.className='rupk-cookie-row';
      for(var i=0;i<3;i++) putCell(row,input(values[i],i));
      var sel=document.createElement('select');sel.dataset.rupkColumn='3';sel.setAttribute('aria-label','Тип cookie');
      Object.keys(types).forEach(function (key) {var op=document.createElement('option');op.value=key;op.textContent=types[key];sel.appendChild(op);});
      sel.value=types[values[3]]?values[3]:'functional';putCell(row,sel);
      putCell(row,input(values[4],4));
      var del=document.createElement('button');del.type='button';del.className='button-link-delete';del.dataset.rupkCookieRemove='1';del.textContent='×';del.setAttribute('aria-label','Удалить cookie');putCell(row,del);
      tbody.appendChild(row);
      return row;
    }
    function values(row) {
      return [0,1,2,3,4].map(function(i){var el=row.querySelector('[data-rupk-column="'+i+'"]');return el?String(el.value || '').trim():'';});
    }
    function synchronize() {
      var lines=[];
      tbody.querySelectorAll('.rupk-cookie-row').forEach(function(row){
        var fields=values(row);
        if(fields[0] && fields[1] && fields[2] && fields[3] && fields[4]) {
          // Pipe is a delimiter in the backward-compatible data format.
          fields=fields.map(function(x){return x.replace(/[|\r\n]/g,' ').trim();});
          lines.push(fields.join(' | '));
        }
      });
      hidden.value=lines.join('\n');
    }
    function markUnreviewed() { if (reviewed) reviewed.checked=false; synchronize(); }
    function classify(name) {
      if (/^(_ym_|_ym$|yandexuid|yuidss|ymex|i$)/i.test(name)) return ['ООО «Яндекс»','Статистика посещаемости','analytics'];
      if (/^(_ga|_gid|_gat|_gcl_|_gac_)/i.test(name)) return ['Google','Статистика посещаемости','analytics'];
      if (/^(_fbp|_fbc|_vk_|remix)/i.test(name)) return ['Сторонний сервис — проверить','Маркетинговый идентификатор — проверить','marketing'];
      if (/^(wordpress_|wordpress_logged_in_|wordpress_test_cookie|wp-settings-|wp-settings-time-|PHPSESSID)/i.test(name)) return ['WordPress / сайт','Вход и работа сайта','necessary'];
      if (name==='rupk_consent') return ['RU Privacy Kit','Сохранение выбора cookie','necessary'];
      return ['Сайт / неизвестный сервис','Назначение нужно уточнить','functional'];
    }
    function expiryText(cookie) {
      var x=cookie && cookie.expires;
      if(typeof x!=='number' || !isFinite(x) || x<=0) return 'Срок не определён';
      var d=Math.ceil((x-Date.now())/86400000);
      if(d<=0) return 'До конца сеанса / уточнить';
      return d===1?'До 1 дня':'До '+d+' дней';
    }
    async function scan() {
      scanButton.disabled=true;
      status.textContent='Проверяем cookie, доступные в этом браузере…';
      var list=[];
      try {
        // cookieStore may expose expiry, when supported by the browser.
        if(window.cookieStore && typeof window.cookieStore.getAll==='function') {
          var result=await window.cookieStore.getAll();
          list=result.map(function(c){return {name:c.name,expires:c.expires};});
        } else {
          list=document.cookie.split(';').map(function(item){return {name:item.split('=')[0].trim()};}).filter(function(c){return c.name;});
        }
        var existing=new Set();
        tbody.querySelectorAll('.rupk-cookie-row').forEach(function(row){existing.add(values(row)[0]);});
        var added=0;
        list.forEach(function(c){
          if(!c.name || c.name==='rupk_consent' || existing.has(c.name) || c.name.indexOf('|')!==-1 || /[\r\n]/.test(c.name)) return;
          var cls=classify(c.name);
          add([c.name,cls[0],cls[1],cls[2],expiryText(c)]);
          existing.add(c.name); added++;
        });
        if(added) {
          details.open=true;
          markUnreviewed();
          status.textContent='Найдено '+added+' новых cookie. Их названия добавлены ниже. Категории и сроки требуют проверки; подтверждение не поставлено автоматически.';
        } else {
          status.textContent='Новых доступных cookie не найдено. Это нормально: часть cookie появляется после посещения публичных страниц или доступна только серверу. Заполнение вручную не требуется.';
        }
      } catch(e) {
        status.textContent='Браузер не разрешил автоматическую проверку. Вы можете продолжать без неё — выбранные сервисы уже попадут в документы.';
      } finally {scanButton.disabled=false;}
    }
    panel.addEventListener('click',function(ev) {
      if(ev.target.closest('[data-rupk-cookie-add]')) {ev.preventDefault();details.open=true;add(['','','','functional','']);markUnreviewed();}
      else if(ev.target.closest('[data-rupk-cookie-remove]')) {ev.preventDefault();ev.target.closest('tr').remove();markUnreviewed();}
      else if(ev.target.closest('[data-rupk-scan]')) {ev.preventDefault();scan();}
    });
    tbody.addEventListener('change',markUnreviewed);
    tbody.addEventListener('input',markUnreviewed);
    var form=panel.closest('form');
    if(form) form.addEventListener('submit',synchronize);
    synchronize();
  }
})();