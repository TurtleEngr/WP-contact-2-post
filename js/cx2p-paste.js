/*
 * Contact 2 Post - paste filter for Contact Form 7 textareas.
 *
 * When HTML is pasted into a CF7 textarea, only these tags are kept:
 * h1, h2, h3, h4, a, p, b, strong, i, em, ul, ol, li, br. All
 * attributes are removed, except href on <a>, and only for http,
 * https, or mailto URLs.
 * Other tags are removed, but their text is kept. Script, style, and
 * similar elements are removed with their content.
 *
 * The cleaned HTML is inserted as text, so the textarea shows the
 * tags. The server filters the value again, so this is only a
 * convenience for the user, not the security check.
 *
 * Also, after a form is submitted, any errors the plugin found are
 * shown in a popup (cx2p_fShowErrors).
 *
 * Function prefix: cx2p_f
 */
(function () {
    'use strict';

    const cx2p_cTagList = ['H1', 'H2', 'H3', 'H4', 'A', 'P', 'B', 'STRONG', 'I', 'EM',
        'UL', 'OL', 'LI', 'BR'];

    // Kept tags that are followed by a new line in the textarea.
    const cx2p_cLineEndList = ['H1', 'H2', 'H3', 'H4', 'P', 'UL', 'OL', 'LI', 'BR'];

    // Removed with all their content.
    const cx2p_cDropList = ['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'IFRAME', 'OBJECT',
        'EMBED', 'SVG', 'MATH', 'HEAD', 'TITLE', 'META', 'LINK'];

    // Removed tags that end a line, so their text does not run together.
    const cx2p_cBreakList = ['DIV', 'TR', 'H5', 'H6', 'BLOCKQUOTE', 'PRE'];

    const cx2p_cProtocolList = ['http:', 'https:', 'mailto:'];

    // ----------------------------------------
    // Return the href if it is a http, https, or mailto URL, else ''.
    function cx2p_fSafeHref(pHref) {
        if (!pHref) {
            return '';
        }
        try {
            const tUrl = new URL(pHref.trim());
            return cx2p_cProtocolList.includes(tUrl.protocol) ? tUrl.href : '';
        } catch (tErr) {
            return '';
        }
    }

    // ----------------------------------------
    // Copy the allowed parts of pNode's children into pOut.
    function cx2p_fCopyNodes(pNode, pOut, pDoc) {
        for (const tChild of pNode.childNodes) {
            if (tChild.nodeType === Node.TEXT_NODE) {
                pOut.appendChild(pDoc.createTextNode(tChild.nodeValue.replace(/\s+/g, ' ')));
                continue;
            }
            if (tChild.nodeType !== Node.ELEMENT_NODE) {
                continue; // comments, etc.
            }
            const tTag = tChild.tagName.toUpperCase();
            if (cx2p_cDropList.includes(tTag)) {
                continue;
            }
            if (cx2p_cTagList.includes(tTag)) {
                const tNew = pDoc.createElement(tTag.toLowerCase());
                if (tTag === 'A') {
                    const tHref = cx2p_fSafeHref(tChild.getAttribute('href'));
                    if (tHref) {
                        tNew.setAttribute('href', tHref);
                    }
                }
                cx2p_fCopyNodes(tChild, tNew, pDoc);
                pOut.appendChild(tNew);
                if (cx2p_cLineEndList.includes(tTag)) {
                    pOut.appendChild(pDoc.createTextNode('\n'));
                }
                continue;
            }
            cx2p_fCopyNodes(tChild, pOut, pDoc);
            if (cx2p_cBreakList.includes(tTag)) {
                pOut.appendChild(pDoc.createTextNode('\n'));
            }
        }
    }

    // ----------------------------------------
    // Return the cleaned HTML string. DOMParser does not run scripts
    // or load images.
    function cx2p_fCleanHtml(pHtml) {
        const tSrc = new DOMParser().parseFromString(pHtml, 'text/html');
        const tDoc = document.implementation.createHTMLDocument('');
        const tOut = tDoc.createElement('div');
        cx2p_fCopyNodes(tSrc.body, tOut, tDoc);
        return tOut.innerHTML.replace(/[ \t]*\n[ \t]*/g, '\n').replace(/\n{2,}/g, '\n').trim();
    }

    // ----------------------------------------
    function cx2p_fOnPaste(pEvent) {
        const tArea = pEvent.target;
        if (!(tArea instanceof HTMLTextAreaElement) || !tArea.closest('.wpcf7-form')) {
            return;
        }
        const tData = pEvent.clipboardData;
        const tHtml = tData ? tData.getData('text/html') : '';
        if (!tHtml) {
            return; // plain text paste, let the browser do it
        }
        pEvent.preventDefault();
        tArea.setRangeText(cx2p_fCleanHtml(tHtml), tArea.selectionStart, tArea.selectionEnd, 'end');
        tArea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ----------------------------------------
    // CF7 fires wpcf7submit when its reply comes back. The plugin adds
    // its errors to the reply as cx2p_errors (cx2p_fFeedbackResponse).
    function cx2p_fShowErrors(pEvent) {
        const tResponse = pEvent.detail ? pEvent.detail.apiResponse : null;
        const tErrorList = tResponse ? tResponse.cx2p_errors : null;
        if (!Array.isArray(tErrorList) || !tErrorList.length) {
            return;
        }
        alert('Contact 2 Post errors (post not created):\n- ' + tErrorList.join('\n- '));
    }

    document.addEventListener('paste', cx2p_fOnPaste);
    document.addEventListener('wpcf7submit', cx2p_fShowErrors);
}());
