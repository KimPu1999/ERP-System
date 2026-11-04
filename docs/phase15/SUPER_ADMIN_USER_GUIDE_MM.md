# Platform Super Admin Guide (Burmese / မြန်မာ)

**ရည်ရွယ်သူ:** Enterprise ERP Cloud ပလက်ဖောင်း Super Administrator များ။
**အခြေခံအချက်အလက်:** အောက်တွင်ဖော်ပြထားသော စာမျက်နှာတိုင်းသည် တင်ပြီးသော frontend (`apps/web/src` route စာရင်း) တွင် တကယ်ရှိပြီး ၎င်း၏ backend API သည် `backend/routes/api.php` တွင် တကယ်တည်ရှိသည်။ မရှိသေးသော ခလုတ်များကို မရေးထားပါ။
**ဗားရှင်း:** ၂၀၂၆-၁၁-၀၄ (Phase 15)။

## ၁။ အသုံးပြုခွင့်ရယူခြင်း

၁။ အသုံးပြုသူ account ကို ပုံမှန် public page မှ စတင်ဖန်တီးရမည်။ `/platform/*` သို့ ဝင်ခွင့်ကို နောက်မှသာ ပေးသည်။
၂။ Platform operator တစ်ဦးက `php artisan platform:provision-admin {email} --yes` ကွပ်ကွဲကို လည်ပတ်ပေးရမည်။
   မဖြစ်မနေလိုအပ်ချက်များ- user ရှိပြီးဖြစ်ရမည်, `active`, suspend မဖြစ်ရ, **email verify ပြီးဖြစ်ရမည်**။ မဟုတ်ပါက command က ငြင်းပယ်သည်။
၃။ **Super-admin အများသုံးရန် sign-up မရှိပါ** — `/platform/login` ကို ရောက်သော်လည်း မဝင်ရောက်နိုင်ပါက သင့် `is_platform_super_admin` အလံမထားပါ။ provisioning တောင်းဆိုပါ။

## ၂။ Login နှင့် MFA

၁။ `/platform/login` သို့သွားပါ (business login `/login` နှင့် သီးခြားသည်)။
၂။ ဝင်ရောက်ပါ။ policy အရ MFA လိုအပ်ပါက MFA စနစ်ထည့်သွင်းမှုစာမျက်နှာသို့ ညွှန်းသည်- enroll → authenticator app တွင် TOTP secret ကို scan → ဂဏန်း ၆ လုံး code အတည်ပြု → **recovery codes များကို တစ်ကြိမ်သာကြည့်ရှုနိုင်သောကြောင့် ချက်ချင်းသိမ်းဆည်းပါ**။
၃။ Recovery code များသည် တစ်ကြိမ်သာ သုံးနိုင်သည်။ MFA ပြောင်းလဲခြင်းသည် step-up အတည်ပြုချက်လိုအပ်သည်။
၄။ စကားဝှန်မှောင်ပါက `/platform/forgot-password` → email link → `/platform/reset-password`။

## ၃။ Control Center — `/platform/control`

စီးပွားရေးစာရင်းအင်းများ၊ ခွင့်ပြုချက်စောင့်နေသည့်အရေအတွက်၊ subscription အချုပ်နှင့် စောင့်ကြည့်ခြင်း link များပြသည်။
**တကယ့်ဒေတာကိုသာပြသည်** — ချက်စီစဉ်ထားသော မလိမ်ဒေတာမရှိပါ။ စာရင်းအလွတ်ဖြစ်နေပါက ဒေတာစစ်စစ်မရှိသေးခြင်းသာဖြစ်သည်။

## ၄။ စီးပွားရေးလုပ်ငန်းစီမံခန့်ခွဲမှု

| လုပ်ငန်း | စာမျက်နှာ | လုပ်နိုင်သောအရာ |
|---|---|---|
| လုပ်ငန်းစာရင်းကြည့် | `/platform/businesses` | ရှာဖွေ၊ ခွဲခြား၊ လုပ်ငန်းအသေးစိတ်နှင့် activity trail ကြည့် |
| လျှောက်ထားမှု approve/reject | လုပ်ငန်းအသေးစိတ် | server-side validation၊ reason မှတ်တမ်း |
| Suspend / reactivate | လုပ်ငန်းအသေးစိတ် | suspend ဖြစ်နေစဉ် user များ fail-closed ဖြင့်ပိတ်သည် |
| Business admin | `/platform/business-admins` | ဖိတ်ကြား၊ စီမံ၊ revoke |
| Audit trail | `/platform/audit-logs` | လုပ်ဆောင်ချက်တိုင်း actor+context ဖြင့်မှတ်တမ်း |
| Report | `/platform/reports/financial`, `/platform/reports/exports` | export pipeline မှတစ်ဆင့် |

## ၅။ Billing

| လုပ်ငန်း | စာမျက်နှာ |
|---|---|
| Plan ဖန်တီး/ပြင်/archive | Billing စီမံခန့်ခွဲမှု entry point များ |
| Subscription (plan ထည့်, renew, cancel) | `/platform/payments` မှ |
| Invoice lifecycle | `/platform/invoices` |
| Payment verify | `/platform/payments` — တစ်ကြိမ်သာ verifyနိုင်ပြီး audit မှတ်တမ်းတင်သည် |

⚠️ Live processor အတွက် သီးခြားရေးမှတ်ခွင့်ပြုမှတ်မရှိမီ **sandbox ကိုသာ** သုံးရမည်။

## ၆။ Security Center — `/platform/security`

- MFA မူဝါဒ၊ session မူဝါဒ၊ step-up စည်းမျဉ်း
- Security events စာရင်း
- ဖြစ်ပျက်မှုပေါ်ပါက **session revoke** ဆောင်ရွက်နိုင်သည်
- `/platform/operations/maintenance` နှင့် ညီညွတ်စွာလုပ်ဆောင်ရန်

## ၇။ Monitoring — `/platform/monitoring`

Sub-view: api, database, queues, scheduler, errors, email, servers, incidents, alerts၊
ဆက်စပ်: `/platform/operations` (backups, costs, database, queues, maintenance)။
စည်းမျဉ်း- collector မဖွဲ့ထားပါက view အလွတ်ဖြစ်ပါက "ကောင်းမွန်" မစူးစမ်းပါနှင့်, "စုဆောင်းမထား" ဟုယူဆပါ။

## ၈။ Support

ဖောက်သည်များ `/business/support` တွင် ticket ဖွင့်သည်။ Platform agent များသည် စာမျက်နှာဘက်မှ ဖြေဆိုႏိုင်၊ close/reopen၊ attachment ကို ACL ဖြင့် download၊ အတွင်း note များလည်း ရေးနိုင်သည်။ ပြဿနာရှာရန် အချိန်ကန့်သတ် access grant (`/support/access-grants`) ကို လိုအပ်မှသာ approve လုပ်ပါ။

## ၉။ စိတ်ကူယဉ်မှုမရှိစေရန် လုပ်ဆောင်ခြင်း

၁။ backup အသစ်မရှိပါက၊ တစ်ကိုယ်တော်နှစ်ဦးနှင့်တည်း ဒေတာပြောင်းလဲမှု command မလုပ်ပါနှင့်။
၂။ RLS ခ်ုံထားသည့် ဒေတာပြောင်းလဲ migration သည် `SET app.platform_admin = 'true'` ကို အလုံအလောက်ထည့်ရမည်။
၃။ audit trail မှ ဖော်ပြပြီးမဖြစ်နိုင်သော ဖြေရှင်းချက်ကို လုပ်မမည် — incident record တွင် မှတ်တမ်းတင်ပြီးမှ ဆက်လုပ်ပါ။
၄။ Escalation- on-call Platform Admin → Security Owner (authentication/suspicion) → Deployment Operator (infrastructure)။ လက်ရှိ owner များ- `RESPONSIBILITY_MATRIX.md`။

## ၁၀။ Quick-start checklist

- [ ] `platform:provision-admin` ဖြင့် account ရထားပြီ (email verified, active)
- [ ] MFA ရွူမပီ၊ recovery code ကို လုံခြုံသိမ်းထားပြီ
- [ ] `/platform/control` → businesses → monitoring → audit-logs ကို ဖတ်ရှုလေ့လာပြီ
- [ ] Sandbox-only billing စည်းမျဉ်းနှင့် last-admin protection ကို မှတ်ထားပြီ
- [ ] `RESPONSIBILITY_MATRIX.md` ပြီး escalation contact သိထားပြီ

## ၁၁။ FAQ

**မေး- Login ဝင်ရောက်နိုင်သော်လည်း `/platform/*` page အားလုံး 403 ဖြစ်နေသည်။** အဖြေ- `is_platform_super_admin` အလံ မထားပါ။ provisioning တောင်းဆိုပါ၊ ကိုယ်တိုင်ပေး၍ မမည်။
**မေး- Verify email မရောက်။** အဖြေ- `/platform/monitoring/email` ကိုစစ်ပါ။ SMTP မတည်ငြိမ်ပါက app log သို့ ကူးပြသည်။ 
**မေး- RLS ကို "ဖြတ်သန်း"၍ ရမလား။** အဖြေ- မရပါ။ ခ်ုံထားသည့် စာရင်းများကို documented context ဖြင့်သာလုပ်ရမည်။ isolation ဖြတ်ခြင်းသည် security gate ကျရှုံးမှုဖြစ်သည်။
**မေး- စာရင်းအင်းပြနော်။** အဖြေ- ပလတ်ဖောင်းသည် ဒေတာစစ်စစ်ကိုသာပြသည်။ feature enabled ဖြစ်မှုနှင့် collector ဖွဲ့စည်းမှုကိုစစ်ပါ (အပိုင်း ၇ တွင်ကြည့်ပါ)။
