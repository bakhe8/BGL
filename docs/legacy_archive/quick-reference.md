# Sessions vs Batches - Quick Reference

## For Developers

### When to use Session?
✅ Creating an **extension** for a guarantee  
✅ Issuing a **release** letter  
✅ Any **action** performed within the system

→ Use `ImportSessionRepository::getOrCreateDailySession()`

### When to use Batch?
✅ Importing an **Excel file**  
✅ **Manual entry** of new records  
✅ **Pasting text** from external source

→ Use `ImportBatchRepository::create()` or `getOrCreateDaily...()`

---

## Golden Rule

**Actions** → Sessions  
**Imports** → Batches

---

## Common Mistakes

❌ Creating a new session for each action  
✅ Use `getOrCreateDailySession()` instead

❌ Using batch for actions  
✅ Use session for actions

❌ Using session for imports  
✅ Use batch for imports

---

For detailed documentation, see: `docs/sessions-vs-batches.md`
