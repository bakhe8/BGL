# التحديثات البسيطة للتايم لاين

## الهدف
إضافة روابط واضحة للـ batch/session **بدون تغيير التصميم الحالي**

---

## التغيير المطلوب

### في `guarantee-history.js`

**إضافة سطر واحد** في كل timeline item:

```javascript
// Line ~237 (بعد badges)
html += `
    <div class="timeline-header">
        <div>
            <span class="session-badge">جلسة #${item.session_id}</span>
            ${actionBadge}
            ${statusBadge}
        </div>
    </div>
    <div class="timeline-date">${formattedDate}</div>
    
    <!-- أضف هذا: -->
    <div class="timeline-source">
        ${getSourceLink(item)}
    </div>
    
    <div class="timeline-info">
        ...
`;

// دالة helper جديدة
function getSourceLink(item) {
    // إجراء (extension/release)
    if (item.record_type && item.record_type !== 'import') {
        return `📋 <a href="/?session_id=${item.session_id}">إجراءات ${formatDate(item.date)}</a>`;
    }
    
    // استيراد (import)
    // Note: نحتاج batch_id من الـ API
    if (item.import_batch_id) {
        return `📦 <a href="/?batch_id=${item.import_batch_id}">مجموعة #${item.import_batch_id}</a>`;
    }
    
    return '';
}
```

### في `guarantee-history.php`

**إضافة batch_id** للاستيرادات:

```php
// Line ~57
$history[] = [
    'id' => $r['id'],
    'record_id' => $r['id'],
    'session_id' => $r['session_id'],
    'import_batch_id' => $r['import_batch_id'], // ← أضف هذا
    // ...
];
```

### CSS

```css
.timeline-source {
    font-size: 12px;
    color: #666;
    margin-top: 6px;
    padding: 4px 0;
}

.timeline-source a {
    color: #1976D2;
    text-decoration: none;
    font-weight: 500;
}

.timeline-source a:hover {
    text-decoration: underline;
}
```

---

## النتيجة

التايم لاين الحالي **بدون تغيير** + سطر إضافي فقط:

```
┌─────────────────────────────┐
│ 🔵 تمديد  ✅ جاهز         │
│ 2025-12-20 03:00 PM        │
│ 📋 إجراءات اليوم          │ ← هذا السطر جديد
│                             │
│ المورد: شركة ABC            │
│ البنك: البنك الوطني        │
└─────────────────────────────┘
```

**بسيط وواضح!** ✨
