# التتبع والتسويق — دليل الإعداد

الموقع يرسل أحداث التجارة الإلكترونية جاهزة إلى **dataLayer** بصيغة GA4 القياسية. كل ما يلزم هو حاوية Google Tag Manager تُربط بها الأدوات (GA4، بكسل ميتا، بكسل تيك توك).

## 1. ربط الحاوية
لوحة التحكم ← ووكومرس ← الإعدادات ← تبويب **نفحة** ← **معرّف حاوية Google Tag Manager** (مثال `GTM-ABC1234`) ← حفظ.
يُضاف كود GTM تلقائيًا في كل الصفحات (`<head>` و`<noscript>`).

## 2. الأحداث المرسلة

| الحدث | متى | البيانات |
|---|---|---|
| `view_item` | فتح صفحة منتج | المنتج، السعر الأدنى |
| `add_to_cart` | أي إضافة للسلة (بطاقة المتجر، صفحة المنتج، صندوق التجربة) | المنتج، الحجم، السعر، الكمية، `event_id` |
| `begin_checkout` | فتح صفحة إتمام الطلب | محتوى السلة، الإجمالي |
| `purchase` | صفحة شكر الطلب (مرة واحدة لكل طلب حتى مع إعادة التحميل) | `transaction_id`، الإجمالي، الشحن، طريقة الدفع، المنتجات بالسعر المدفوع فعلًا |
| `whatsapp_click` | زر واتساب في صفحة المنتج، وزر "إرسال الطلب" بعد الطلب عبر واتساب | `whatsapp_location` (`product` / `order_confirmation`)، المنتج أو رقم الطلب |
| `quiz_complete` | ظهور نتائج اختبار "شو عطرك المناسب؟" | `quiz_results` |
| `discovery_box_add` | إضافة صندوق تجربة | `box_size`، `item_size` |
| `consent_update` | اختيار الزائر في شريط الكوكيز | `consent_choice` |

مثال `add_to_cart`:
```json
{
  "event": "add_to_cart",
  "event_id": "f561ba42-…",
  "ecommerce": {
    "currency": "USD", "value": 27,
    "items": [{ "item_id": "pdm-layton-10ml", "item_name": "لايتون", "item_brand": "Parfums de Marly",
                "item_category": "عطور رجالية", "item_variant": "10 مل", "price": 27, "quantity": 1 }]
  }
}
```

## 3. الإعداد داخل GTM

**المتغيرات (Data Layer Variables):** `ecommerce.value`، `ecommerce.currency`، `ecommerce.transaction_id`، `ecommerce.items`، `event_id`.

**المشغّلات (Custom Event):** `view_item`، `add_to_cart`، `begin_checkout`، `purchase`، `whatsapp_click`.

**الوسوم:**
- **GA4:** وسم *Google tag* بمعرّف القياس `G-XXXX` على All Pages. ثم وسم *GA4 Event* لكل حدث بنفس الاسم مع تفعيل **Send Ecommerce data → Data Layer**. علّم `purchase` و`whatsapp_click` كأحداث رئيسية (Key events) في GA4.
- **Meta Pixel:** قالب *Facebook Pixel* من معرض القوالب. ربط الأحداث: `view_item → ViewContent`، `add_to_cart → AddToCart`، `begin_checkout → InitiateCheckout`، `purchase → Purchase` (value + currency)، `whatsapp_click → Contact`. مرّر `event_id` كـ *Event ID* لمنع التكرار إن أُضيف لاحقًا Conversions API.
- **TikTok Pixel:** قالب *TikTok Pixel* بنفس الربط: `ViewContent`، `AddToCart`، `InitiateCheckout`، `CompletePayment`، `Contact`.

## 4. الموافقة على الكوكيز (Consent Mode v2)
- شريط الموافقة مفعّل افتراضيًا (يمكن إيقافه من نفس تبويب الإعدادات).
- قبل الموافقة تكون `analytics_storage` و`ad_storage` و`ad_user_data` و`ad_personalization` على **denied**؛ عند "موافق" تتحول إلى **granted** وتُحفظ لزيارات الزائر التالية.
- وسوم Google تحترم ذلك تلقائيًا. لوسوم ميتا وتيك توك فعّل في GTM إعداد **Consent Settings → Require additional consent: ad_storage** أو شغّلها على حدث `consent_update` بقيمة `granted`.
- عند إيقاف الشريط تكون الموافقة الافتراضية granted.

## 5. خلاصة المنتجات (Google Merchant Center / Meta Catalog)
الرابط: `https://<الدومين>/feed/nafha-products/`
- كل حجم = منتج مستقل، مجمّعة حسب العطر (`item_group_id`)، والرابط يفتح صفحة المنتج على الحجم نفسه.
- يتحدّث تلقائيًا (كاش ساعة واحدة، ويُمسح عند تعديل أي منتج).
- في Merchant Center: *Products → Feeds → Scheduled fetch* بهذا الرابط، الدولة قطر، اللغة العربية، العملة USD.
- في Meta Commerce Manager: *Catalog → Data sources → Data feed → Scheduled feed* بنفس الرابط.

## 6. Google Search Console
بعد النشر: أضف الموقع في Search Console (التحقق عبر DNS أو وسم HTML في Rank Math ← الإعدادات العامة ← أدوات مشرفي المواقع)، ثم أرسل خريطة الموقع: `https://<الدومين>/sitemap_index.xml`.
