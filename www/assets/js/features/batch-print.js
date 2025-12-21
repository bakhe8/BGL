/**
 * BGL Batch Print Feature
 * Handles printing all records in a session
 * 
 * @since v2.0 - Extracted from decision.js
 * @requires showWarning from dialog.js
 */

window.BGL = window.BGL || {};
window.BGL.features = window.BGL.features || {};

/**
 * Initialize batch print functionality
 * 
 * @param {string|number} sessionId - Current session ID (optional, will try to detect)
 */
window.BGL.features.initBatchPrint = function (sessionId = null) {
    const btnPrintAll = document.getElementById('btnPrintAll');
    if (!btnPrintAll) return;

    btnPrintAll.addEventListener('click', () => {
        // Try to get session_id from multiple sources
        let sid = sessionId;

        // From URL params
        if (!sid) {
            const urlParams = new URLSearchParams(window.location.search);
            sid = urlParams.get('session_id');
        }

        // From config
        if (!sid && window.DecisionApp && window.DecisionApp.sessionId) {
            sid = window.DecisionApp.sessionId;
        }

        // From meta element
        if (!sid) {
            const metaElem = document.getElementById('metaSessionId');
            if (metaElem) {
                sid = metaElem.textContent.trim();
            }
        }

        if (sid && sid !== '-') {
            // Create hidden iframe for printing
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            iframe.src = '/?session_id=' + sid + '&print_batch=1';

            iframe.onload = function () {
                setTimeout(() => document.body.removeChild(iframe), 60000);
            };

            document.body.appendChild(iframe);
        } else {
            if (typeof showWarning === 'function') {
                showWarning('لا يوجد رقم جلسة محدد. الرجاء اختيار جلسة أولاً أو استيراد ملف.');
            } else {
                alert('لا يوجد رقم جلسة محدد');
            }
        }
    });
};

console.log('✓ BGL.features.initBatchPrint loaded');
