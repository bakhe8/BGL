/**
 * Dictionary Overlay Logic
 * Handles creating new suppliers via the popup overlay.
 */

window.BGL = window.BGL || {};

window.BGL.Overlay = {

    el: null,
    input: null,
    msg: null,

    init() {
        // Inject HTML if not exists
        if (!document.getElementById('dict-overlay')) {
            const div = document.createElement('div');
            div.id = 'dict-overlay';
            div.className = 'fixed inset-0 bg-black bg-opacity-50 z-[100] hidden items-center justify-center';
            div.innerHTML = `
           <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 transform transition-all scale-100">
             <h3 class="text-lg font-bold text-gray-800 mb-4">إضافة مورد جديد</h3>
             <div class="mb-4">
               <label class="block text-sm text-gray-700 mb-1">الاسم الرسمي للمورد</label>
               <input type="text" id="newSupplierName" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
             </div>
             <div id="overlayMsg" class="mb-4 text-sm font-bold min-h-[20px]"></div>
             <div class="flex justify-end gap-3">
               <button id="btnCancelDict" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">إلغاء</button>
               <button id="btnSaveDict" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">حفظ وإضافة</button>
             </div>
           </div>
        `;
            document.body.appendChild(div);

            // Bind Events
            document.getElementById('btnCancelDict').addEventListener('click', () => this.close());
            document.getElementById('btnSaveDict').addEventListener('click', () => this.save());
        }

        this.el = document.getElementById('dict-overlay');
        this.input = document.getElementById('newSupplierName');
        this.msg = document.getElementById('overlayMsg');
    },

    open(initialName = '') {
        this.init();
        this.input.value = initialName;
        this.msg.textContent = '';
        this.el.classList.remove('hidden');
        this.el.classList.add('flex');
        this.input.focus();
    },

    close() {
        if (this.el) {
            this.el.classList.add('hidden');
            this.el.classList.remove('flex');
        }
    },

    async save() {
        const name = this.input.value.trim();
        if (!name) {
            this.msg.textContent = 'الاسم مطلوب';
            this.msg.className = 'text-red-500';
            return;
        }

        this.msg.textContent = 'جارٍ الحفظ...';
        this.msg.className = 'text-blue-500';

        try {
            const res = await api.post('/api/dictionary/suppliers', { official_name: name });

            if (res.success) {
                this.msg.textContent = 'تمت الإضافة بنجاح';
                this.msg.className = 'text-green-500';

                // Update Global Cache
                await window.loadSuppliersCache(true);

                // Select in Panel
                BGL.State.selectedSupplierId = res.data.id;
                BGL.State.selectedSupplierName = name;

                const input = document.getElementById('supplierInput');
                if (input) input.value = name;

                setTimeout(() => this.close(), 500);
            } else {
                throw new Error(res.message);
            }
        } catch (e) {
            this.msg.textContent = 'خطأ: ' + e.message;
            this.msg.className = 'text-red-500';
        }
    }
};
