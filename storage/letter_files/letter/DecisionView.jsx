import React, { useEffect, useMemo, useRef, useState } from "react";
import { formatDateValue, normalizeName } from "../utils/normalization";
import { toDisplayOneYear } from "../utils/dateUtils";
import { getBankContactInfo } from "../utils/bankContacts";
import { SupplierTypeahead } from "./SupplierTypeahead";
import AdminToggleButton from "./AdminToggleButton";

const toArabicDigits = (val) => {
  const digits = "٠١٢٣٤٥٦٧٨٩";
  return String(val ?? "").replace(/\d/g, (d) => digits[d] || d);
};

const getGuaranteeTypePhrase = (typeRaw) => {
  const type = String(typeRaw || "").trim().toLowerCase();
  if (type.includes("advance")) return "ضمان الدفعة المقدَّمة";
  if (type.includes("bid") || type.includes("initial")) return "الضمان البنكي الابتدائي";
  return "الضمان البنكي النهائي";
};

const clamp = (val, min, max) => Math.max(min, Math.min(max, val));

export default function DecisionView({
  record,
  records,
  decisionDraft,
  setDecisionDraft,
  onSave,
  onQuickFix,
  onPrev,
  onNext,
  onJumpIndex,
  currentIndex,
  total,
  onPrintAllReady,
  readyCount = 0,
  bankOptions = [],
  suppliersCanonical = [],
  supplierVariants = {},
  counters,
  exportReady,
  onExport,
  onUpload,
  onAnalyze,
  onManualInput,
  onPasteInput,
  adminButtonVisible,
  openAdminModal,
  activeTab,
  setActiveTab,
}) {
  const borderColor = record?.needsDecision ? "#ef4444" : "#22c55e";
  const wrapRef = useRef(null);
  const [scale, setScale] = useState(0.8);
  const [scaledWidth, setScaledWidth] = useState("100%");
  const [showBackground] = useState(true);
  const [jumpValue, setJumpValue] = useState(currentIndex + 1 || 1);
  const emptyManual = useMemo(
    () => ({
      bank: "",
      supplier: "",
      guaranteeNo: "",
      amount: "",
      dateRaw: "",
      contractNo: "",
      poNo: "",
      type: "",
    }),
    []
  );
  const [manualOpen, setManualOpen] = useState(false);
  const [manualForm, setManualForm] = useState(emptyManual);
  const [pasteOpen, setPasteOpen] = useState(false);
  const [pasteText, setPasteText] = useState("");

  useEffect(() => {
    if (!record) {
      setDecisionDraft({ bank: "", supplier: "", supplierId: null });
      setJumpValue(1);
      return;
    }
    setJumpValue(currentIndex + 1);

    // إذا كانت المسودة الحالية تخص نفس السجل وبها اسم غير فارغ، لا نعيد الكتابة فوقها
    if (decisionDraft?.supplier && decisionDraft?.supplierId === record.supplierId) {
      return;
    }

    // مصدر الحقيقة للاسم المعروض هو القاموس المدمج فقط
    const supMerged = suppliersCanonical?.find((c) => c.id && c.id === record.supplierId);
    const preferredName =
      supMerged?.displayNameAr ||
      supMerged?.canonical ||
      supMerged?.official ||
      record?.supplierRaw ||
      "";

    setDecisionDraft({
      bank: record.bankFuzzySuggestion || record.bankOfficial || "",
      supplier: preferredName,
      supplierId: record.supplierId || null,
    });
    // لوج تشخيصي مؤقت لتأكيد وصول supplierId من الـ pipeline
    // eslint-disable-next-line no-console
    if (window?.DEBUG_LOGS) {
      console.log("DecisionView supplier", {
        supplierId: record?.supplierId,
        supplierOfficial: record?.supplierOfficial,
        supplierDisplay: record?.supplierDisplay,
        supplierRaw: record?.supplierRaw,
      });
    }
  }, [record, suppliersCanonical, decisionDraft?.supplier, decisionDraft?.supplierId, currentIndex]);

  // اسم العرض النهائي يعتمد فقط على المسودة أو بيانات السجل
  const displaySupplierName =
    decisionDraft.supplier ||
    record?.supplierDisplay ||
    record?.supplierOfficial ||
    record?.supplierRaw ||
    "";

  // لا إعادة تهيئة للمسودة هنا حتى لا تنتقل قيم السجل السابق

  const totalRecords = records?.length ?? 0;
  const needsDecisionCount = records?.filter((r) => r?.needsDecision)?.length ?? 0;
  const readyUI = Math.max(0, totalRecords - needsDecisionCount);
  const reviewCount =
    records?.filter(
      (r) =>
        r?.bankStatus === "fuzzy" ||
        r?.supplierStatus === "fuzzy" ||
        r?.status === "manual" ||
        r?.status === "verified_by_user"
    )?.length ?? 0;

  const handleManualSubmit = () => {
    onManualInput?.({
      bank: manualForm.bank,
      supplier: manualForm.supplier,
      guaranteeNo: manualForm.guaranteeNo,
      amount: manualForm.amount,
      dateRaw: manualForm.dateRaw,
      contractNo: manualForm.contractNo,
      poNo: manualForm.poNo,
      type: manualForm.type,
    });
  };
  const handlePasteSubmit = () => {
    onPasteInput?.(pasteText);
  };

  useEffect(() => {
    const updateScale = () => {
      if (!wrapRef.current) return;
      const width = wrapRef.current.getBoundingClientRect().width || 0;
      const targetWidth = Math.min(850, Math.max(680, width * 0.96));
      const nextScale = Math.min(1, Math.max(0.45, width / (targetWidth + 16)));
      setScale(nextScale);
      setScaledWidth(`${targetWidth}px`);
    };
    updateScale();
    window.addEventListener("resize", updateScale);
    return () => window.removeEventListener("resize", updateScale);
  }, []);

  const previewHtml = useMemo(() => {
    if (!record) return "";
    // استخدم مسودة القرار لإظهار التغييرات فوراً في المعاينة بدون انتظار الحفظ
    const bankName =
      decisionDraft.bank || record.bankDisplay || record.bankOfficial || record.bankRaw || "البنك الرسمي";
    const bankContact = getBankContactInfo(bankName);
    const supplierName =
      decisionDraft.supplier ||
      record.supplierDisplay ||
      record.supplierOfficial ||
      record.supplierRaw ||
      "المورد";
    const guaranteeNo = record.guaranteeNumber || record.guaranteeNo || record.guarantee_no || "-";
    const contractNo = record.contractNumber || record.contractNo || record.contract_no || "-";
    const poNo = record.poNumber || record.poNo || record.po_number || "";
    const guaranteeContext =
      record.guaranteeContext || (contractNo && contractNo !== "-" ? "CONTRACT" : poNo ? "PO" : "UNKNOWN");
    const poDisplay = guaranteeContext === "PO" ? toArabicDigits(poNo) : poNo;
    const refSentence =
      guaranteeContext === "CONTRACT" && contractNo && contractNo !== "-"
        ? `والعائد لعقد رقم (${contractNo})`
        : guaranteeContext === "PO" && poDisplay
          ? `والعائد لأمر الشراء رقم (${poDisplay})`
          : "";
    const guaranteeTypePhrase = getGuaranteeTypePhrase(record.type || record.guaranteeType || record.guarantee_type);
    const amountNum = Number(String(record.amount || "").replace(/[^0-9.-]/g, ""));
    const amount =
      Number.isNaN(amountNum) || !Number.isFinite(amountNum)
        ? record.amount || "-"
        : new Intl.NumberFormat("ar-SA", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            .format(amountNum)
            .replace(/٬/g, ",");
    const renewal = toDisplayOneYear(record.dateRaw) || formatDateValue(record.renewalDateDisplay) || "-";
    const bgUrl = `${window.location.origin}/templates/letter_bg.svg`;
    const bgStyle = showBackground
      ? `background: #ffffff url("${bgUrl}") top center/contain no-repeat;`
      : "background: #ffffff;";
    return `
<style>
  :root {
    font-family: 'Cairo', 'Segoe UI', sans-serif;
    line-height: 1.9;
  }
  @font-face {
    font-family: 'AL-Mohanad';
    src: url('${window.location.origin}/templates/AL-Mohanad Bold.ttf') format('truetype');
    font-weight: bold;
    font-style: normal;
    unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF;
  }
  @font-face {
    font-family: 'ArialBodyCS';
    src: local("Arial (Body CS)"), local("Arial");
    font-weight: normal;
    font-style: normal;
    unicode-range: U+0000-00FF;
  }
  .fw-800-sharp {
    font-weight: 800;
    text-shadow:
      0.015em 0       currentColor,
     -0.015em 0       currentColor,
      0      0.015em  currentColor,
      0     -0.015em  currentColor,
      0.01em  0.01em  currentColor,
     -0.01em  0.01em  currentColor,
      0.01em -0.01em  currentColor,
     -0.01em -0.01em  currentColor;
  }
  .letter-inline,
  .letter-inline * {
    font-family: 'AL-Mohanad', 'ArialBodyCS', 'Arial', sans-serif !important;
    line-height: 23pt;
  }
  .letter-preview {
    background: none;
    padding: 0;
    border: none;
    direction: rtl;
    width: auto;
    min-height: auto;
    margin: 0 auto;
    box-shadow: none;
  }
  .letter-inline .A4 {
    font-size:15pt !important;
    box-sizing: border-box;
    padding-top: 2.2in;
    padding-right: 1in;
    padding-left: 1in;
    padding-bottom: 1in;
    ${bgStyle}
    border: none;
    box-shadow: none;
    width: 210mm;
    min-height: 297mm;
    margin-left: auto;
    margin-right: auto;
    display: block;
    position: relative;
  }
  .subject {
    background: #f0f1f5;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #c8c9d1;
    margin: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .info-list { margin: 12px 0; padding-right: 20px; }
  .info-list li { margin-bottom: 6px; }
  .A4::before { content: none; }
  .A4 p {
    margin: 0;
    text-align: justify;
    text-justify: inter-word;
  }
  .first-paragraph { text-align: justify !important; text-justify: inter-word; }
  .justify-filler { display: inline-block; width: 100%; opacity: 0; }
  .email-wrapper-fixed { direction: rtl; unicode-bidi: isolate; display: inline-block; }
  .arabic-spacer { display: inline-block; width: 0; }
  .email-block {
    display: inline-block;
    direction: ltr;
    unicode-bidi: bidi-override;
    text-align: left;
  }
  .A4 p + p { margin-top: 0; }
</style>
<section class="letter-preview">
  <div class="A4">
        <div class="header-line" style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin:0; line-height:23pt;">
          <div class="to-line fw-800-sharp" style="margin:0; line-height:23pt;">السادة / ${bankName}</div>
          <div class="greeting" style="margin:0; line-height:23pt;">المحترمين</div>
        </div>

        <div style="margin:0; line-height:23pt;">
          <div style="margin:0; line-height:23pt;">
            <div class="fw-800-sharp" style="margin:0; line-height:23pt;">${bankContact.department}</div>
            ${bankContact.addressLines
              .map((line) => `<div class="fw-800-sharp" style="margin:0; line-height:23pt;">${line}</div>`)
              .join("")}
            ${
              bankContact.email
                ? `<div style=\"margin:0; line-height:23pt;\">
              <span class=\"fw-800-sharp\">بريد الكتروني:</span>
              <span style=\"text-shadow:none; font-family:'ArialBodyCS','Arial',sans-serif; font-weight:400;\"> ${bankContact.email}</span>
            </div>`
                : ""
            }
          </div>

          <div style="text-align:right; font-weight:normal; margin:0 0 1px 0; line-height:23pt;">السَّلام عليكُم ورحمَة الله وبركاتِه</div>

          <div class="subject" style="padding: 1px; margin: 0; display:flex; align-items:flex-start; flex-wrap:wrap;">
        <span style="flex:0 0 70px; min-width:70px; margin:0; line-height:23pt;">الموضوع:</span>
        <span style="flex:1 1 0; margin:0; line-height:23pt;">
          طلب تمديد الضمان البنكي رقم (${guaranteeNo}) ${refSentence}
        </span>
      </div>

      <div class="first-paragraph">
        إشارة الى ${guaranteeTypePhrase} الموضح أعلاه، والصادر منكم لصالحنا على حساب ${supplierName} بمبلغ قدره (${amount})، نأمل منكم <span class="fw-800-sharp" style="display:inline;">تمديد فترة سريان الضمان حتى تاريخ ${renewal}</span>، مع بقاء الشروط الأخرى دون تغيير،
        وإفادتنا بذلك من خلال البريد الإلكتروني المخصص للضمانات البنكية لدى مستشفى الملك فيصل التخصصي ومركز الأبحاث بالرياض (bgfinance@kfshrc.edu.sa)، كما نأمل منكم إرسال أصل تمديد الضمان إلى:
      </div>

      <div style="margin:0; line-height:23pt; padding-right:2.5em;">
        <div style="margin:0; line-height:23pt;">مستشفى الملك فيصل التخصصي ومركز الأبحاث – الرياض</div>
        <div style="margin:0; line-height:23pt;">ص.ب ٣٣٥٤ الرياض ١١٢١١</div>
        <div style="margin:0; line-height:23pt;">مكتب الخدمات الإدارية</div>
      </div>

      <div class="first-paragraph" style="margin-top:0; line-height:23pt;">
        علمًا بأنه في حال عدم تمكن البنك من تمديد الضمان المذكور قبل انتهاء مدة سريانه، فيجب على البنك دفع قيمة الضمان إلينا حسب النظام.
      </div>

    <div style="text-indent:5em; margin-top:6px; line-height:23pt; margin-bottom:0;">وَتفضَّلوا بِقبُول خَالِص تحيَّاتِي</div>

    <div class="fw-800-sharp" style="text-align: center; margin-right:17em; line-height:32pt; margin-top:0;">
        مُدير الإدارة العامَّة للعمليَّات المحاسبيَّة<br><br>
        سَامِي بن عبَّاس الفايز
      </div>

    <div style="position:absolute; left:1in; right:1in; bottom:0.7in; display:flex; justify-content:space-between; font-size:8.5pt; line-height:11pt; font-weight:300; font-family:'ArialBodyCS','Arial',sans-serif; text-shadow:none;">
      <span>MBC:09-2</span>
      <span>BAMZ</span>
    </div>
    </div>
  </div>
</section>`;
  }, [record, decisionDraft.bank, decisionDraft.supplier, showBackground]);

  const handlePrint = () => {
    if (!previewHtml) return;
    const bgUrl = `${window.location.origin}/templates/letter_bg.svg`;
    const bgStyle = showBackground
      ? `background: #ffffff url("${bgUrl}") top center/contain no-repeat;`
      : "background: #ffffff;";
    const container = document.createElement("div");
    container.id = "print-container";
    container.innerHTML = `
      <style>
        @page { size: A4; margin: 0; }
        @font-face {
          font-family: 'AL-Mohanad';
          src: url('${window.location.origin}/templates/AL-Mohanad Bold.ttf') format('truetype');
          font-weight: bold;
          font-style: normal;
          unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF;
        }
        @font-face {
          font-family: 'ArialBodyCS';
          src: local("Arial (Body CS)"), local("Arial");
          font-weight: normal;
          font-style: normal;
          unicode-range: U+0000-00FF;
        }
        .fw-800-sharp {
          font-weight: 800;
          text-shadow:
            0.015em 0       currentColor,
           -0.015em 0       currentColor,
            0      0.015em  currentColor,
            0     -0.015em  currentColor,
            0.01em  0.01em  currentColor,
           -0.01em  0.01em  currentColor,
            0.01em -0.01em  currentColor,
           -0.01em -0.01em  currentColor;
        }
        * { -webkit-print-color-adjust: auto; print-color-adjust: auto; }
        #print-container {
          width: 100%;
          display: flex;
          justify-content: center;
          align-items: flex-start;
          background: #fff;
          padding: 0;
          margin: 0;
        }
        #print-container .letter-preview { margin: 0; padding: 0; width: 210mm; }
        #print-container .letter-preview .A4 {
          font-size:15pt !important;
          box-sizing: border-box;
          padding-top: 2.2in;
          padding-right: 1in;
          padding-left: 1in;
          padding-bottom: 1in;
          ${bgStyle}
          border: none;
          box-shadow: none;
          width: 210mm;
          min-height: 297mm;
          margin: 0 auto;
          position: relative;
        }
        #print-container .letter-inline, #print-container .letter-inline * {
          font-family: 'AL-Mohanad', 'ArialBodyCS', 'Arial', sans-serif !important;
        }
      </style>
      <div class="letter-inline">${previewHtml}</div>
    `;
    document.body.appendChild(container);
    setTimeout(() => {
      window.print();
      document.body.removeChild(container);
    }, 400);
  };

  return (
    <div className="single-root" dir="rtl">
      {pasteOpen ? (
        <div className="manual-modal-backdrop" onClick={() => setPasteOpen(false)}>
          <div className="manual-modal" onClick={(e) => e.stopPropagation()}>
            <div className="manual-modal-head">
              <div>
                <div className="manual-title">Paste Input – لصق نص الإيميل</div>
                <div className="manual-subtitle">
                  الصق النص الخام، سنستخرج البنك/المورد/الضمان/المبلغ/التاريخ/العقد أو PO ويمر عبر runExcelPipeline.
                </div>
              </div>
              <button type="button" className="uc-btn inline" onClick={() => setPasteOpen(false)}>
                ✖
              </button>
            </div>
            <div className="manual-card">
              <label className="manual-field">
                <span>النص الخام *</span>
                <textarea
                  className="paste-area"
                  rows={10}
                  value={pasteText}
                  onChange={(e) => setPasteText(e.target.value)}
                  placeholder="الصق هنا نص الإيميل الذي يحتوي على بيانات الضمان..."
                />
              </label>
              <div className="manual-actions">
                <button type="button" className="single-btn primary" onClick={handlePasteSubmit}>
                  📋 إضافة سجل من النص
                </button>
                <button
                  type="button"
                  className="single-btn ghost"
                  onClick={() => {
                    setPasteText("");
                  }}
                >
                  🧹 تفريغ النص
                </button>
                <span className="manual-hint">
                  يجب أن يتوفر: رقم الضمان + اسم المورد + اسم البنك + المبلغ + تاريخ الانتهاء + (عقد أو PO).
                </span>
              </div>
            </div>
          </div>
        </div>
      ) : null}
      {manualOpen ? (
        <div className="manual-modal-backdrop" onClick={() => setManualOpen(false)}>
          <div className="manual-modal" onClick={(e) => e.stopPropagation()}>
            <div className="manual-modal-head">
              <div>
                <div className="manual-title">إدخال يدوي / لصق نصي</div>
                <div className="manual-subtitle">
                  يمر عبر runExcelPipeline لإنتاج خطاب بدون Excel. الحقول الإلزامية: رقم الضمان + اسم المورد + اسم البنك + المبلغ + تاريخ الانتهاء + (رقم العقد أو أمر الشراء).
                </div>
              </div>
              <button type="button" className="uc-btn inline" onClick={() => setManualOpen(false)}>
                ✖
              </button>
            </div>
            <div className="manual-card">
              <div className="manual-grid">
                <label className="manual-field">
                  <span>البنك *</span>
                  <input
                    type="text"
                    value={manualForm.bank}
                    onChange={(e) => setManualForm((m) => ({ ...m, bank: e.target.value }))}
                    placeholder="أدخل اسم البنك"
                  />
                </label>
                <label className="manual-field">
                  <span>المورد *</span>
                  <input
                    type="text"
                    value={manualForm.supplier}
                    onChange={(e) => setManualForm((m) => ({ ...m, supplier: e.target.value }))}
                    placeholder="أدخل اسم المورد"
                  />
                </label>
                <label className="manual-field">
                  <span>رقم الضمان *</span>
                  <input
                    type="text"
                    value={manualForm.guaranteeNo}
                    onChange={(e) => setManualForm((m) => ({ ...m, guaranteeNo: e.target.value }))}
                    placeholder="رقم الضمان"
                  />
                </label>
                <label className="manual-field">
                  <span>المبلغ *</span>
                  <input
                    type="text"
                    value={manualForm.amount}
                    onChange={(e) => setManualForm((m) => ({ ...m, amount: e.target.value }))}
                    placeholder="مثال: 123456.00"
                  />
                </label>
                <label className="manual-field">
                  <span>تاريخ الانتهاء *</span>
                  <input
                    type="date"
                    value={manualForm.dateRaw}
                    onChange={(e) => setManualForm((m) => ({ ...m, dateRaw: e.target.value }))}
                  />
                </label>
                <label className="manual-field">
                  <span>رقم العقد * (أحدهما مطلوب)</span>
                  <input
                    type="text"
                    value={manualForm.contractNo}
                    onChange={(e) => setManualForm((m) => ({ ...m, contractNo: e.target.value }))}
                    placeholder="اختياري"
                  />
                </label>
                <label className="manual-field">
                  <span>رقم أمر الشراء * (أحدهما مطلوب)</span>
                  <input
                    type="text"
                    value={manualForm.poNo}
                    onChange={(e) => setManualForm((m) => ({ ...m, poNo: e.target.value }))}
                    placeholder="اختياري"
                  />
                </label>
                <label className="manual-field">
                  <span>نوع الضمان</span>
                  <input
                    type="text"
                    value={manualForm.type}
                    onChange={(e) => setManualForm((m) => ({ ...m, type: e.target.value }))}
                    placeholder="مثال: advance/bid/final"
                  />
                </label>
              </div>
              <div className="manual-actions">
                <button type="button" className="single-btn primary" onClick={handleManualSubmit}>
                  ➕ إضافة سجل يدوي
                </button>
                <button
                  type="button"
                  className="single-btn ghost"
                  onClick={() => setManualForm({ ...emptyManual })}
                >
                  🧹 تفريغ الحقول
                </button>
                <span className="manual-hint">
                  أدخل جميع الحقول، مع رقم عقد أو أمر شراء واحد على الأقل.
                </span>
              </div>
            </div>
          </div>
        </div>
      ) : null}
      <div className="single-decision-panel">
        <div
          className="single-head single-head-actions"
          style={{ display: "flex", alignItems: "center", gap: 10 }}
        >
          <button
          className={`single-btn ghost ${activeTab === "decision" ? "tab-active" : ""}`.trim()}
          onClick={() => setActiveTab("decision")}
        >
          اتخاذ القرار
        </button>
        <button
          className={`single-btn ghost ${activeTab === "scpe" ? "tab-active" : ""}`.trim()}
          onClick={() => setActiveTab("scpe")}
        >
          إدارة القاموس (SCPE)
        </button>
          {adminButtonVisible ? (
            <AdminToggleButton onClick={openAdminModal} className="single-btn ghost" />
          ) : null}

          <div className="utility-inline" style={{ marginInline: "auto" }}>
            <button
              type="button"
              className="uc-btn inline"
              onClick={() => document.getElementById("single-upload-input")?.click()}
              title="رفع ملف Excel"
            >
              📁
            </button>
            <button
              type="button"
              className={`uc-btn inline ${manualOpen ? "active" : ""}`.trim()}
              onClick={() => setManualOpen((v) => !v)}
              title="إدخال يدوي / لصق نصي"
            >
              ✍️
            </button>
            <button
              type="button"
              className={`uc-btn inline ${pasteOpen ? "active" : ""}`.trim()}
              onClick={() => setPasteOpen((v) => !v)}
              title="لصق نص من الإيميل"
            >
              📋
            </button>
            <input
              id="single-upload-input"
              type="file"
              accept=".xlsx"
              multiple={false}
              style={{ display: "none" }}
              onChange={(e) => {
                const file = e.target.files?.[0] || null;
                onUpload?.(e, file);
                setTimeout(() => onAnalyze?.(file), 0);
              }}
            />
            <div className="uc-stats" title="ملخص الحالة">
              <span
                className="uc-chip total"
                aria-label="إجمالي السجلات المحملة"
                title="إجمالي السجلات بعد التنظيف (صفوف فارغة أو مكررة يتم إسقاطها أثناء القراءة)"
              >
                📄 {totalRecords}
              </span>
              <span
                className="uc-chip ready"
                aria-label="جاهز"
                title="عدد السجلات المكتملة الجاهزة للتصدير"
              >
                ✔ {readyUI}
              </span>
              <span
                className="uc-chip warn"
                aria-label="يحتاج قرار"
                title="سجلات ما زالت تنتظر قرار بنك/مورد"
              >
                ❗ {needsDecisionCount}
              </span>
              <span
                className="uc-chip review"
                aria-label="مراجعة"
                title="سجلات تحت المراجعة أو مطابقة غامضة (قد تكون جزءًا من الجاهز إذا لم تعد تحتاج قرار)"
              >
                🔍 {reviewCount}
              </span>
            </div>
            <button
              type="button"
              className={`uc-btn inline ${exportReady ? "" : "disabled"}`}
              onClick={onExport}
              disabled={!exportReady}
              title={exportReady ? "تصدير CSV" : "أكمل القرارات أولاً"}
            >
              ⬇️
            </button>
          </div>

          <button
            type="button"
            className="single-btn ghost"
            onClick={handlePrint}
            style={{ marginInlineStart: "auto", marginInlineEnd: 8 }}
          >
            🖨️ طباعة المعاينة
          </button>
          <button
            type="button"
            className="single-btn ghost"
            onClick={onPrintAllReady}
            disabled={!readyCount}
            title={readyCount ? `طباعة كل السجلات الجاهزة (${readyCount})` : "لا توجد سجلات جاهزة للطباعة"}
          >
            🖨️ طباعة كل الجاهز
          </button>
        </div>

        <div className="single-fields">
          <div className="single-field">
            <label>البنك</label>
            <select
              value={decisionDraft.bank}
              onChange={(e) => setDecisionDraft((d) => ({ ...d, bank: e.target.value }))}
              disabled={!bankOptions || bankOptions.length === 0}
            >
              <option value="">اختر البنك</option>
              {bankOptions.map((b) => (
                <option key={b} value={b}>
                  {b}
                </option>
              ))}
            </select>
          </div>

          <div className="single-field">
            <label>المورد</label>
            <SupplierTypeahead
              value={decisionDraft.supplier}
              onChange={(val, sid) => setDecisionDraft((d) => ({ ...d, supplier: val, supplierId: sid }))}
              supplierId={record?.supplierId || decisionDraft.supplierId || null}
              fallbackName={
                record?.supplierDisplay || record?.supplierOfficial || record?.supplierRaw || ""
              }
              suppliersCanonical={suppliersCanonical}
              disabled={!suppliersCanonical || suppliersCanonical.length === 0}
              placeholder="بحث / اختيار مورد"
            />
          </div>
        </div>

        <div className="single-nav">
          <button
            type="button"
            className="single-btn ghost"
            onClick={onPrev}
            disabled={!records?.length || currentIndex <= 0}
          >
            ▶ السابق
          </button>
          <button
            type="button"
            className="single-btn primary"
            onClick={() => {
              // لوج تشخيصي للحالة الحالية للسجل قبل الحفظ
              // eslint-disable-next-line no-console
              console.log("[save] before", {
                id: record?.id,
                supplierId: record?.supplierId,
                supplierStatus: record?.supplierStatus,
                bankStatus: record?.bankStatus,
                needsDecision: record?.needsDecision,
              });
              const bankCurrent = record?.bankOfficial || record?.bankDisplay || record?.bankRaw || "";
              const supplierCurrent =
                record?.supplierOfficial || record?.supplierDisplay || record?.supplierRaw || "";
              // استخدم قيمة المسودة أو الحالية لضمان عدم الإرسال بقيم فارغة
              const bankFinal = decisionDraft.bank || bankCurrent;
              const supplierFinal = decisionDraft.supplier || supplierCurrent;
              const supplierIdFinal = decisionDraft.supplierId || record?.supplierId || null;
              const bankChanged = record?.needsDecision || bankFinal !== bankCurrent;
              const supplierChanged = record?.needsDecision || supplierFinal !== supplierCurrent;
              onSave(bankFinal, supplierFinal, supplierIdFinal, { bankChanged, supplierChanged });
            }}
            disabled={!record}
          >
            ✔ حفظ السجل رقم (
            <input
              type="number"
              min={1}
              max={Math.max(total, 1)}
              value={jumpValue}
              onClick={(e) => e.stopPropagation()}
              onChange={(e) => setJumpValue(Number(e.target.value) || 1)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && onJumpIndex) {
                  e.stopPropagation();
                  const targetIdx = clamp((jumpValue || 1) - 1, 0, Math.max(total - 1, 0));
                  onJumpIndex(targetIdx);
                }
              }}
              onBlur={(e) => {
                e.stopPropagation();
                if (!onJumpIndex) return;
                const targetIdx = clamp((jumpValue || 1) - 1, 0, Math.max(total - 1, 0));
                onJumpIndex(targetIdx);
              }}
              className="inline-number-input"
              title="اذهب مباشرة إلى رقم سجل محدد"
            />
            من {total || 0}) والانتقال إلى التالي
          </button>
          <button
            type="button"
            className="single-btn ghost"
            onClick={onNext}
            disabled={!records?.length || currentIndex >= total - 1}
          >
            التالي ◀
          </button>
        </div>
      </div>

      <div className="single-main">
        <div className="single-preview-card" style={{ borderColor }}>
          <div className="letter-iframe-wrap centered" ref={wrapRef}>
            {previewHtml ? (
              <div
                className="letter-inline"
                style={{
                  transform: `scale(${scale})`,
                  transformOrigin: "top center",
                  width: scaledWidth,
                }}
                dangerouslySetInnerHTML={{ __html: previewHtml }}
              />
            ) : (
              <div className="sv-empty">اختر سجلاً لعرضه</div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
