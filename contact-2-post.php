<?php
/*
 * Plugin Name: Contact 2 Post
 * Plugin URI: https://github.com/TurtleEngr/WP-contact-2-post
 * Description: Create a pending post from a Contact Form 7 submission saved by Flamingo, using a page as the post template.
 * Version: VERSION
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires Plugins: contact-form-7, flamingo, save-cf7-file-uploads
 * Requires PHP: 8.0
 * Author: TurtleEngr
 * Author URI: https://github.com/TurtleEngr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * Constant prefix: cx2p_c
 * Function prefix: cx2p_f
 */

if (!defined('ABSPATH')) {
    exit;
}

const cx2p_cSlug = 'contact-2-post';

// Special syntax lines in the template page that set post attributes.
// They are between the cx2p_cAttrBegin and cx2p_cAttrEnd lines.
const cx2p_cAttrList = ['title', 'categories', 'tags', 'publish-date', 'expire-date', 'expire-time'];
const cx2p_cAttrBegin = 'ATTR-BEGIN';
const cx2p_cAttrEnd = 'ATTR-END';

// Meta key used by the "ninja-auto-post-expire" plugin. Format: "Y-m-d H:i"
const cx2p_cExpireMetaKey = '_njtape_expiration_date';

add_action('plugins_loaded', function () {
    if (!class_exists('WPCF7') || !class_exists('Flamingo_Inbound_Message')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Contact 2 Post</strong> requires the Contact Form 7 and Flamingo plugins to be installed and active.';
            echo '</p></div>';
        });
        return;
    }
    // save-cf7-file-uploads (scf7fu) saves the message in the same request.
    add_action('scf7fu_fCreateAttachmentIdGenerated', 'cx2p_fSaveAttachId');
    add_action('wpcf7_after_flamingo', 'cx2p_fAfterFlamingo');
});

// ----------------------------------------
// Collect attachment IDs saved for the current submission.
// Called with no arg to get the list.
function cx2p_fSaveAttachId($pAttachId = null)
{
    static $tIdList = [];

    if ($pAttachId !== null && (int) $pAttachId > 0) {
        $tIdList[] = (int) $pAttachId;
    }
    return $tIdList;
}

// ----------------------------------------
function cx2p_fAfterFlamingo($pResult)
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
        error_log(cx2p_cSlug . ': post_template is not set for form ' . $tForm->id());
        return;
    }
    $tPage = get_page_by_path($tSlug, OBJECT, 'page');
    if (!$tPage) {
        error_log(cx2p_cSlug . ': post_template page not found: ' . $tSlug);
        return;
    }

    $tMsg = new Flamingo_Inbound_Message((int) $pResult['flamingo_inbound_id']);
    $tFields = is_array($tMsg->fields) ? $tMsg->fields : [];

    // Errors saved by save-cf7-file-uploads. They are kept in memory for
    // this request only, so they are from this submission only.
    $tErrorList = function_exists('scf7fu_fErrorLog') ? scf7fu_fErrorLog() : [];

    cx2p_fCreatePost($tPage->post_content, $tFields, cx2p_fSaveAttachId(), $tErrorList);
}

// ----------------------------------------
function cx2p_fCreatePost($pTemplate, $pFields, $pAttachIdList, $pErrorList = [])
{
    $tAttr = [];
    $tBlockList = [];

    $tSkipBlank = false;
    $tInAttr = false;
    foreach (parse_blocks($pTemplate) as $tBlock) {
        // Also drop the blank separator that followed a removed attribute block.
        if ($tSkipBlank && $tBlock['blockName'] === null && trim($tBlock['innerHTML']) === '') {
            $tSkipBlank = false;
            continue;
        }
        $tLineAttr = cx2p_fGetAttrLines($tBlock, $pFields, $tInAttr);
        $tSkipBlank = ($tLineAttr !== null);
        if ($tLineAttr !== null) {
            $tAttr = array_merge($tAttr, $tLineAttr);
            continue;
        }
        $tBlockList[] = cx2p_fReplaceInBlock($tBlock, $pFields);
    }
    if ($tInAttr) {
        error_log(cx2p_cSlug . ': ' . cx2p_cAttrEnd . ' not found in the template');
    }
    if ($pErrorList) {
        $tBlockList[] = cx2p_fErrorBlock($pErrorList);
    }

    $tTitle = isset($tAttr['title']) ? sanitize_text_field($tAttr['title']) : '';
    $tPost = [
        'post_type'    => 'post',
        'post_status'  => 'pending',
        'post_author'  => cx2p_fGetAdminId(),
        'post_title'   => $tTitle !== '' ? $tTitle : 'UNDEFINED',
        'post_content' => serialize_blocks($tBlockList),
    ];

    if (isset($tAttr['publish-date'])) {
        $tDate = cx2p_fParseDate($tAttr['publish-date']);
        if ($tDate) {
            $tPost['post_date'] = $tDate . ' 12:00:00';
            $tPost['post_date_gmt'] = get_gmt_from_date($tPost['post_date']);
        }
    }

    // Core applies kses to the content when the submitter lacks unfiltered_html.
    $tPostId = wp_insert_post(wp_slash($tPost), true);
    if (is_wp_error($tPostId)) {
        error_log(cx2p_cSlug . ': wp_insert_post failed: ' . $tPostId->get_error_message());
        return 0;
    }

    if (isset($tAttr['categories'])) {
        wp_set_object_terms($tPostId, cx2p_fGetTermIdList($tAttr['categories'], 'category'), 'category');
    }
    if (isset($tAttr['tags'])) {
        wp_set_object_terms($tPostId, cx2p_fGetTermIdList($tAttr['tags'], 'post_tag'), 'post_tag');
    }

    if (isset($tAttr['expire-date'])) {
        $tDate = cx2p_fParseDate($tAttr['expire-date']);
        if ($tDate) {
            $tTime = isset($tAttr['expire-time']) ? cx2p_fParseTime($tAttr['expire-time']) : '';
            update_post_meta($tPostId, cx2p_cExpireMetaKey, $tDate . ' ' . ($tTime ? $tTime : '00:00'));
        } else {
            error_log(cx2p_cSlug . ': invalid expire-date: "' . $tAttr['expire-date'] . '"');
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
// If the top-level block is in the ATTR-BEGIN ... ATTR-END section,
// return [attr => value, ...] for its "attr: value" lines, with {field}
// replaced (plain text). Else null. The section can span blocks, so
// pInAttr keeps the state between calls. The whole block is dropped.
function cx2p_fGetAttrLines($pBlock, $pFields, &$pInAttr)
{
    $tText = preg_replace('/<br\s*\/?>/i', "\n", (string) $pBlock['innerHTML']);
    $tText = html_entity_decode(wp_strip_all_tags($tText), ENT_QUOTES, 'UTF-8');
    // The editor often saves a space as &nbsp; which trim() and \s miss.
    $tText = str_replace("\xC2\xA0", ' ', $tText);

    $tInBlock = $pInAttr;
    $tAttr = [];
    $tPattern = '/^(' . implode('|', array_map('preg_quote', cx2p_cAttrList)) . ')\s*:\s*(.*)$/i';
    foreach (preg_split('/\R/', $tText) as $tLine) {
        $tLine = trim($tLine);
        if ($tLine === cx2p_cAttrBegin) {
            $pInAttr = true;
            $tInBlock = true;
        } elseif ($tLine === cx2p_cAttrEnd) {
            $pInAttr = false;
        } elseif ($pInAttr && preg_match($tPattern, $tLine, $tMatch)) {
            $tAttr[strtolower($tMatch[1])] = cx2p_fReplaceFields($tMatch[2], $pFields, false);
        }
    }
    return $tInBlock ? $tAttr : null;
}

// ----------------------------------------
// Replace {field} in the block's HTML (and its inner blocks). Block
// attributes (the JSON in the block comment) are not changed.
function cx2p_fReplaceInBlock($pBlock, $pFields)
{
    $pBlock['innerHTML'] = cx2p_fReplaceFields((string) $pBlock['innerHTML'], $pFields, true);
    foreach ($pBlock['innerContent'] as $tKey => $tChunk) {
        if (is_string($tChunk)) {
            $pBlock['innerContent'][$tKey] = cx2p_fReplaceFields($tChunk, $pFields, true);
        }
    }
    foreach ($pBlock['innerBlocks'] as $tKey => $tInner) {
        $pBlock['innerBlocks'][$tKey] = cx2p_fReplaceInBlock($tInner, $pFields);
    }
    return $pBlock;
}

// ----------------------------------------
// Single pass, so a value containing "{x}" is not expanded again.
// Unknown {x} are left as is. For HTML: escape the value, and encode
// "[" "]" so submitted text can not become a shortcode.
function cx2p_fReplaceFields($pText, $pFields, $pIsHtml)
{
    return preg_replace_callback('/\{([A-Za-z0-9_-]+)\}/', function ($pMatch) use ($pFields, $pIsHtml) {
        if (!array_key_exists($pMatch[1], $pFields)) {
            return $pMatch[0];
        }
        $tValue = cx2p_fValueToString($pFields[$pMatch[1]]);
        if (!$pIsHtml) {
            return $tValue;
        }
        return str_replace(['[', ']'], ['&#91;', '&#93;'], nl2br(esc_html($tValue), false));
    }, $pText);
}

// ----------------------------------------
// A paragraph block listing the error messages, one per line. Messages
// can hold submitted file names, so they are escaped like field values.
function cx2p_fErrorBlock($pErrorList)
{
    $tLineList = [];
    foreach ($pErrorList as $tMsg) {
        $tLineList[] = str_replace(['[', ']'], ['&#91;', '&#93;'], esc_html((string) $tMsg));
    }
    $tHtml = '<p>Upload errors:<br>' . implode('<br>', $tLineList) . '</p>';
    return [
        'blockName'    => 'core/paragraph',
        'attrs'        => [],
        'innerBlocks'  => [],
        'innerHTML'    => $tHtml,
        'innerContent' => [$tHtml],
    ];
}

// ----------------------------------------
function cx2p_fValueToString($pValue)
{
    if (is_array($pValue)) {
        return implode(', ', array_map('strval', array_filter($pValue, 'is_scalar')));
    }
    return is_scalar($pValue) ? (string) $pValue : '';
}

// ----------------------------------------
// Comma separated names or slugs. Only existing terms are returned.
function cx2p_fGetTermIdList($pList, $pTax)
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
// The user with the site's admin_email, else the lowest ID administrator.
function cx2p_fGetAdminId()
{
    $tUser = get_user_by('email', get_option('admin_email'));
    if ($tUser && user_can($tUser, 'administrator')) {
        return (int) $tUser->ID;
    }
    $tIdList = get_users(['role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
    return $tIdList ? (int) $tIdList[0] : 0;
}

// ----------------------------------------
// Returns "Y-m-d" if pDate is a valid YYYY-MM-DD, else "".
function cx2p_fParseDate($pDate)
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
function cx2p_fParseTime($pTime)
{
    $pTime = trim($pTime);
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $pTime, $tMatch)) {
        return '';
    }
    return sprintf('%02d:%s', $tMatch[1], $tMatch[2]);
}
