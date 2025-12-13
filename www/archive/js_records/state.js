/**
 * Records State Management
 * Initializes the BGL Global Namespace to prevent scope pollution.
 */
window.BGL = window.BGL || {};

// 1. Global State
window.BGL.State = {
  records: [],
  supplierCache: [],
  bankMap: {},       // id => {official_name, ...}
  bankNormMap: {},   // normalized_key => display
  supplierMap: {},   // id => {official_name, ...}

  // === NEW: Inline Edit State ===
  editing: {
    recordId: null,        // السجل قيد التعديل
    field: null,           // 'supplier' أو 'bank'
    originalValue: null,   // للاستعادة عند الإلغاء
    pendingValue: null,    // القيمة المختارة قبل الحفظ
    isSaving: false,       // للمؤشر البصري
    candidates: []         // المرشحون المحملون من API
  },

  // القيم المختارة لكل سجل (للحفظ المؤقت قبل الإرسال)
  pendingDecisions: {},    // { [recordId]: { supplierId, supplierName, bankId, bankName } }

  // === LEGACY: Kept for backward compatibility with Panel ===
  selectedRecord: null,
  selectedSupplierName: null,
  selectedSupplierId: null,
  selectedSupplierBlockedId: null,
  selectedBankName: null,
  selectedBankId: null,

  // Flags
  autoSaved: false
};

// 2. Configuration & Constants
window.BGL.Config = {
  sortKey: 'id',
  sortDir: 'desc',
  panelRowId: 'inline-panel-row',
  PANEL_HTML_TEMPLATE: `
      <td colspan="7" class="p-0 border-b border-blue-200">
        <div class="panel-container bg-blue-50 border-l-4 border-blue-500 shadow-inner p-4 animate-slideDown">
          <div class="grid grid-cols-12 gap-6" id="panel-content">
            <div class="col-span-12 text-center text-gray-500 py-8">
              جارٍ تحميل التفاصيل...
            </div>
          </div>
        </div>
      </td>
    `
};

// 3. DOM Cache (Populated in init.js)
window.BGL.DOM = {
  tableBody: null,
  sessionFilter: null,
  statusFilter: null,
  refreshBtn: null,
  paginationInfo: null,
  // ... others added as needed
};

// 4. Shared Utilities
// escapeHtml is defined in api.js as window.escapeHtml
// We expose it through BGL.Utils for consistency
window.BGL.Utils = {
  escapeHtml: window.escapeHtml || ((str = '') => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;'))
};
