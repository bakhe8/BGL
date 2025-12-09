# Feature List

Below is the complete catalog of features included in the project.

---

## 1. Data Input Features
### ✔ Excel Import
- Supports multiple layouts.
- Validates missing, malformed, or misformatted values.
- Extracts dates, amounts, and reference numbers.

### ✔ Manual Entry
- Add individual records directly from the UI.

### ✔ Paste Input
- Paste structured rows (from Excel or text).
- Automatic detection and parsing.

---

## 2. Dictionary Features
### ✔ Supplier Dictionary
- Add, edit, delete suppliers.
- All fields stored in SQLite.

### ✔ Alternative Names
- Stores all different raw names linking to the same supplier.
- Tracks usage count & last used timestamp.

### ✔ Overrides
- Forced names that take highest priority during matching.

### ✔ Normalization Rules
- Character cleaning  
- Unicode normalization  
- Removing trailing numbers/symbols  
- Arabic linguistic corrections  

---

## 3. Matching Engine Features
- Multi‑layer matching:
  1. **Official name**
  2. **Alternative names (confirmed)**
  3. **Overrides**
  4. **Alternative names (unconfirmed)**
  5. **Raw matching**

- Confidence scoring  
- Detailed logs of each match decision  

---

## 4. Review Panel Features
- Compare suggested supplier with alternatives.
- Display confidence score.
- Edit or override suggested name.
- Approve or reject matches.
- Add new suppliers directly from panel.
- Add new alternative names.

---

## 5. Export Features
- Export clean data to new Excel file.
- Export dictionary (CSV/JSON).
- Export audit logs for debugging.

---

## 6. System Infrastructure Features
- Entire system runs offline.
- Portable — copy the folder to any machine.
- SQLite storage (zero configuration).
- Precompiled TailwindCSS (fast & lightweight).
- PHP Desktop runtime (no server required).
- All logs stored locally.

