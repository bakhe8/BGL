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

  // Selection state
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
window.BGL.Utils = {
  escapeHtml: (str = '') => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
};
