# Business Administrator Guide (Burmese / မြန်မာ)

**ရည်ရွယ်သူ:** Enterprise ERP Cloud workspace ကိုသုံးမည့် Business (Tenant) Administrator များ။
**အခြေခံအချက်အလက်:** အောက်တွင်ဖော်ပြထားသော screen တိုင်းသည် frontend route စာရင်းတွင် တကယ်ရှိသည်။ သင့် tenant အတွက် လုပ်ပိုင်ခွင့်မဖွင့်ထားသော module ကို ဖွင့်ရန် ညွှန်းကိန်းအဖြစ် မရေးထားပါ။
**ဗားရှင်း:** ၂၀၂၆-၁၁-၀၄ (Phase 15)။

## ၁။ Account နှင့် ဝင်ရောက်ခြင်း

- လုပ်ငန်းနေ့စဉ်- `/login` တွင် login လုပ်ပါ (business login; `/platform/login` မသုံးပါနှင့်)။
- Admin ဖိတ်ကြားချက်လက်ခံ- email link → `/accept-invitation` → preview (login မလုပ်ရသေးမီ ကြည့်နိုင်သည်) → accept → password/profile ဖြည့်။
- စနစ်ထည့်သွင်း ပြီးမြောက်အောင်- `/business/getting-started`။ အဆင့်တစ်ခုမြီီးမြင့်မှုသည် api က confirm ပြုမှသာ ပြီးမြောက်သည်။
- Workspace ပင်မ- `/business/dashboard`။

## ၂။ အဖွဲ့အစည်း — `/organization`

| Screen | ရည်ရွယ်ချက် |
|---|---|
| `/organization/structure` | Company + branch အဆင့်ဆင့် |
| `/organization/branches` | Branch ဖန်တီး/ပြင်၊ branch manager ရွေး |
| `/organization/departments` | အစိတ်အပိုင်း ဖန်တီး |
| `/organization/roles` | Role သတ်မှတ် (company/branch/department scope) |
| `/organization/members` | ဝန်ထမ်း ဖိတ်ကြား/စီမံ |

**နောက်ဆုံး admin ကာကွယ်ခြင်း-** active admin တစ်ဦးတည်းသာရှိချိန်တွင် demote/revoke ပြုလုပ်ခွင့်မပေးပါ — ရှင်းလင်းချက်နှင့်အတူပိတ်သည်။

**ဝန်ထမ်းဖိတ်မီ အနည်းဆုံးခွင့်ပြုချက်မူဝါဒကိုလိုက်နာပါ။** scope မှားနေသော staff သည် Access Denied စာမျက်နှာသာမြင်ရမည်။

## ၃။ Catalog

`/catalog`၊ `/catalog/categories`၊ `/catalog/references`။

၁။ ပထမ reference data ဖန်တီးပါ- အမျိုးအစား နှင့် ယူနစ်။ prerequisite မပါပဲ product ဖန်တီးရန် ကြိုးစားပါက ညွှန်ကြားချက်နှင့်အတူ ငြင်းပယ်သည်။
၂။ `/catalog` တွင် product ဖန်တီးပါ။ အရေအတွက် နှင့် ဈေးနှုန်း သည် တိကျသော decimal ဖြင့်သိမ်းသည်။

## ၄။ ဝယ်ယူမှုနှင့် ထောက်ပံ့သူ

| အဆင့် | Screen |
|---|---|
| Supplier ဖန်တီး (+contact+address) | `/suppliers` |
| Purchase order ဖန်တီး | purchase order screen |
| PO submit → approval | `/approvals/inbox` |
| Goods receipt | goods receipts screen (`/purchases/receipts` form) |
| Supplier bill ဖန်တီး/submit | `/accounts-payable`၊ supplier-bills screen |
| 3-way match → approve → post | supplier bill detail screen |

Supplier bill post ပြုလုပ်ပါက balance journal ယဉ်ငင် အလိုအလျောက်ဖြစ်ပေါ်သည်။ ထောက်ပံ့သူ balance သည် posted bill − payment ဖြစ်သည်။

## ၅။ စတော်ရှော့

`/inventory` — တည်ဆောက်ရန် `/inventory/warehouses`၊ လက်ငင်းလုပ်ဆောင်မှု `/inventory/operations`။

- Warehouse + location ဖန်တီးခြင်း
- Stock လက်ခံ: goods receipt မှ သို့မဟုတ် `/inventory/operations` တွင် adjustment
- Stock transfer: အရင်းအမြစ်/ပန်းတိုင် location နှင့် အရေအတွက်သတ်မှတ်; ledger တွင် −/+ movement အမှန်တကန်မှတ်တမ်းတင်သည်
- **စာရင်းရွေးချယ်လေ့လာခြင်း:** ledger စုစည်းမှုသည် item+location လက်ရှိ balance နှင့်တစ်သံတည်းဖြစ်ရမည်။ မဟုတ်ပါက transacting ရပ်ပြီး support ticket တင်ပါ။
- တချိန်တည်းပြင်ဆင်မှုကိစ္စတွင် single balance row ဖြင့် serialize ဖြစ်သည် — retry စာတန်းပေါ်နိုင်သည်။

## ၆။ ရောင်းချမှု — `/crm-sales`

| အခြေအနေ | Screen |
|---|---|
| ဖောက်သည် | customers screen (+ detail) |
| Quotation lifecycle | quotations screen → submit → send → approve → convert to order |
| Sales order lifecycle | orders screen → confirm (stock စောင့်ဆိုင်း) → pick → pack → ship |
| Invoice | invoices screen (+ detail), print |
| Payment | record → verify → allocate across invoices. Duplicate ကို server-side ငြင်းပယ် |
| Return | returns screen → receive; stock မှတ်တမ်းပြန်ရောက်ရမည် |

_invoice total သည် လက်ဖြင့်တွက်သည့် သင်္ချာနှင့်တစ်သံတည်းဖြစ်ရမည်။ မမှန်ပါက P1 defect တင်ပါ — ဒါက product ၏ အဓိက integrity signal ဖြစ်သည်။_

## ၇။ စာရင်း — `/accounting`

၁။ **အစပြုခြင်း:** base currency နှင့် fiscal year ဦးစွာသတ်မှတ်။
၂။ **Chart of accounts:** account ဖန်တီး။
၃။ **Manual journal:** draft → submit → approve → **post**။ Post ရန် debit == credit ရှိရမည်; period lock မဖြစ်ရမည်။ **မှန်ကန်စေရန်-** posted journal ကို reversal entry ဖြင့်သာ ပြင်ပါ၊ history တိုက်ရိုက်မပြင်ပါနှင့်။
၄။ **Report:** Trial balance၊ General Ledger၊ P&L၊ Balance Sheet။ period close/lock/reopen သည် `/accounting/periods`။
၅။ **စည်းမျဉ်း-** financial statement သည် posted journal ကိုသာ အခြေခံသည်။ Draft/approval အခြေအနေ ဖြစ်နေဆဲတွင် statement ထဲမပါဝင်ပါ။

## ၈။ Team၊ Role နှင့် ခွင့်ပြုချက်

`/business/team` — staff ဖိတ်၊ role ရွေး၊ access history ကြည့်။ Revocation ချက်ချင်းအာနိသင်ရှိသည် (fail-closed)။ Suspend ဖြစ်နေပါက admin ပြန်ဖွင့်မှသာ ဆက်လုပ်နိုင်သည်။

## ၉။ Billing (သင်၏ subscription)

`/business/billing` — plan၊ usage၊ entitlement၊ invoice ကြည့်။ Entitlement ကန့်သတ်ချက်တွင် သေချုပ်ခလုတ်မဟုတ်ဘဲ plan limit စာတန်းပေါ်သည်။

## ၁၀။ Support

`/business/support` — ticket တင်၊ file attach၊ SLA status ကြည့်၊ reply ဖတ်။ Platform agent ၏ အတွင်း note သည် သင့်ဘက်မြင်မရပါ။

## ၁၁။ Optional modules (subscription မှာ ဖွင့်ထားမှသာ)

| Module | Screen |
|---|---|
| POS | `/optional-modules/pos` |
| Fixed assets | `/optional-modules/assets` |
| Projects | `/optional-modules/projects` |
| HR | `/hr` |

## ၁၂။ ပြဿနာရှာဖွေ အမြန်စာရင်း

| လက္ခဏာ | ပထမစစ်ရန် | ဆက် |
|---|---|---|
| Login မဝင်နိုင် | surface မှန်လား (`/login` vs `/platform/login`) | password reset / admin ကို suspension စစ်ဖို့တောင်းဆို |
| Save button အလုပ်မလုပ် | လိုအပ်ချက် field အနီဖြင့် highlight? | တစ်ကြိမ် retry; မရပါက screenshot နှင့် ticket တင် |
| "Access Denied" | role scope မှန်လား | admin ကို `/business/team` တွင်ပြင်ခိုင်း |
| Ledger မတစ်သံတည်း | unposted journal / locked period? | `/accounting/reports` မှ ပြန်စစ် |
| Report export နောက်ကျ | export သည် queued ဖြစ်သည်; ခေတ္တစောင့် | backlog ဆက်ရှိပါက escalate |

## ၁၃။ Quick-start checklist

- [ ] ဖိတ်ကြားချက် accept; MFA (လိုအပ်ပါက) enroll
- [ ] `/business/getting-started` အဆင့်များ ပြီးမြောက်
- [ ] Company၊ branch၊ department၊ role၊ member တည်ဆောက်ပြီ
- [ ] Reference data + ပထမ product ပြီး
- [ ] ပထမ supplier + PO + GR + bill post ပြီး
- [ ] ပထမ ဖောက်သည် + quotation → order → invoice → payment စနစ်စည်းဝန်းပြီး
- [ ] Trial balance + P&L + Balance Sheet တစ်ကြိမ်ဖွင့်ပြီး
- [ ] staff ကို least-privilege role ဖြင့်ဖိတ်ပြီ; စမ်းသပ် revoke လည်း ပြီး
- [ ] `/business/support` တွင် support ticket တင်နိုင်ပြီ
