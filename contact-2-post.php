<?php
/*
Plugin Name: Contact 2 Post
Plugin URI: https://github.com/TurtleEngr/WP-contact-2-post
Description: Create a pending post from a Contact Form 7 submission saved by Flamingo, using a page as the post template.
Author: TurtleEngr
Text Domain: contact-2-post
Version: VERSION
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

// Special syntax lines in the template page that set post attributes.
const cC2pAttrList = ['title', 'categories', 'tags', 'publish-date', 'expire-date', 'expire-time'];

// Meta key used by the "ninja-auto-post-expire" plugin. Format: "Y-m-d H:i"
const cC2pExpireMetaKey = '_njtape_expiration_date';

add_action('plugins_loaded', function () {
    if (!class_exists('WPCF7') || !class_exists('Flamingo_Inbound_Message')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Contact 2 Post</strong> requires the Contact Form 7 and Flamingo plugins to be installed and active.';
            echo '</p></div>';
        });
        return;
    }
    // store-cf7-file-uploads fires this during wpcf7_before_send_mail,
    // i.e. before Flamingo saves the message in the same request.
    add_action('nmr_create_attachment_id_generated', 'fC2pSaveAttachId');
    add_action('wpcf7_after_flamingo', 'fC2pAfterFlamingo');
});

// ----------------------------------------
// Collect attachment IDs saved for the current submission.
// Called with no arg to get the list.
function fC2pSaveAttachId($pAttachId = null)
{
    static $tIdList = [];

    if ($pAttachId !== null && (int) $pAttachId > 0) {
        $tIdList[] = (int) $pAttachId;
    }
    return $tIdList;
}

// ----------------------------------------
function fC2pAfterFlamingo($pResult)
{
    if (empty($pResult['flamingo_inbound_id']) || empty($pResult['contact_form_id'])) {
        return;
    }
    if (isset($pResult['status']) && $pResult['status'] === 'spam') {
        return;
    }

    $tForm = WPCF7_ContactForm::get_instance((int) $pResult['contact_form_id']);
    if (!$tForm || !$tForm->is_true('create_post')) {
        return;
    }

    $tSlug = sanitize_title((string) $tForm->pref('post_template'));
    if ($tSlug === '') {
        error_log('Contact 2 Post: post_template is not set for form ' . $tForm->id());
        return;
    }
    $tPage = get_page_by_path($tSlug, OBJECT, 'page');
    if (!$tPage) {
        error_log('Contact 2 Post: post_template page not found: ' . $tSlug);
        return;
    }

    $tMsg = new Flamingo_Inbound_Message((int) $pResult['flamingo_inbound_id']);
    $tFields = is_array($tMsg->fields) ? $tMsg->fields : [];

    fC2pCreatePost($tPage->post_content, $tFields, fC2pSaveAttachId());
}

// ----------------------------------------
function fC2pCreatePost($pTemplate, $pFields, $pAttachIdList)
{
    $tAttr = [];
    $tBlockList = [];

    $tSkipBlank = false;
    foreach (parse_blocks($pTemplate) as $tBlock) {
        // Also drop the blank separator that followed a removed attribute block.
        if ($tSkipBlank && $tBlock['blockName'] === null && trim($tBlock['innerHTML']) === '') {
            $tSkipBlank = false;
            continue;
        }
        $tLineAttr = fC2pGetAttrLines($tBlock, $pFields);
        $tSkipBlank = ($tLineAttr !== null);
        if ($tLineAttr !== null) {
            $tAttr = array_merge($tAttr, $tLineAttr);
            continue;
        }
        $tBlockList[] = fC2pReplaceInBlock($tBlock, $pFields);
    }

    $tTitle = isset($tAttr['title']) ? sanitize_text_field($tAttr['title']) : '';
    $tPost = [
        'post_type'    => 'post',
        'post_status'  => 'pending',
        'post_author'  => 0,
        'post_title'   => $tTitle !== '' ? $tTitle : 'UNDEFINED',
        'post_content' => serialize_blocks($tBlockList),
    ];

    if (isset($tAttr['publish-date'])) {
        $tDate = fC2pParseDate($tAttr['publish-date']);
        if ($tDate) {
            $tPost['post_date'] = $tDate . ' 12:00:00';
            $tPost['post_date_gmt'] = get_gmt_from_date($tPost['post_date']);
        }
    }

    // Core applies kses to the content when the submitter lacks unfiltered_html.
    $tPostId = wp_insert_post(wp_slash($tPost), true);
    if (is_wp_error($tPostId)) {
        error_log('Contact 2 Post: wp_insert_post failed: ' . $tPostId->get_error_message());
        return 0;
    }

    if (isset($tAttr['categories'])) {
        wp_set_object_terms($tPostId, fC2pGetTermIdList($tAttr['categories'], 'category'), 'category');
    }
    if (isset($tAttr['tags'])) {
        wp_set_object_terms($tPostId, fC2pGetTermIdList($tAttr['tags'], 'post_tag'), 'post_tag');
    }

    if (isset($tAttr['expire-date'])) {
        $tDate = fC2pParseDate($tAttr['expire-date']);
        if ($tDate) {
            $tTime = isset($tAttr['expire-time']) ? fC2pParseTime($tAttr['expire-time']) : '';
            update_post_meta($tPostId, cC2pExpireMetaKey, $tDate . ' ' . ($tTime ? $tTime : '00:00'));
        }
    }

    $tFeatured = false;
    foreach ($pAttachIdList as $tAttachId) {
        if (get_post_type($tAttachId) !== 'attachment') {
            continue;
        }
        wp_update_post(['ID' => $tAttachId, 'post_parent' => $tPostId]);
        if (!$tFeatured && wp_attachment_is_image($tAttachId)) {
            $tFeatured = set_post_thumbnail($tPostId, $tAttachId);
        }
    }

    return $tPostId;
}

// ----------------------------------------
// If every non-empty line in a top-level block is "attr: value", return
// [attr => value, ...] with [field] replaced (plain text). Else null.
function fC2pGetAttrLines($pBlock, $pFields)
{
    $tText = preg_replace('/<br\s*\/?>/i', "\n", (string) $pBlock['innerHTML']);
    $tText = html_entity_decode(wp_strip_all_tags($tText), ENT_QUOTES, 'UTF-8');

    $tAttr = [];
    $tPattern = '/^\s*(' . implode('|', array_map('preg_quote', cC2pAttrList)) . ')\s*:\s*(.*?)\s*$/i';
    foreach (preg_split('/\R/', $tText) as $tLine) {
        if (trim($tLine) === '') {
            continue;
        }
        if (!preg_match($tPattern, $tLine, $tMatch)) {
            return null;
        }
        $tAttr[strtolower($tMatch[1])] = fC2pReplaceFields($tMatch[2], $pFields, false);
    }
    return $tAttr ? $tAttr : null;
}

// ----------------------------------------
// Replace [field] in the block's HTML (and its inner blocks). Block
// attributes (the JSON in the block comment) are not changed.
function fC2pReplaceInBlock($pBlock, $pFields)
{
    $pBlock['innerHTML'] = fC2pReplaceFields((string) $pBlock['innerHTML'], $pFields, true);
    foreach ($pBlock['innerContent'] as $tKey => $tChunk) {
        if (is_string($tChunk)) {
            $pBlock['innerContent'][$tKey] = fC2pReplaceFields($tChunk, $pFields, true);
        }
    }
    foreach ($pBlock['innerBlocks'] as $tKey => $tInner) {
        $pBlock['innerBlocks'][$tKey] = fC2pReplaceInBlock($tInner, $pFields);
    }
    return $pBlock;
}

// ----------------------------------------
// Single pass, so a value containing "[x]" is not expanded again.
// Unknown [x] are left as is. For HTML: escape the value, and encode
// "[" "]" so submitted text can not become a shortcode.
function fC2pReplaceFields($pText, $pFields, $pIsHtml)
{
    return preg_replace_callback('/\[([A-Za-z0-9_-]+)\]/', function ($pMatch) use ($pFields, $pIsHtml) {
        if (!array_key_exists($pMatch[1], $pFields)) {
            return $pMatch[0];
        }
        $tValue = fC2pValueToString($pFields[$pMatch[1]]);
        if (!$pIsHtml) {
            return $tValue;
        }
        return str_replace(['[', ']'], ['&#91;', '&#93;'], nl2br(esc_html($tValue), false));
    }, $pText);
}

// ----------------------------------------
function fC2pValueToString($pValue)
{
    if (is_array($pValue)) {
        return implode(', ', array_map('strval', array_filter($pValue, 'is_scalar')));
    }
    return is_scalar($pValue) ? (string) $pValue : '';
}

// ----------------------------------------
// Comma separated names or slugs. Only existing terms are returned.
function fC2pGetTermIdList($pList, $pTax)
{
    $tIdList = [];
    foreach (explode(',', $pList) as $tName) {
        $tName = sanitize_text_field($tName);
        if ($tName === '') {
            continue;
        }
        $tTerm = get_term_by('name', $tName, $pTax);
        if (!$tTerm) {
            $tTerm = get_term_by('slug', sanitize_title($tName), $pTax);
        }
        if ($tTerm) {
            $tIdList[] = (int) $tTerm->term_id;
        }
    }
    return array_values(array_unique($tIdList));
}

// ----------------------------------------
// Returns "Y-m-d" if pDate is a valid YYYY-MM-DD, else "".
function fC2pParseDate($pDate)
{
    $pDate = trim($pDate);
    $tDate = DateTime::createFromFormat('!Y-m-d', $pDate);
    if (!$tDate || $tDate->format('Y-m-d') !== $pDate) {
        return '';
    }
    return $pDate;
}

// ----------------------------------------
// Returns "H:i" if pTime is a valid HH:MM, else "".
function fC2pParseTime($pTime)
{
    $pTime = trim($pTime);
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $pTime, $tMatch)) {
        return '';
    }
    return sprintf('%02d:%s', $tMatch[1], $tMatch[2]);
}
