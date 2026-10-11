# مرجع المشروع — كل ما تم وما بقي

> ابدأ من هنا. هذا الملف يلخّص المشروع كاملًا: ما الذي بُني، أين يعيش كل شيء، كيف تشغّله وتحدّثه، وما المؤجّل مع خطواته.
> آخر تحديث: 11 أكتوبر 2026.

**الموقع الحي:** https://nafha.great-site.net · **الكود:** https://github.com/anasAboAli/nafha-perfume-store
**المعلومات الخاصة** (أسماء الحسابات، المستخدمين، قاعدة البيانات): ملف `PRIVATE-NOTES.md` في جذر المشروع على جهازك — **غير مرفوع** على GitHub. كلمات المرور ليست في أي ملف؛ هي في مدير كلمات المرور عندك.

---

## 1. ما هو المشروع
متجر عطور بالتقسيم (عينات 5/10/30 مل + عبوات كاملة + باقات هدايا) بالعربية (RTL)، للجوال أولًا، توصيل داخل قطر، الأسعار بالدولار.
مشروع لمعرض الأعمال: منشور مجانًا بالكامل (استضافة ونطاق فرعي مجانيان)، ولا يظهر في الموقع أي وصف "تجريبي".

**التقنيات:** WordPress 7.1 · WooCommerce 11.2 · قالب Blocksy + قالب فرعي `nafha-child` · إضافة مخصصة `nafha-core` · PHP 8.2 · Rank Math · Cloudflare Turnstile · Playwright · Lighthouse.

## 2. مراحل العمل (كلها منجزة)
| # | المرحلة | ملخص |
|---|---|---|
| 1–2 | البيئة والإعداد | XAMPP محلي، ووردبريس عربي، ووكومرس، القالب، العملة USD، مناطق قطر |
| 3 | الهوية والقالب | الألوان، الخطوط المحلية، الشريط السفلي للجوال، شريط الإعلانات |
| 4 | المنتجات | أحجام بأزرار وسعر لكل حجم، ملف العطر (نوتات/ثبات/انتشار)، العلامات، 14 منتجًا بصور مولّدة |
| 5 | المتجر والبحث | فلاتر AJAX بعدّادات، بحث ذكي بالعربي والإنجليزي والنوتات |
| 6 | الميزات الخاصة | اختبار "شو عطرك المناسب؟"، صندوق التجربة، خصم العينات، باقات الهدايا، مراجعات بالصور |
| 7 | السلة والدفع | سلة جانبية + شريط الشحن المجاني، دفع مختصر، تحقق رقم الجوال القطري، الدفع عند الاستلام، الطلب عبر واتساب، تحويل بنكي |
| 8 | التتبع والسيو | أحداث GA4 جاهزة في dataLayer، Consent Mode v2 + شريط كوكيز، Rank Math، خلاصة منتجات |
| 9 | الأداء والأمان | Lighthouse ~90، jQuery للفوتر، CSS مدمج، WebP، Turnstile، رابط دخول مخفي، رؤوس أمان |
| 10 | الاختبار والتسليم | 24 اختبار متصفح آلي، فحص الترجمة، الأدلة |
| — | النشر المجاني | InfinityFree + Brevo + Turnstile + GitHub (التفاصيل أدناه) |

تفاصيل كل مرحلة: [PLAN.md](PLAN.md) · نتائج القبول: [HANDOVER.md](HANDOVER.md).

## 3. أين يعيش كل شيء

### على جهازك
| | المسار |
|---|---|
| المشروع | `D:\wordPress\perfume-store` |
| الموقع المحلي | http://localhost:8080 (Apache منفذ 8080، قاعدة بيانات `perfume_store`) |
| دخول المدير المحلي | http://localhost:8080/nafha-login/ — البيانات في `.local-credentials.txt` |
| حزمة النشر | `dist/` (تُنشأ بالأمر، غير مرفوعة) |
| نسخ قاعدة البيانات | `backups/` |

### على الإنترنت (حسابات مجانية)
| الخدمة | الاستخدام | الحالة |
|---|---|---|
| **InfinityFree** | الاستضافة + النطاق `nafha.great-site.net` + SSL (شهادة النطاق الأم تغطيه تلقائيًا) | ✅ |
| **Cloudflare Turnstile** | حماية الدخول والتسجيل والمراجعات — widget باسم `Nafha` | ✅ |
| **Brevo** | إرسال الإيميلات (300/يوم مجانًا)، المرسل "نفحة" مُتحقق منه | ✅ |
| **FluentSMTP** (إضافة على الموقع الحي فقط) | توصل ووردبريس بـ Brevo عبر API | ✅ تم اختبار وصول إيميل الطلب للمدير والعميل |
| **GitHub** | الكود + الصور + README | ✅ عام |
| Google Tag Manager / GA4 | تتبع الزوار والمبيعات | ⏸ مؤجّل |
| Google Search Console | الظهور في جوجل | ⏸ مؤجّل |

### الكود
| المجلد | المحتوى |
|---|---|
| `public/wp-content/plugins/nafha-core/` | كل ميزات المتجر (ملف لكل ميزة في `includes/` — الجدول في [README](../README.md)) |
| `public/wp-content/themes/nafha-child/` | المظهر فقط |
| `tools/setup/` | سكربتات إعداد المتجر والمحتوى والمنتجات |
| `tools/e2e/` | اختبارات المتصفح، فحص الترجمة، صور الشاشة |
| `tools/deploy/` | بناء حزمة النشر |
| `docs/` | الأدلة (القائمة في القسم 7) |

ملفات ووردبريس نفسها والإضافات الخارجية **غير مرفوعة** على GitHub (تُحمَّل من مصادرها)؛ المرفوع هو الكود الخاص بنا فقط.

## 4. الفروق بين الموقع المحلي والحي
| | المحلي | الحي |
|---|---|---|
| Wordfence، UpdraftPlus | مفعّلتان | **غير موجودتين** (ثقيلتان على الاستضافة المجانية) |
| FluentSMTP | غير موجودة (الإيميلات تُحفظ في `uploads/nafha-mail/`) | مفعّلة ومربوطة بـ Brevo |
| مفاتيح Turnstile | مفاتيح اختبار | المفاتيح الحقيقية |
| اسم المدير | المحلي | اسم جديد بكلمة مرور عشوائية (في `PRIVATE-NOTES.md` الاسم فقط) |
| الطلبات | — | ما يطلبه الزوار |

## 5. تشغيل المشروع محليًا
1. شغّل XAMPP (Apache + MySQL) ثم افتح http://localhost:8080.
2. **إذا لم تعمل MySQL** (تحدث بعد إطفاء الجهاز المفاجئ):
   - شغّلها يدويًا لترى السبب: `C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone --console`
   - "Aria recovery failed": انقل `aria_log.00000001` و`aria_log_control` من `C:\xampp\mysql\data` إلى مجلد نسخة، ثم من داخل `data` نفّذ `..\bin\aria_chk.exe --zerofill mysql\*.MAI`.
   - "Failed to initialize multi master structures": ملفات تكرار قديمة معطوبة — انقل ملفات `master-*`, `mysql-relay-bin-*`, `multi-master.info` من `data` إلى مجلد نسخة (تم ذلك مرة؛ النسخة في `C:\xampp\mysql\replication-junk-backup-20261011`).
3. الأوامر الشائعة (اختبارات، Lighthouse، الترجمة…): جدول **Common commands** في [README](../README.md).

## 6. تحديث الموقع الحي ⚠️
- **تعديل في الكود فقط** (ملفات `nafha-core` أو `nafha-child`): ارفع الملفات المعدّلة فقط عبر FileZilla إلى نفس المسار داخل `htdocs`. هذه الطريقة الآمنة.
- **إعادة النشر الكاملة** (`tools/deploy/build.sh https://nafha.great-site.net <إيميل>` ثم استيراد SQL ورفع الملفات — [DEPLOY-FREE.md](DEPLOY-FREE.md)) **تمسح** من الموقع الحي: الطلبات، مفاتيح Turnstile، إعدادات FluentSMTP، وتغيّر كلمة مرور المدير. بعدها يجب إعادة: إدخال مفاتيح Turnstile، تثبيت FluentSMTP وربطه بـ Brevo (مفتاح API جديد)، والدخول بالبيانات الجديدة من `dist/deploy-credentials.txt`.
- تعديل المنتجات والصفحات والإعدادات: مباشرة من لوحة تحكم الموقع الحي ([USER-GUIDE.md](USER-GUIDE.md)).

## 7. الأدلة
| الملف | محتواه |
|---|---|
| [USER-GUIDE.md](USER-GUIDE.md) | إدارة المتجر: المنتجات، الطلبات، العروض، الشحن، الإعدادات |
| [DEPLOY-FREE.md](DEPLOY-FREE.md) | النشر المجاني خطوة بخطوة |
| [DEPLOY.md](DEPLOY.md) | النشر على استضافة مدفوعة ودومين خاص (للمستقبل) |
| [TRACKING.md](TRACKING.md) | GTM / GA4 / ميتا / تيك توك / Search Console / خلاصة المنتجات |
| [PLUGINS.md](PLUGINS.md) | الإضافات ولماذا كل واحدة |
| [IMPORT-GUIDE.md](IMPORT-GUIDE.md) | استيراد المنتجات بالجملة (CSV) |
| [HANDOVER.md](HANDOVER.md) | معايير القبول ونتائج الاختبار |
| [PLAN.md](PLAN.md) | خطة المراحل وقرارات المتجر (المناطق، الأسعار، العروض) |

---

## 8. ما بقي (مؤجّل باختيارك)

### أ) Google Analytics 4 + Google Tag Manager — معرفة زوار المشروع
الموقع يرسل الأحداث جاهزة (زيارة منتج، إضافة للسلة، بدء الدفع، شراء، نقرة واتساب، الاختبار، صندوق التجربة). المطلوب:
1. **GA4:** analytics.google.com ← Start measuring ← Account `Nafha` ← Property `نفحة` (Qatar، US Dollar) ← Web ← `nafha.great-site.net` ← انسخ **Measurement ID** (`G-…`).
2. **GTM:** tagmanager.google.com ← Create Account ← `Nafha` / Qatar ← Container `nafha.great-site.net` / Web ← انسخ **`GTM-…`**.
3. في لوحة الموقع: **ووكومرس ← الإعدادات ← نفحة ← معرّف حاوية Google Tag Manager** ← الصق `GTM-…` ← حفظ.
4. داخل GTM: وسم **Google tag** بمعرّف `G-…` على All Pages، ووسوم **GA4 Event** للأحداث مع *Send Ecommerce data → Data Layer* — التفاصيل في [TRACKING.md](TRACKING.md) القسم 3. (أو اطلب من Claude إنشاء ملف JSON جاهز للاستيراد في GTM: Admin ← Import Container.)
5. **Submit ← Publish** في GTM، ثم تحقق من **Realtime** في GA4.

### ب) Google Search Console — الظهور في جوجل
1. search.google.com/search-console ← Add property ← **URL prefix** ← `https://nafha.great-site.net/`.
2. طريقة **HTML tag** ← انسخ الكود ← في الموقع: **Rank Math SEO ← General Settings ← Webmaster Tools ← Google Search Console** ← الصق ← حفظ ← **Verify**.
3. **Sitemaps** ← `sitemap_index.xml` ← Submit. ثم فحص الرابط الرئيسي ← **Request indexing**.
ملاحظة: حماية InfinityFree تحجب الزيارات غير المتصفحة؛ إن فشل التحقق صوّر الخطأ.

### ج) اختياري لاحقًا
- **Meta / TikTok Pixel** عبر GTM ([TRACKING.md](TRACKING.md)).
- **دومين خاص واستضافة مدفوعة** إن تحوّل لمتجر حقيقي: [DEPLOY.md](DEPLOY.md) و"المطلوب قبل الإطلاق" في [HANDOVER.md](HANDOVER.md).
- **التحقق من رقم الجوال في Brevo** إن ظهر تنبيه يمنع الإرسال.

## 9. قيود معروفة
- الاستضافة المجانية تحجب الزيارات غير المتصفحة (curl، بعض روبوتات المعاينة)؛ قد لا تظهر صورة معاينة للرابط عند مشاركته على واتساب/لينكدإن.
- عند ضغط زيارات عالٍ قد توقف InfinityFree الحساب مؤقتًا (حد العمليات المتزامنة).
- إيميلات الموقع تُرسل من عنوان Gmail عبر Brevo؛ قد تصل أول رسالة إلى Spam.
- Firefox لم يُختبر آليًا (سياسة ويندوز على جهاز التطوير)؛ Safari مختبر بمحرك WebKit.
