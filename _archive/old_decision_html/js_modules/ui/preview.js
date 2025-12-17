/**
 * Preview Module
 * Handles Letter Preview Generation & Printing
 */

import * as State from '../state.js';
import { DOM } from './layout.js';

export function initLetterPreview() {
    // Initial Render
    updatePreview();

    // Setup listener for print
    const btn = document.getElementById('btnPrintPreview');
    if (btn) {
        btn.addEventListener('click', () => {
            window.print();
        });
    }

    const btnPrintAll = document.getElementById('btnPrintAll');
    if (btnPrintAll) {
        btnPrintAll.addEventListener('click', printAll);
    }
}

export function updatePreview() {
    const record = State.getCurrentRecord();
    const container = document.getElementById('letterContainer');

    if (!record || !container) return;

    // Use Current Selection if available (Transient State), else Record's saved state
    const currentSel = State.getCurrentSelection();

    const bankId = currentSel.bankId || record.bankId;
    const supplierId = currentSel.supplierId || record.supplierId;

    // Resolve Names
    let bankName = currentSel.bankName || record.bankDisplay || record.rawBankName || "البنك الرسمي";
    if (bankId && State.getDictionaries().bankMap[bankId]) {
        bankName = State.getDictionaries().bankMap[bankId].official_name;
    }

    let supplierName = currentSel.supplierName || record.supplierDisplayName || record.rawSupplierName || "المورد";
    if (supplierId && State.getDictionaries().supplierMap[supplierId]) {
        supplierName = State.getDictionaries().supplierMap[supplierId].official_name;
    }

    const html = buildLetterHtml(record, bankId, bankName, supplierId, supplierName);
    container.innerHTML = html;
}

// Local Escape Helper
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildLetterHtml(record, bankId, bankName, supplierId, supplierName) {
    // Get bank address data from dictionary (dynamic)
    const bankData = bankId ? State.getDictionaries().bankMap[bankId] : null;

    const bankContact = {
        department: bankData?.department || "إدارة الضمانات",
        addressLines: [
            bankData?.address_line_1 || "المقر الرئيسي",
            bankData?.address_line_2,
            bankData?.contact_email ? `البريد الالكتروني: ${bankData.contact_email}` : null // Added email with label
        ].filter(Boolean), // Remove null/empty values
        email: bankData?.contact_email || ""
    };


    const guaranteeNo = record.guaranteeNumber || "-";
    const contractNo = record.contractNumber || "-";
    let amount = record.amount ? Number(record.amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) : "-";
    // Convert to Hindi Numerals
    if (amount !== "-") {
        amount = amount.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);
    }

    // Calculate Renewal Date (Expiry + 1 Year) as requested
    let renewalDate = "-";
    if (record.expiryDate) {
        try {
            const dateObj = new Date(record.expiryDate);
            if (!isNaN(dateObj.getTime())) {
                // Calculate next year date
                const nextYearDate = new Date(dateObj);
                nextYearDate.setFullYear(dateObj.getFullYear() + 1);

                // Format: Day MonthName Year (Arabic)
                const formatter = new Intl.DateTimeFormat('ar-EG', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                // "10 أكتوبر 2026"
                let dateStr = formatter.format(nextYearDate);

                // Convert to Hindi Numerals
                dateStr = dateStr.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);

                // Append 'م'
                renewalDate = dateStr + 'م';
            } else {
                // For manually entered dates, try to format them if possible, otherwise use as is
                const d = new Date(record.expiryDate);
                if (!isNaN(d.getTime())) {
                    const formatter = new Intl.DateTimeFormat('ar-EG', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                    renewalDate = formatter.format(d).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]) + 'م';
                } else {
                    renewalDate = record.expiryDate;
                }
            }
        } catch (e) {
            console.error("Date parse error", e);
            renewalDate = record.expiryDate;
        }
    }

    // Determine watermark status
    // Access State via Getters implicitly or passed args. The args are already resolved.
    // Wait, we need to know if "User Selected" to show Partial/Ready.
    // In preview.js updatePreview, we pass resolved IDs.
    // If IDs are passed, it means we have data.

    // Logic from legacy: checking this.selectedSupplierId vs null.
    // Here we need to check if we have valid IDs.
    const hasSupplier = !!supplierId;
    const hasBank = !!bankId;

    let watermarkText = '';
    let watermarkClass = '';

    if (hasSupplier && hasBank) {
        watermarkText = 'جاهز';
        watermarkClass = 'status-ready';
    } else if (hasSupplier || hasBank) {
        watermarkText = 'يحتاج قرار';
        watermarkClass = 'status-partial';
    } else {
        watermarkText = 'يحتاج قرار';
        watermarkClass = 'status-draft';
    }

    let guaranteeDesc = "الضمان البنكي";
    if (record.type) {
        const t = record.type.toUpperCase();
        if (t === 'FINAL') guaranteeDesc = "الضمان البنكي النهائي";
        else if (t === 'ADVANCED') guaranteeDesc = "ضمان الدفعة المقدمة البنكي";
    }


    // Determine font style based on language
    // If it contains Arabic characters, use default (Arabic). Otherwise (English), use Inter/Sans.
    const hasArabic = /[\u0600-\u06FF]/.test(supplierName);
    const supplierStyle = !hasArabic
        ? "font-family: 'Arial', sans-serif !important; direction: ltr; display: inline-block;"
        : "";

    // Clean HTML template (styling handled by letter.css)
    return `
    <div class="letter-preview">
        <div class="letter-paper">
            
            <!-- Watermark -->
            <div class="watermark ${watermarkClass}">${watermarkText}</div>
            
            <div class="header-line">
              <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">السادة / ${escapeHtml(bankName)}</div>
              <div class="greeting">المحترمين</div>
            </div>

            <div>
               <div class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${bankContact.department}</div>
               ${bankContact.addressLines.map(line => {
        if (line.includes('البريد الالكتروني:')) {
            const parts = line.split('البريد الالكتروني:');
            return `<div><span style="text-shadow: 0 0 1px #333, 0 0 1px #333;">البريد الالكتروني:</span>${parts[1]}</div>`;
        }
        return `<div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">${line.replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d])}</div>`;
    }).join('')}
            </div>

            <div style="text-align:right; margin: 5px 0;">السَّلام عليكُم ورحمَة الله وبركاتِه</div>

            <div class="subject">
                <span style="flex:0 0 70px;">الموضوع:</span>
                <span>
                  طلب تمديد الضمان البنكي رقم (${escapeHtml(guaranteeNo)}) 
                  ${(() => {
            if (contractNo === '-') return '';
            let displayNo = contractNo;
            // If it's a PO, convert to Hindi numerals as requested
            if (record.contractSource === 'po') {
                displayNo = String(displayNo).replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);
            }
            return `والعائد ${record.contractSource === 'po' ? 'لأمر الشراء' : 'للعقد'} رقم (${displayNo})`;
        })()}
                </span>
            </div>

            <div class="first-paragraph">
                إشارة الى ${guaranteeDesc} الموضح أعلاه، والصادر منكم لصالحنا على حساب 
                <span style="${supplierStyle}">${escapeHtml(supplierName)}</span> 
                بمبلغ قدره (<strong>${amount}</strong>) ريال، 
                نأمل منكم <span class="fw-800-sharp" style="text-shadow: 0 0 1px #333, 0 0 1px #333;">تمديد فترة سريان الضمان حتى تاريخ ${renewalDate}</span>، 
                مع بقاء الشروط الأخرى دون تغيير، وإفادتنا بذلك من خلال البريد الالكتروني المخصص للضمانات البنكية لدى مستشفى الملك فيصل التخصصي ومركز الأبحاث بالرياض (bgfinance@kfshrc.edu.sa)، كما نأمل منكم إرسال أصل تمديد الضمان الى:
            </div>

            <div style="margin-top: 5px; margin-right: 50px;">
                <div>مستشفى الملك فيصل التخصصي ومركز الأبحاث – الرياض</div>
                <div>ص.ب ٣٣٥٤ الرياض ١١٢١١</div>
                <div>مكتب الخدمات الإدارية</div>
            </div>

            <div class="first-paragraph">
                علمًا بأنه في حال عدم تمكن البنك من تمديد الضمان المذكور قبل انتهاء مدة سريانه، فيجب على البنك دفع قيمة الضمان إلينا حسب النظام.
            </div>

            <div style="text-indent:5em; margin-top:5px;">وَتفضَّلوا بِقبُول خَالِص تحيَّاتِي</div>

            <div class="fw-800-sharp" style="text-align: center; margin-top: 5px; margin-right: 320px;">
                <div style="margin-bottom: 60px; text-shadow: 0 0 1px #333, 0 0 1px #333;">مُدير الإدارة العامَّة للعمليَّات المحاسبيَّة</div>
                <div style="text-shadow: 0 0 1px #333, 0 0 1px #333;">سَامِي بن عبَّاس الفايز</div>
            </div>

            <div style="position:absolute; left:1in; right:1in; bottom:0.7in; display:flex; justify-content:space-between; font-size:9pt;">
              <span>MBC:09-2</span>
              <span>BAMZ</span>
            </div>

        </div>
    </div>
    `;
}

async function printAll() {
    const list = State.getRecords().filter(r => r.matchStatus === 'ready' || r.matchStatus === 'approved');

    if (list.length === 0) {
        alert('لا توجد سجلات جاهزة للطباعة');
        return;
    }

    let iframe = document.getElementById('printFrame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'printFrame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
    }

    let content = '';
    list.forEach((record, index) => {
        const pageBreak = index < list.length - 1 ? 'page-break' : '';

        // Resolve Names for Printing
        const dict = State.getDictionaries();
        let bankName = record.rawBankName;
        if (record.bankId && dict.bankMap[record.bankId]) bankName = dict.bankMap[record.bankId].official_name;

        let supplierName = record.rawSupplierName;
        if (record.supplierId && dict.supplierMap[record.supplierId]) supplierName = dict.supplierMap[record.supplierId].official_name;

        content += `<div class="print-page ${pageBreak}">
            ${buildLetterHtml(record, record.bankId, bankName, record.supplierId, supplierName)}
        </div>`;
    });

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <title>طباعة الكل (${list.length})</title>
            <meta charset="UTF-8">
            <link rel="stylesheet" href="/assets/css/letter.css">
            <style>
                body { margin: 0; padding: 0; background: #fff; }
                .print-page { margin: 0; padding: 0; }
                @media print {
                    body, .print-page, .letter-preview {
                        width: 100% !important;
                        position: static !important;
                        visibility: visible !important;
                        overflow: visible !important;
                    }
                    .letter-paper {
                        position: relative !important;
                        box-shadow: none !important; 
                        margin: 0 auto !important; 
                        min-height: 296mm !important; 
                        overflow: hidden !important; 
                    }
                    .page-break { 
                        page-break-after: always !important; 
                        height: 0; 
                        display: block;
                    }
                    @page { margin: 0; }
                }
            </style>
        </head>
        <body>
            ${content}
            <script>
                window.addEventListener('load', () => {
                    document.fonts.ready.then(() => {
                        setTimeout(() => { window.print(); }, 500); 
                    });
                });
            </script>
        </body>
        </html>
    `);
    doc.close();
}
