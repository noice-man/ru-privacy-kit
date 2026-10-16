/* RU Privacy Kit: manages only scripts added by this plugin, not tags injected by themes or other plugins. */
(function () {
  'use strict';
  var cfg = window.RUPK_CONFIG;
  if (!cfg) return;
  var data = window.RUPK_EARLY && window.RUPK_EARLY.choice;
  var consent = data && data.v === cfg.revision && data.decided === true ? data : null;
  var id = consent && consent.id || makeId();
  var started = { yandex: false, google: false, sberads: false, vk: false };
  var googleInitialized = false;
  var initialAnalytics = false;
  var root, banner, panel, lastFocused;

  function makeId() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var bytes = new Uint8Array(16);
    if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(bytes);
    else return '00000000-0000-4000-8000-000000000000';
    bytes[6] = bytes[6] & 15 | 64;
    bytes[8] = bytes[8] & 63 | 128;
    var s = Array.prototype.map.call(bytes, function (b) { return b.toString(16).padStart(2,'0'); }).join('');
    return s.substring(0,8)+'-'+s.substring(8,12)+'-'+s.substring(12,16)+'-'+s.substring(16,20)+'-'+s.substring(20);
  }
  function isDenied() { return consent !== null && consent.a === false; }
  function hasAnalytics() { return !!(consent && consent.a); }
  function analyticEligible(service) {
    if (!service || !service.managed) return false;
    if (hasAnalytics()) return true;
    if (isDenied()) return false;
    if (cfg.mode === 'optout' && service.basis) return true;
    if (cfg.mode === 'advanced' && service === cfg.services.google && service.basis) return true;
    return false;
  }
  function analyticsOn() {
    return analyticEligible(cfg.services.yandex) || analyticEligible(cfg.services.google) || analyticEligible(cfg.services.sberads);
  }
  function persist() {
    var maxAge = Math.max(1,Math.min(365,cfg.days))*86400;
    document.cookie = 'rupk_consent=' + encodeURIComponent(JSON.stringify(consent)) + ';path=/;max-age=' + maxAge + ';SameSite=Lax' + (location.protocol==='https:'?';Secure':'');
  }
  function emit(type) {
    var detail = { revision:cfg.revision, consent: consent ? { functional:!!consent.f, analytics:!!consent.a, marketing:!!consent.m } : null,
      analyticsOperating:analyticsOn(), mode:cfg.mode };
    document.dispatchEvent(new CustomEvent(type,{detail:detail}));
  }
  function wpConsent() {
    // wp_set_consent describes choices, NOT an independent legal basis in opt-out mode.
    if (typeof window.wp_set_consent !== 'function') return;
    try {
      window.wp_set_consent('statistics',consent && consent.a?'allow':'deny');
      window.wp_set_consent('marketing',consent && consent.m?'allow':'deny');
      window.wp_set_consent('preferences',consent && consent.f?'allow':'deny');
      window.wp_set_consent('functional','allow');
    } catch(e) { /* third-party API absent or incompatible */ }
  }
  function inject(src) {
    var script = document.createElement('script'); script.async=true; script.src=src; script.referrerPolicy='strict-origin-when-cross-origin';
    document.head.appendChild(script);
  }
  function runYandex() {
    var s=cfg.services.yandex;
    if (!analyticEligible(s) || started.yandex) return;
    var n=Number(s.id);
    if (!n || !/^[0-9]{4,12}$/.test(String(s.id))) return;
    window['disableYaCounter'+s.id]=false;
    window.ym=window.ym || function(){(window.ym.a=window.ym.a||[]).push(arguments);};
    window.ym.l=window.ym.l || Date.now();
    inject('https://mc.yandex.ru/metrika/tag.js');
    window.ym(n,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true});
    started.yandex=true;
  }
  function googleCommand() {
    window.dataLayer=window.dataLayer || [];
    window.gtag=window.gtag || function(){window.dataLayer.push(arguments);};
  }
  function googleState() {
    return {analytics_storage:hasAnalytics()?'granted':'denied', ad_storage:consent&&consent.m?'granted':'denied',
      ad_user_data:consent&&consent.m?'granted':'denied', ad_personalization:consent&&consent.m?'granted':'denied'};
  }
  function runGoogle() {
    var s=cfg.services.google;
    if (!analyticEligible(s) || started.google || !/^G-[A-Z0-9]{5,20}$/.test(String(s.id))) return;
    googleCommand();
    // Default is denied even if opt-out uses another legal basis; do not fabricate an affirmative consent.
    var denied={analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'};
    window.gtag('consent','default',denied);
    if (consent) window.gtag('consent','update',googleState());
    window.gtag('js',new Date());
    window['ga-disable-'+s.id]=false;
    inject('https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(s.id));
    window.gtag('config',s.id, {send_page_view:true});
    started.google=true; googleInitialized=true;
  }
  function runSberads() {
    var s=cfg.services.sberads;
    if (!analyticEligible(s) || started.sberads || !/^[0-9]{1,20}$/.test(String(s.id))) return;
    // Official SberAds (Top100/Kraken) queue pattern; no no-script pixel,
    // because such a pixel would bypass the configured consent policy.
    window._top100q=window._top100q || [];
    window._top100q.push(function () {
      // If consent has since been refused while the SDK was in flight, do not initialize.
      if (!analyticEligible(s)) return;
      try { window.top100Counter=new window.top100({project:String(s.id)}); } catch(e) {}
    });
    started.sberads=true;
    if (!document.querySelector('script[data-rupk-sberads]')) {
      var script=document.createElement('script');
      script.async=true;
      script.src='https://st.top100.ru/top100/top100.js';
      script.setAttribute('data-rupk-sberads','1');
      document.head.appendChild(script);
    }
  }
  function runVkPixel() {
    // VK Ads pixel uses the top.mail.ru counter runtime; do not create noscript pixels.
    // Marketing must be explicitly accepted, regardless of the analytics mode.
    var s=cfg.services.vk;
    if (!s || !s.managed || !consent || consent.m !== true || started.vk || !/^[0-9]{1,20}$/.test(String(s.id))) return;
    window._tmr=window._tmr||[];
    window._tmr.push({id:String(s.id),type:'pageView',start:Date.now()});
    started.vk=true;
    if (!document.querySelector('script[data-rupk-vk]')) {
      var script=document.createElement('script');
      script.async=true;
      script.src='https://top-fwz1.mail.ru/js/code.js';
      script.setAttribute('data-rupk-vk','1');
      document.head.appendChild(script);
    }
  }
  function runTrackers() { runYandex(); runGoogle(); runSberads(); runVkPixel(); }
  function disableTrackers() {
    var y=cfg.services.yandex, g=cfg.services.google;
    if (y && y.managed) window['disableYaCounter'+y.id]=true;
    if (g && g.managed) window['ga-disable-'+g.id]=true;
    if (googleInitialized && typeof window.gtag==='function') window.gtag('consent','update',googleState());
  }
  function log(eventName) {
    if (!cfg.log || !consent || !/^[0-9a-f-]{36}$/.test(consent.id)) return;
    var payload=new URLSearchParams({action:'rupk_log',nonce:cfg.nonce,id:consent.id,
      event:eventName,revision:String(cfg.revision),categories:(consent.f?'1':'0')+(consent.a?'1':'0')+(consent.m?'1':'0')});
    if (window.fetch) fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',body:payload,keepalive:true}).catch(function(){});
  }
  function hideBanner() { if (banner) banner.hidden=true; }
  function closePanel() {
    if (panel) panel.hidden=true;
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }
  function showPanel() {
    if (!root) return;
    lastFocused=document.activeElement;
    if (banner) banner.hidden=true;
    panel.hidden=false;
    ['functional','analytics','marketing'].forEach(function (k) {
      var field=panel.querySelector('[data-rupk-category="'+k+'"]');
      if (field) field.checked=!!(consent && consent[k==='functional'?'f':k==='analytics'?'a':'m']);
    });
    panel.querySelector('button').focus();
  }
  function store(f,a,m) {
    var wasTracking=started.yandex || started.google || started.sberads;
    var wasMarketing=started.vk;
    var before=consent;
    consent={v:cfg.revision,id:id,decided:true,f:!!f,a:!!a,m:!!m,ts:Date.now()};
    persist();
    // Set flags immediately before updating any Google consent state or restarting trackers.
    if (!consent.a) disableTrackers();
    else {
      var y=cfg.services.yandex,g=cfg.services.google;
      if (y && y.managed) window['disableYaCounter'+y.id]=false;
      if (g && g.managed) window['ga-disable-'+g.id]=false;
      if (googleInitialized && typeof window.gtag==='function') window.gtag('consent','update',googleState());
      runTrackers();
    }
    if (consent.m) runVkPixel();
    wpConsent(); log(before && before.a && !consent.a ? 'revoke' : before?'change':consent.a||consent.m||consent.f?'grant':'deny');
    emit(before && before.a && !consent.a?'rupk:consent-revoked':'rupk:consent-changed');
    hideBanner(); closePanel();
    if (root) root.classList.add('rupk-decided');
    // In-flight async scripts cannot be retracted; a reload reapplies early suppression before third-party code.
    if ((wasTracking && !consent.a) || (wasMarketing && !consent.m)) window.location.reload();
  }
  function onAction(action) {
    if (action==='deny') store(false,false,false);
    else if (action==='accept') store(true,true,true);
    else if (action==='micro_accept') store(false,true,false);
    else if (action==='settings') showPanel();
    else if (action==='save') {
      function check(k) { var el=panel.querySelector('[data-rupk-category="'+k+'"]'); return el?el.checked:false; }
      store(check('functional'),check('analytics'),check('marketing'));
    } else if (action==='close') { closePanel(); if (!consent && banner) banner.hidden=false; }
    else if (action==='dismiss') {
      // Acknowledgement is not consent and must not create a persisted consent decision.
      try { window.sessionStorage.setItem('rupk_seen_'+cfg.revision,'1'); } catch(e){}
      hideBanner();
    }
  }
  function setupUI() {
    root=document.getElementById('rupk-root');
    if (!root) return;
    // Themes may call wp_footer() inside a transformed/clipped wrapper.
    // Portaling directly to body makes fixed positioning viewport-relative and
    // prevents the cookie UI from being clipped by a theme's overflow rules.
    if (document.body && root.parentNode !== document.body) document.body.appendChild(root);
    banner=root.querySelector('.rupk-banner'); panel=root.querySelector('.rupk-preferences');
    root.hidden=false; banner.hidden=true; panel.hidden=true;
    if (!consent && window.RUPK_SHOW_BANNER !== false) {
      var seen=false;
      try { seen=(cfg.mode==='optout' || cfg.layout==='notice') && window.sessionStorage.getItem('rupk_seen_'+cfg.revision)==='1'; } catch(e){}
      if (!seen) banner.hidden=false;
    }
    if (consent) root.classList.add('rupk-decided');
    document.addEventListener('click',function (e) {
      var trigger=e.target.closest('[data-rupk],.rupk-open-preferences');
      if (!trigger) return;
      if (trigger.classList.contains('rupk-open-preferences')) { e.preventDefault();showPanel();return; }
      if (root.contains(trigger)) { e.preventDefault(); onAction(trigger.getAttribute('data-rupk')); }
    });
    document.addEventListener('keydown',function (e) {
      if (!panel.hidden) {
        if (e.key==='Escape') { e.preventDefault();closePanel(); if (!consent && banner) banner.hidden=false; }
        if (e.key==='Tab') {
          var focusable=Array.prototype.slice.call(panel.querySelectorAll('button,input:not([disabled])'));
          if (!focusable.length) return;
          var first=focusable[0],last=focusable[focusable.length-1];
          if (e.shiftKey && document.activeElement===first) { e.preventDefault();last.focus(); }
          else if (!e.shiftKey && document.activeElement===last) { e.preventDefault();first.focus(); }
        }
      }
    });
  }
  // Declare opt-in to other WordPress Consent API integrations: never report consent for opt-out legal basis.
  window.wp_consent_type='optin';
  document.dispatchEvent(new CustomEvent('wp_consent_type_defined'));
  runTrackers();
  wpConsent();
  emit('rupk:consent-ready');
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',setupUI); else setupUI();
  window.RUPK={hasConsent:function(k){return !!(consent && consent[k==='functional'?'f':k==='marketing'?'m':'a']);},
    open:function(){showPanel();},registerService:function(){throw new Error('Registration is available via PHP filter rupk_service_registry; manually injected scripts are not auto-blocked.');}};
})();