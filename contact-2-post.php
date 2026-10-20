<?php
/*
 * Plugin Name: Contact 2 Post
 * Plugin URI: https://github.com/TurtleEngr/WP-contact-2-post
 * Description: When a contact-form-7 form is submitted, this plugin saves uploaded images to Media Library, then it creates a post from a template page.
 * Version: VERSION
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires Plugins: contact-form-7, flamingo
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

// HTML allowed in textarea values. Only <a> has an attribute (href).
const cx2p_cHtmlTagList = [
    'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'p' => [],
    'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'a' => ['href' => true],
    'ul' => [], 'ol' => [], 'li' => [], 'br' => [],
];
const cx2p_cHtmlProtocolList = ['http', 'https', 'mailto'];

add_action('plugins_loaded', function () {
    if (!class_exists('WPCF7') || !class_exists('Flamingo_Inbound_Message')) {
        add_action('admin_notices', function () {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Contact 2 Post</strong> requires the Contact Form 7 and Flamingo plugins to be installed and active.';
            echo '</p></div>';
        });
        return;
    }
    // Uploads are saved first (wpcf7_before_send_mail), then the post is
    // created after Flamingo saves the message (wpcf7_after_flamingo).
    // Both run in the same request.
    add_action('wpcf7_before_send_mail', 'cx2p_fOnBeforeCf7SendMail');
    add_action('wpcf7_after_flamingo', 'cx2p_fAfterFlamingo');
    // Email any errors last. Flamingo runs on wpcf7_submit at priority
    // 10, and fires wpcf7_after_flamingo from there.
    add_action('wpcf7_submit', 'cx2p_fSendErrors', 20, 2);
    // Clean HTML pasted into the form's textareas.
    add_action('wpcf7_enqueue_scripts', 'cx2p_fEnqueuePasteScript');
});

// Documentation and Support links on the admin Plugins page. Added
// even when the required plugins are not active.
add_filter('plugin_row_meta', 'cx2p_fMetaLinks', 10, 2);

// ----------------------------------------
// Add links after the Version and Author in this plugin's row.
function cx2p_fMetaLinks($pLinks, $pFile)
{
    if ($pFile !== plugin_basename(__FILE__)) {
        return $pLinks;
    }
    $tRowMeta = [
        'docs'    => '<a href="' . esc_url('https://turtleengr.github.io/WP-contact-2-post/')
            . '" target="_blank" rel="noopener noreferrer">Documentation</a>',
        'support' => '<a href="' . esc_url('https://turtleengr.github.io/WP-contact-2-post/index.html#support')
            . '" target="_blank" rel="noopener noreferrer">Support</a>',
    ];
    return array_merge($pLinks, $tRowMeta);
}

// ----------------------------------------
function cx2p_fEnqueuePasteScript()
{
    $tFile = 'js/cx2p-paste.js';
    wp_enqueue_script(cx2p_cSlug . '-paste', plugins_url($tFile, __FILE__), [],
        (string) filemtime(plugin_dir_path(__FILE__) . $tFile), true);
}

// ----------------------------------------
// Keep only the cx2p_cHtmlTagList tags, with no attributes except a
// safe href on <a>. Script and style elements are removed with their
// content, and HTML comments are removed (they could add block markup).
function cx2p_fCleanHtml($pHtml)
{
    $pHtml = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $pHtml);
    $pHtml = preg_replace('/<!--.*?(-->|$)/s', '', $pHtml);
    return force_balance_tags(wp_kses($pHtml, cx2p_cHtmlTagList, cx2p_cHtmlProtocolList));
}

// ----------------------------------------
// Save an error message for the current submission. pAdminOnly is
// true for site setup errors, which are not sent to the submitter.
// Call with no argument to get the saved list: [[msg, adminOnly], ...]
// The list is kept in memory for this request (one form submission),
// and is emailed by cx2p_fSendErrors.
function cx2p_fErrorLog($pMsg = null, $pAdminOnly = false)
{
    static $tMsgList = [];

    if ($pMsg !== null) {
        $tMsgList[] = [$pMsg, $pAdminOnly];
    }
    return $tMsgList;
}

// ----------------------------------------
// Email the errors saved with cx2p_fErrorLog. The site admin gets all
// of them. The submitter ("your-email" field) gets the ones that are
// not admin-only, but not for spam.
function cx2p_fSendErrors($pForm, $pResult)
{
    $tErrorList = cx2p_fErrorLog();
    if (!$tErrorList) {
        return;
    }
    $tAllList = [];
    $tUserList = [];
    foreach ($tErrorList as $tError) {
        $tAllList[] = $tError[0];
        if (!$tError[1]) {
            $tUserList[] = $tError[0];
        }
    }
    $tSubject = 'Contact 2 Post errors: ' . $pForm->title();
    $tIntro = "These errors were found when the form was submitted:\n\n";
    $tAdminEmail = get_option('admin_email');
    wp_mail($tAdminEmail, $tSubject, $tIntro . implode("\n", $tAllList));

    if (!$tUserList || (isset($pResult['status']) && $pResult['status'] === 'spam')) {
        return;
    }
    $tSubmission = WPCF7_Submission::get_instance();
    $tUserEmail = $tSubmission ? sanitize_email(cx2p_fValueToString($tSubmission->get_posted_data('your-email'))) : '';
    if (!is_email($tUserEmail) || strcasecmp($tUserEmail, $tAdminEmail) === 0) {
        return;
    }
    wp_mail($tUserEmail, $tSubject, $tIntro . implode("\n", $tUserList));
}

// ----------------------------------------
// Replace chars not in [a-zA-Z0-9._-] with "_", then collapse
// repeated "_".
function cx2p_fNormalizeFileName($pFileName)
{
    $pFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $pFileName);
    return preg_replace('/_+/', '_', $pFileName);
}

// ----------------------------------------
// If the file exists, insert "_N" before the extension, N = 1, 2,
// ... until unused.
function cx2p_fUniqueFileName($pFilePath)
{
    if (!file_exists($pFilePath)) {
        return $pFilePath;
    }
    $tInfo = pathinfo($pFilePath);
    $tExt = isset($tInfo['extension']) ? '.' . $tInfo['extension'] : '';
    $tN = 1;
    do {
        $tNewPath = $tInfo['dirname'] . '/' . $tInfo['filename'] . '_' . $tN . $tExt;
        $tN++;
    } while (file_exists($tNewPath));
    return $tNewPath;
}

// ----------------------------------------
// Copy an uploaded image to the uploads dir and add it to the Media
// Library. Returns the attachment metadata, or false if the file was
// not added to the Media Library.
function cx2p_fCreateAttachment($pFileName)
{
    // The extension must also be an allowed image type, so a file is
    // never saved with an extension the web server would run or serve
    // as a page.
    $tExtType = wp_check_filetype(basename($pFileName));
    if (!$tExtType['type'] || strpos($tExtType['type'], 'image/') !== 0) {
        cx2p_fErrorLog('file extension not allowed, not saved: ' . basename($pFileName));
        return false;
    }
    // Only image types allowed by WordPress, detected from the file content.
    $tMimeType = wp_get_image_mime($pFileName);
    if (!$tMimeType || !in_array($tMimeType, get_allowed_mime_types(), true)) {
        cx2p_fErrorLog('file type not allowed, not saved: ' . basename($pFileName));
        return false;
    }
    $tUploadDir = wp_upload_dir();

    $tAttachFileName = $tUploadDir['path'] . '/' . cx2p_fNormalizeFileName(basename($pFileName));
    $tAttachFileName = apply_filters('cx2p_fCreateAttachment_file_name', $tAttachFileName);
    $tAttachFileName = cx2p_fUniqueFileName($tAttachFileName);
    if (!copy($pFileName, $tAttachFileName)) {
        cx2p_fErrorLog('copy failed, not saved: ' . $tAttachFileName);
        return false;
    }

    $tSkipMediaLibrary = apply_filters('cx2p_should_skip_save_attachment_to_media_library', false);
    if ($tSkipMediaLibrary) {
        return false;
    }

    $tAttachment = [
        'guid'           => str_replace($tUploadDir['basedir'], $tUploadDir['baseurl'], $tAttachFileName),
        'post_mime_type' => $tMimeType,
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($tAttachFileName)),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $tAttachment = apply_filters('cx2p_before_insert_attachment', $tAttachment);
    $tAttachId = wp_insert_attachment($tAttachment, $tAttachFileName, 0, true);
    if (is_wp_error($tAttachId) || !$tAttachId) {
        $tReason = is_wp_error($tAttachId) ? ': ' . $tAttachId->get_error_message() : '';
        cx2p_fErrorLog('not added to Media Library, file removed: ' . $tAttachFileName . $tReason);
        wp_delete_file($tAttachFileName);
        return false;
    }
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    $tAttachData = wp_generate_attachment_metadata($tAttachId, $tAttachFileName);
    wp_update_attachment_metadata($tAttachId, $tAttachData);

    cx2p_fSaveAttachId($tAttachId);
    return $tAttachData;
}

// ----------------------------------------
// Save all the uploaded files for the current submission.
function cx2p_fOnBeforeCf7SendMail()
{
    $tSubmission = WPCF7_Submission::get_instance();
    if (!$tSubmission) {
        return;
    }
    foreach ((array) $tSubmission->uploaded_files() as $tFilePath) {
        if (is_array($tFilePath)) {
            foreach ($tFilePath as $tValue) {
                cx2p_fCreateAttachment($tValue);
            }
        } else {
            cx2p_fCreateAttachment($tFilePath);
        }
    }
}

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
        cx2p_fErrorLog('post_template is not set for form ' . $tForm->id(), true);
        return;
    }
    $tPage = get_page_by_path($tSlug, OBJECT, 'page');
    if (!$tPage) {
        cx2p_fErrorLog('post_template page not found: ' . $tSlug, true);
        return;
    }

    $tMsg = new Flamingo_Inbound_Message((int) $pResult['flamingo_inbound_id']);
    $tFields = is_array($tMsg->fields) ? $tMsg->fields : [];

    // Textarea values can have some HTML. See cx2p_fCleanHtml.
    $tHtmlFieldList = [];
    foreach ($tForm->scan_form_tags(['basetype' => 'textarea']) as $tTag) {
        $tHtmlFieldList[] = $tTag->name;
    }

    cx2p_fCreatePost($tPage->post_content, $tFields, cx2p_fSaveAttachId(), $tHtmlFieldList);
}

// ----------------------------------------
function cx2p_fCreatePost($pTemplate, $pFields, $pAttachIdList, $pHtmlFieldList = [])
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
        $tBlockList = array_merge($tBlockList, cx2p_fReplaceInBlock($tBlock, $pFields, $pHtmlFieldList));
    }
    if ($tInAttr) {
        cx2p_fErrorLog(cx2p_cAttrEnd . ' not found in the template', true);
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
        cx2p_fErrorLog('wp_insert_post failed: ' . $tPostId->get_error_message(), true);
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
            cx2p_fErrorLog('invalid expire-date: "' . $tAttr['expire-date'] . '"');
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
// Returns a list of blocks, because a paragraph with a {field} that
// has block HTML (p, h1-h4, ul, ol) is split into several blocks.
function cx2p_fReplaceInBlock($pBlock, $pFields, $pHtmlFieldList = [])
{
    if ($pBlock['blockName'] === 'core/paragraph') {
        $tSplitList = cx2p_fSplitParagraph($pBlock, $pFields, $pHtmlFieldList);
        if ($tSplitList !== null) {
            return $tSplitList;
        }
    }
    $pBlock['innerHTML'] = cx2p_fReplaceFields((string) $pBlock['innerHTML'], $pFields, true, $pHtmlFieldList);
    $tContentList = [];
    $tInnerList = [];
    $tN = 0;
    foreach ($pBlock['innerContent'] as $tChunk) {
        if (is_string($tChunk)) {
            $tContentList[] = cx2p_fReplaceFields($tChunk, $pFields, true, $pHtmlFieldList);
            continue;
        }
        // One null in innerContent for each inner block.
        foreach (cx2p_fReplaceInBlock($pBlock['innerBlocks'][$tN++], $pFields, $pHtmlFieldList) as $tInner) {
            $tInnerList[] = $tInner;
            $tContentList[] = null;
        }
    }
    $pBlock['innerContent'] = $tContentList;
    $pBlock['innerBlocks'] = $tInnerList;
    return [$pBlock];
}

// ----------------------------------------
// If the paragraph has a {field} whose value has block HTML, return
// the list of blocks: the paragraph text before the {field}, the
// blocks made from the value, the text after it. Else null.
function cx2p_fSplitParagraph($pBlock, $pFields, $pHtmlFieldList)
{
    if (!preg_match('#^(\s*<p\b[^>]*>)(.*)(</p>\s*)$#is', (string) $pBlock['innerHTML'], $tMatch)) {
        return null;
    }
    $tPartList = preg_split('/(\{[A-Za-z0-9_-]+\})/', $tMatch[2], -1, PREG_SPLIT_DELIM_CAPTURE);
    $tBlockList = [];
    $tText = '';
    $tFound = false;
    foreach ($tPartList as $tI => $tPart) {
        $tHtml = ($tI % 2) ? cx2p_fBlockHtmlValue(substr($tPart, 1, -1), $pFields, $pHtmlFieldList) : '';
        if ($tHtml === '') {
            $tText .= $tPart;
            continue;
        }
        $tFound = true;
        $tBlockList = array_merge($tBlockList, cx2p_fTextBlock($pBlock, $tMatch, $tText, $pFields, $pHtmlFieldList),
            cx2p_fHtmlToBlocks($tHtml));
        $tText = '';
    }
    if (!$tFound) {
        return null;
    }
    return array_merge($tBlockList, cx2p_fTextBlock($pBlock, $tMatch, $tText, $pFields, $pHtmlFieldList));
}

// ----------------------------------------
// A copy of the paragraph block with pText (its {field} replaced) as
// its text. Returns [] if there is no text.
function cx2p_fTextBlock($pBlock, $pMatch, $pText, $pFields, $pHtmlFieldList)
{
    $tEdge = '(\s|&nbsp;|<br\s*/?>)*';
    $pText = preg_replace("#^$tEdge|$tEdge\$#i", '', $pText);
    $pText = force_balance_tags(cx2p_fReplaceFields($pText, $pFields, true, $pHtmlFieldList));
    if (trim(str_replace('&nbsp;', ' ', wp_strip_all_tags($pText))) === '') {
        return [];
    }
    $tHtml = $pMatch[1] . $pText . $pMatch[3];
    $pBlock['innerHTML'] = $tHtml;
    $pBlock['innerContent'] = [$tHtml];
    return [$pBlock];
}

// ----------------------------------------
// The cleaned value of an HTML field, if it has block HTML. Else "".
function cx2p_fBlockHtmlValue($pName, $pFields, $pHtmlFieldList)
{
    if (!in_array($pName, $pHtmlFieldList, true) || !array_key_exists($pName, $pFields)) {
        return '';
    }
    $tHtml = cx2p_fCleanHtml(cx2p_fValueToString($pFields[$pName]));
    return preg_match('#<(p|h[1-4]|ul|ol|li)\b#i', $tHtml) ? $tHtml : '';
}

// ----------------------------------------
// Make editor blocks from cleaned HTML: p => paragraph, h1-h4 =>
// heading, ul/ol => list. Text between them becomes a paragraph.
function cx2p_fHtmlToBlocks($pHtml)
{
    $tDoc = cx2p_fLoadHtml($pHtml);
    $tBlockList = [];
    $tInline = '';
    foreach ($tDoc->getElementsByTagName('body')->item(0)->childNodes as $tNode) {
        $tName = ($tNode instanceof DOMElement) ? strtolower($tNode->nodeName) : '';
        if (!preg_match('/^(p|h[1-4]|ul|ol)$/', $tName)) {
            $tInline .= ($tName === 'li') ? cx2p_fNodeHtml($tDoc, $tNode, false) . '<br>' : $tDoc->saveHTML($tNode);
            continue;
        }
        $tBlockList = array_merge($tBlockList, cx2p_fParagraphBlock(cx2p_fTidyInline($tInline)));
        $tInline = '';
        if ($tName === 'ul' || $tName === 'ol') {
            $tBlockList[] = cx2p_fListBlock($tDoc, $tNode);
        } elseif ($tName === 'p') {
            $tBlockList = array_merge($tBlockList, cx2p_fParagraphBlock(cx2p_fNodeHtml($tDoc, $tNode, false)));
        } else {
            $tText = cx2p_fNodeHtml($tDoc, $tNode, false);
            $tLevel = (int) substr($tName, 1);
            if ($tText !== '') {
                $tBlockList[] = cx2p_fMakeBlock('core/heading', $tLevel === 2 ? [] : ['level' => $tLevel],
                    "\n<$tName class=\"wp-block-heading\">$tText</$tName>\n");
            }
        }
    }
    return array_merge($tBlockList, cx2p_fParagraphBlock(cx2p_fTidyInline($tInline)));
}

// ----------------------------------------
// Returns [paragraph block], or [] if pHtml is empty.
function cx2p_fParagraphBlock($pHtml)
{
    return $pHtml === '' ? [] : [cx2p_fMakeBlock('core/paragraph', [], "\n<p>$pHtml</p>\n")];
}

// ----------------------------------------
// A list block, with a list-item block for each li. A ul/ol right
// inside the list is put in the item before it.
function cx2p_fListBlock($pDoc, $pList)
{
    $tTag = strtolower($pList->nodeName);
    $tItemList = []; // [[text, [nested list blocks]], ...]
    foreach ($pList->childNodes as $tNode) {
        $tName = ($tNode instanceof DOMElement) ? strtolower($tNode->nodeName) : '';
        if ($tName === 'ul' || $tName === 'ol') {
            if (!$tItemList) {
                $tItemList[] = ['', []];
            }
            $tItemList[count($tItemList) - 1][1][] = cx2p_fListBlock($pDoc, $tNode);
        } elseif ($tName === 'li') {
            $tNestedList = [];
            foreach ($tNode->childNodes as $tChild) {
                if (in_array(strtolower($tChild->nodeName), ['ul', 'ol'], true)) {
                    $tNestedList[] = cx2p_fListBlock($pDoc, $tChild);
                }
            }
            $tItemList[] = [cx2p_fNodeHtml($pDoc, $tNode, true), $tNestedList];
        } else {
            $tText = cx2p_fTidyInline($pDoc->saveHTML($tNode));
            if ($tText !== '') {
                $tItemList[] = [$tText, []];
            }
        }
    }
    $tBlockList = [];
    foreach ($tItemList as $tItem) {
        $tBlockList[] = cx2p_fMakeBlock('core/list-item', [], "\n<li>" . $tItem[0], $tItem[1], "</li>\n");
    }
    return cx2p_fMakeBlock('core/list', $tTag === 'ol' ? ['ordered' => true] : [],
        "\n<$tTag class=\"wp-block-list\">", $tBlockList, "</$tTag>\n");
}

// ----------------------------------------
// The inline HTML in pNode. Block tags inside it (p, h1-h4, li) are
// removed, with a <br> after their text. Lists are skipped if
// pSkipList (a list-item has them as inner blocks), else their items
// are lines.
function cx2p_fNodeHtml($pDoc, $pNode, $pSkipList)
{
    $tHtml = '';
    foreach ($pNode->childNodes as $tChild) {
        $tName = ($tChild instanceof DOMElement) ? strtolower($tChild->nodeName) : '';
        if ($tName === 'ul' || $tName === 'ol') {
            $tHtml .= $pSkipList ? '' : '<br>' . cx2p_fNodeHtml($pDoc, $tChild, false) . '<br>';
        } elseif (preg_match('/^(p|h[1-4]|li)$/', $tName)) {
            $tHtml .= cx2p_fNodeHtml($pDoc, $tChild, $pSkipList) . '<br>';
        } else {
            $tHtml .= $pDoc->saveHTML($tChild);
        }
    }
    return cx2p_fTidyInline($tHtml);
}

// ----------------------------------------
// Collapse white space and repeated <br>, remove them at the ends, and
// encode "[" "]" so the text can not become a shortcode.
function cx2p_fTidyInline($pHtml)
{
    $pHtml = preg_replace('/\s+/', ' ', $pHtml);
    $pHtml = preg_replace('#\s*<br\s*/?>\s*#i', '<br>', $pHtml);
    $pHtml = preg_replace('#(<br>)+#', '<br>', $pHtml);
    $pHtml = trim(preg_replace('#^(<br>|\s)+|(<br>|\s)+$#', '', $pHtml));
    return str_replace(['[', ']'], ['&#91;', '&#93;'], $pHtml);
}

// ----------------------------------------
// Parse an HTML fragment (UTF-8) into a DOMDocument. The fragment is
// the body's content.
function cx2p_fLoadHtml($pHtml)
{
    $tDoc = new DOMDocument();
    $tDoc->loadHTML('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '</head><body>' . $pHtml . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    return $tDoc;
}

// ----------------------------------------
// A parsed block array, as from parse_blocks(). pInnerList blocks go
// between pOpen and pClose.
function cx2p_fMakeBlock($pName, $pAttrs, $pOpen, $pInnerList = [], $pClose = '')
{
    return [
        'blockName'    => $pName,
        'attrs'        => $pAttrs,
        'innerBlocks'  => $pInnerList,
        'innerHTML'    => $pOpen . $pClose,
        'innerContent' => array_merge([$pOpen], array_fill(0, count($pInnerList), null), [$pClose]),
    ];
}

// ----------------------------------------
// Single pass, so a value containing "{x}" is not expanded again.
// Unknown {x} are left as is. For HTML: escape the value, and encode
// "[" "]" so submitted text can not become a shortcode. A field in
// pHtmlFieldList keeps the inline tags allowed by cx2p_fCleanHtml
// (block tags were split into blocks by cx2p_fSplitParagraph, or are
// changed to <br> here); if it has none of them, it is escaped like
// other fields.
function cx2p_fReplaceFields($pText, $pFields, $pIsHtml, $pHtmlFieldList = [])
{
    return preg_replace_callback('/\{([A-Za-z0-9_-]+)\}/', function ($pMatch) use ($pFields, $pIsHtml, $pHtmlFieldList) {
        if (!array_key_exists($pMatch[1], $pFields)) {
            return $pMatch[0];
        }
        $tValue = cx2p_fValueToString($pFields[$pMatch[1]]);
        if (!$pIsHtml) {
            return $tValue;
        }
        $tHtml = in_array($pMatch[1], $pHtmlFieldList, true) ? cx2p_fCleanHtml($tValue) : '';
        // wp_strip_all_tags() trims, so trim here too, or a value with
        // no tags but with edge white space would be taken as HTML.
        if (trim($tHtml) === wp_strip_all_tags($tHtml)) {
            return str_replace(['[', ']'], ['&#91;', '&#93;'], nl2br(esc_html($tValue), false));
        }
        $tDoc = cx2p_fLoadHtml($tHtml);
        return cx2p_fNodeHtml($tDoc, $tDoc->getElementsByTagName('body')->item(0), false);
    }, $pText);
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
