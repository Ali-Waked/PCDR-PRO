# Audit Archives User Guide / دليل استخدام أرشيف سجلات التدقيق

Audience: administrators with `audit_archives.view`, `audit_archives.download` or `audit_archives.manage`.
Applies to: `/{locale}/admin/audit-archives` in Arabic and English.

## English

### What an archive is

An audit archive is a private monthly evidence package. It contains:

- `audit-logs.csv` — UTF-8 spreadsheet export with a byte-order mark for Arabic text.
- `audit-logs.jsonl` — one JSON object per audit event.
- `manifest.json` — period, cutoff, record count, schema version, source, format and SHA-256 checksums.

The ZIP is stored on the private disk. It is never exposed through a public URL.

### Lifecycle

| Status | Meaning | Who can change it |
| --- | --- | --- |
| `pending` | Registered and waiting for the queue worker. | System |
| `processing` | The worker is generating and inspecting the ZIP. | System |
| `verified` | File exists and checksums/manifest/record count match. Downloads unlock. | System |
| `failed` | Generation or verification did not complete. The error is recorded. | System; reset manually before retry |
| `pruned` | Source events were removed by approved retention. Final state. | System only |

Allowed paths: `pending → processing → verified|failed`; `failed → pending` (retry) or `failed → verified` (only after a valid file inspection); `verified → pruned`. `verified → processing` and `pruned → verified` are blocked centrally.

### Create an archive

1. Open **Audit archives**.
2. Read the blue information alert at the top of the page.
3. In **Generate archive**, choose a completed month in `YYYY-MM` format. Leave it empty for the previous complete month.
4. Click **Generate archive** and confirm in the global dialog.
5. The page immediately shows a `pending` row with a queued time. No generation runs inside the web request.
6. Click **Refresh status** until the row becomes `verified`. If it becomes `failed`, open **View details** to read the error.

To retry a failed archive, use **Retry failed archive** on the row and provide a reason. The old attempt stays in **Status history**.

### Search, filter, sort and paginate

Use the filter form to combine:

- free-text search across name, period key, status and creator;
- exact status, source and month;
- year;
- sort column and ascending/descending direction;
- 10, 20 or 50 rows per page.

Click a sortable column heading (**Period**, **Status**, **Records**, **Size**) to toggle direction. Pagination keeps the current filters. **Clear filters** restores the defaults.

### View details

**View details** shows exact period boundaries, cutoff, attempts, queued/started/finished/failed times, checksums, manifest and the full status history with actor and reason. The `period_end_inclusive` field is the last second included in the archive.

### Download

Only `verified` archives can be downloaded. The server rechecks the private file and its checksums before streaming; every successful download is written to the audit log. If the file is missing or fails verification, the download is refused and an error is shown.

### Editing and status changes

**Edit name** changes only the display name, never the ZIP filename or source events. Manual transitions require a reason and are recorded with your name. A failed archive with a valid file can be verified after inspection; this is an explicit recovery action, not a normal retry.

### Automatic archiving

Automatic settings live in **CMS Site Settings → Audit archive automation**. They are private and never appear in the public site payload. The scheduler only decides which completed months are due; a dedicated `audit` queue worker must be running for verification to finish. See `docs/AUDIT_ARCHIVES_OPERATIONS.md` for the exact commands.

### Help button

The **Help / User guide** button opens the same bilingual guide inside the application, so the Arabic and English wording always matches the deployed UI.

---

## العربية

### ما هو الأرشيف

الأرشيف حزمة إثبات شهرية خاصة تحتوي على:

- `audit-logs.csv` — تصدير CSV بترميز UTF-8 مع علامة ترتيب بايت لدعم النص العربي في جداول البيانات.
- `audit-logs.jsonl` — كائن JSON لكل حدث تدقيق.
- `manifest.json` — الفترة وتاريخ القطع وعدد السجلات وإصدار المخطط والمصدر والصيغة ومجموعات التحقق SHA-256.

يُحفظ ملف ZIP على قرص خاص، ولا يُعرض أبدًا عبر رابط عام.

### دورة الحالة

| الحالة | المعنى | من يغيّرها |
| --- | --- | --- |
| `pending` | مسجّل وينتظر عامل الصف. | النظام |
| `processing` | العامل ينشئ ملف ZIP ويفحصه. | النظام |
| `verified` | الملف موجود ومجموعات التحقق وعدد السجلات مطابقة. يسمح التنزيل. | النظام |
| `failed` | لم يكتمل الإنشاء أو التحقق، مع تسجيل الخطأ. | النظام؛ تُعاد تهيئته يدويًا قبل إعادة المحاولة |
| `pruned` | حُذفت الأحداث المصدرية وفق احتفاظ معتمد. حالة نهائية. | النظام فقط |

المسارات المسموحة: `pending ← processing ← verified|failed`؛ و`failed ← pending` لإعادة المحاولة أو `failed ← verified` بعد فحص ملف صالح؛ و`verified ← pruned`. الانتقال من `verified` إلى `processing` ومن `pruned` إلى `verified` ممنوع مركزيًا.

### إنشاء أرشيف

1. افتح **أرشيف سجلات التدقيق**.
2. اقرأ التنبيه الأزرق في أعلى الصفحة.
3. في **إنشاء الأرشيف** اختر شهرًا مكتملًا بصيغة `YYYY-MM`. اتركه فارغًا لاختيار الشهر المكتمل السابق.
4. اضغط **إنشاء الأرشيف** ثم أكّد في نافذة التأكيد العامة.
5. يظهر فورًا صف بحالة `pending` ووقت الإضافة إلى الصف. لا يتم الإنشاء داخل طلب الويب.
6. اضغط **تحديث الحالة** حتى تصبح الحالة `verified`. إذا ظهرت `failed` افتح **عرض التفاصيل** لقراءة الخطأ.

لإعادة محاولة أرشيف فاشل استخدم **إعادة محاولة الأرشيف الفاشل** في الصف وأدخل سببًا. تبقى المحاولة السابقة مسجّلة في **سجل الحالات**.

### البحث والفلاتر والترتيب والصفحات

يمكنك الجمع بين:

- بحث نصي في الاسم والمشهر والحالة والمُنشئ؛
- حالة ومصدر وشهر محددين؛
- السنة؛
- عمود الترتيب واتجاه تصاعدي/تنازلي؛
- 10 أو 20 أو 50 صفًا في الصفحة.

اضغط على عنوان عمود قابل للترتيب (**الفترة**، **الحالة**، **السجلات**، **الحجم**) لتبديل الاتجاه. تحتفظ الصفحات التالية بالفلاتر. **مسح الفلاتر** يعيد الإعدادات الافتراضية.

### عرض التفاصيل

يعرض **عرض التفاصيل** حدود الفترة الدقيقة وتاريخ القطع وعدد المحاولات وأوقات الإضافة والبداية والانتهاء والفشل ومجموعات التحقق والبيان وسجل الحالات الكامل مع المُنفّذ والسبب. حقل `period_end_inclusive` يمثل آخر ثانية مشمولة في الأرشيف.

### التنزيل

يمكن تنزيل الأرشيفات الموثّقة فقط. يعيد الخادم فحص الملف الخاص ومجموعات التحقق قبل إرساله، ويُسجّل كل تنزيل ناجح في سجل التدقيق. إذا كان الملف مفقودًا أو فشل التحقق يُمنع التنزيل ويظهر خطأ.

### التعديل وتغيير الحالة

**تعديل الاسم** يغيّر اسم العرض فقط، ولا يغيّر اسم ملف ZIP ولا الأحداث المصدرية. تتطلب تغييرات الحالة اليدوية سببًا وتُسجّل باسمك. يمكن توثيق أرشيف فاشل إذا كان ملفه صالحًا بعد الفحص؛ هذا إجراء استرداد صريح وليس إعادة محاولة اعتيادية.

### الأرشفة التلقائية

تضبط الإعدادات التلقائية في **إعدادات الموقع ← أتمتة أرشيف التدقيق**. هذه الإعدادات خاصة ولا تظهر في بيانات الموقع العامة. يكتفي المجدول بتحديد الفترات المستحقة، ويجب تشغيل عامل صف `audit` مستقل لإكمال التحقق. الأوامر الدقيقة في `docs/AUDIT_ARCHIVES_OPERATIONS.md`.

### زر المساعدة

يفتح زر **المساعدة / دليل الاستخدام** الدليل نفسه بلغة الواجهة الحالية، بحيث تكون الصياغة العربية والإنجليزية مطابقة دائمًا للواجهة المنشورة.
