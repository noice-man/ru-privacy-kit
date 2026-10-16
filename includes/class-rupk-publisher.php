<?php
if (!defined('ABSPATH')) { exit; }
/** Completion is explicit and uses a single preflight for wizard and Documents screen. */
final class RUPK_Publisher {
    public static function types(array $cfg): array {
        $types=['cookie','privacy','terms'];
        if (!empty($cfg['commercial']['paid'])) { $types[]='offer'; }
        return $types;
    }
    public static function plan(array $cfg): array {
        $jobs=[]; $errors=[]; $refresh=[]; $edited=[];
        $policy_id=(int)get_option('wp_page_for_privacy_policy',0);
        $stock_id=0;
        $policy=$policy_id ? get_post($policy_id) : null;
        if ($policy && $policy->post_type==='page' && !in_array($policy->post_status,['trash','auto-draft'],true)
            && RUPK_Documents::is_wp_suggested_policy($policy->post_content)) { $stock_id=$policy_id; }
        foreach (self::types($cfg) as $type) {
            $id=(int)($cfg['documents'][$type]??0);
            $post=$id?get_post($id):null;
            if ($id && (!$post || $post->post_type!=='page')) {
                $errors[]='Страница «'.RUPK_Documents::labels()[$type].'» недоступна. Проверьте её привязку в разделе документов.';
                continue;
            }
            if (!$post && $type==='privacy' && $stock_id) {
                $id=$stock_id; $post=get_post($stock_id);
            }
            if (!$post) {
                $existing=get_page_by_path(RUPK_Documents::slug($type),'OBJECT','page');
                if ($existing && $type==='privacy' && RUPK_Documents::is_wp_suggested_policy($existing->post_content)) {
                    $jobs[$type]=['action'=>'replace_stock','id'=>(int)$existing->ID,'title'=>RUPK_Documents::labels()[$type]];
                    continue;
                }
                if ($existing) {
                    // An unassigned path collision must never cause an overwrite.
                    $errors[]='Адрес «'.RUPK_Documents::slug($type).'» занят другой страницей. Привяжите её вручную или освободите адрес.';
                    continue;
                }
                $jobs[$type]=['action'=>'create','id'=>0,'title'=>RUPK_Documents::labels()[$type]];
                continue;
            }
            if (RUPK_Documents::is_wp_suggested_policy($post->post_content)) {
                if ($type!=='privacy') {
                    $errors[]='В странице «'.RUPK_Documents::labels()[$type].'» обнаружен стандартный текст WordPress. Проверьте привязку.';
                    continue;
                }
                $jobs[$type]=['action'=>'replace_stock','id'=>$id,'title'=>RUPK_Documents::labels()[$type]];
                continue;
            }
            if (RUPK_Documents::is_generated($id)) {
                $manual=RUPK_Documents::content_was_edited($id);
                // Generated and unedited pages are dynamically rendered. Their stored revision
                // is not a blocker: no refreshing draft, diff, or re-publication is needed.
                if ($manual) { $refresh[$type]=$id; $edited[$type]=$id; }
                $jobs[$type]=['action'=>$manual?'update_generated':'publish_generated','id'=>$id,'title'=>RUPK_Documents::labels()[$type]];
            } elseif ($post->post_status==='publish') {
                if (stripos(wp_strip_all_tags($post->post_content),'Предлагаемый текст:')!==false ||
                    stripos(wp_strip_all_tags($post->post_content),'Suggested text:')!==false) {
                    $errors[]='В странице «'.RUPK_Documents::labels()[$type].'» остались подсказки WordPress. Удалите их вручную или используйте страницу, создаваемую плагином.';
                    continue;
                }
                $jobs[$type]=['action'=>'keep_manual','id'=>$id,'title'=>RUPK_Documents::labels()[$type]];
            } else {
                $errors[]='Страница «'.RUPK_Documents::labels()[$type].'» создана вручную, но не опубликована. Опубликуйте её в WordPress или отвяжите, чтобы создать новую.';
            }
        }
        return ['jobs'=>$jobs,'errors'=>$errors,'refresh'=>$refresh,'edited'=>$edited,'stock_id'=>$stock_id,
            'retire_stock'=> $stock_id && (!isset($jobs['privacy']) || ($jobs['privacy']['id']??0)!==$stock_id)];
    }
    /** Returns WP_Error on fail, otherwise the final page IDs by type. */
    public static function finish(array $cfg,array $opts): array|WP_Error {
        if (!RUPK_Core::ready($cfg)) { return new WP_Error('not_ready','В мастере ещё не заполнены обязательные сведения.'); }
        $plan=self::plan($cfg);
        if ($plan['errors']) { return new WP_Error('page_conflict',implode(' ',$plan['errors'])); }
        if (empty($opts['approved'])) { return new WP_Error('not_approved','Подтвердите публикацию сформированных документов.'); }
        if ($plan['refresh'] && empty($opts['refresh'])) { return new WP_Error('refresh_consent','Подтвердите обновление ранее созданных страниц.'); }
        if ($plan['edited'] && empty($opts['overwrite_edited'])) { return new WP_Error('manual_edits','На созданных плагином страницах обнаружены ручные изменения. Отдельно подтвердите их замену.'); }
        $has_stock_job=false;
        foreach ($plan['jobs'] as $job) { if ($job['action']==='replace_stock') { $has_stock_job=true; break; } }
        if (($has_stock_job || $plan['stock_id'] || $plan['retire_stock']) && empty($opts['replace_stock'])) {
            return new WP_Error('stock','На сайте опубликован стандартный шаблон WordPress с подсказками. Подтвердите замену или снятие старой страницы с публикации.');
        }
        if (!current_user_can('publish_pages') || !current_user_can('edit_pages')) {
            return new WP_Error('permissions','Для публикации необходимы права WordPress на создание и публикацию страниц.');
        }
        // Preflight all jobs before any write. No changes to manually authored published pages.
        $ids=$cfg['documents'];
        foreach ($plan['jobs'] as $type=>$job) {
            if ($job['action']==='keep_manual') { $ids[$type]=$job['id']; continue; }
            if ($job['action']==='create') {
                $id=wp_insert_post([
                    'post_type'=>'page','post_status'=>'publish','post_title'=>$job['title'],
                    'post_name'=>RUPK_Documents::slug($type),'post_content'=>RUPK_Documents::build($type,$cfg),
                    'post_author'=>get_current_user_id(),
                ],true);
                if (is_wp_error($id) || !$id) { return new WP_Error('write','Не удалось создать страницу «'.$job['title'].'».'); }
                $ids[$type]=(int)$id;
                RUPK_Documents::track_generated((int)$id,$cfg);
            } else {
                $id=(int)$job['id'];
                $update=['ID'=>$id];
                if ($job['action']==='replace_stock' || $job['action']==='update_generated') {
                    $update['post_content']=RUPK_Documents::build($type,$cfg);
                }
                if (get_post_status($id)!=='publish') { $update['post_status']='publish'; }
                if (count($update)>1) {
                    $ok=wp_update_post($update,true);
                    if (is_wp_error($ok) || !$ok) { return new WP_Error('write','Не удалось опубликовать страницу «'.$job['title'].'».'); }
                }
                $ids[$type]=$id;
                RUPK_Documents::track_generated($id,$cfg);
            }
        }
        // Set final links for newly published pages, not draft preview or assumed slugs.
        $final=array_replace($cfg,['documents'=>$ids]);
        foreach ($plan['jobs'] as $type=>$job) {
            if ($job['action']==='keep_manual') { continue; }
            $id=(int)$ids[$type];
            $body=RUPK_Documents::build($type,$final);
            if (get_post_field('post_content',$id)!==$body) {
                $ok=wp_update_post(['ID'=>$id,'post_content'=>$body],true);
                if (is_wp_error($ok) || !$ok) { return new WP_Error('write','Не удалось обновить ссылки в документе «'.$job['title'].'».'); }
            }
            RUPK_Documents::track_generated($id,$cfg);
        }
        foreach ($plan['jobs'] as $type=>$job) {
            if (empty($ids[$type]) || get_post_status((int)$ids[$type])!=='publish') {
                return new WP_Error('write','Не все страницы опубликованы. Повторите завершение мастера.');
            }
        }
        if ($plan['retire_stock'] && $plan['stock_id'] && $plan['stock_id']!==(int)$ids['privacy']) {
            $ok=wp_update_post(['ID'=>(int)$plan['stock_id'],'post_status'=>'draft'],true);
            if (is_wp_error($ok) || !$ok) { return new WP_Error('write','Не удалось убрать старую страницу-подсказку WordPress из открытого доступа.'); }
        }
        // Always use the published privacy page managed/selected by this wizard.
        update_option('wp_page_for_privacy_policy',(int)$ids['privacy']);
        RUPK_Core::save(['documents'=>$ids,'complete'=>true,'enabled'=>true,
            'documents_review_required'=>false,'document_revision'=>(int)$cfg['revision'],'wizard_step'=>8]);
        return $ids;
    }
}