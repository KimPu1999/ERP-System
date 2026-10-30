/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Locale = 'en' | 'my-MM';

const STORAGE_KEY = 'erp_cloud.locale';
const localeEvent = 'erp-cloud-locale-change';

const optionalEnglish = {
  'optional.status.available': 'Available',
  'optional.status.disabled': 'Disabled',
  'optional.status.setup_required': 'Setup required',
  'optional.status.unavailable': 'Unavailable',
  'optional.apiFailure': 'API connection failed. Retry to check again.',
  'optional.setupTitle': 'Setup required',
  'optional.setupDetail': 'Required module configuration is incomplete.',
  'optional.unavailableTitle': 'Module unavailable',
  'optional.accessDetail': 'This module is not currently available for your access scope.',
  'optional.posTitle': 'Point of Sale',
  'optional.posDescription': 'Registers and cashier operations backed by verified inventory and payment services.',
  'optional.projectsTitle': 'Project Management',
  'optional.projectsDescription': 'Projects and delivery work using the organization and employee records you already manage.',
  'optional.assetsTitle': 'Fixed Assets',
  'optional.assetsDescription': 'Asset register and depreciation records with accounting-controlled source transactions.',
  'accounting.fiscalYears': 'Fiscal years',
  'accounting.fiscalYearDetail': 'Define company financial years before generating monthly periods.',
  'accounting.fiscalYearName': 'Fiscal year name',
  'accounting.startsOn': 'Start date',
  'accounting.endsOn': 'End date',
  'accounting.createFiscalYear': 'Create fiscal year',
  'accounting.saving': 'Saving…',
  'accounting.fiscalYearError': 'Unable to create fiscal year. Check the dates and company access.',
  'accounting.noFiscalYears': 'No fiscal years configured',
  'accounting.createYearDetail': 'Create a year, then generate its periods.',
  'accounting.generatePeriods': 'Generate periods',
  'accounting.generating': 'Generating…',
  'accounting.periodsGenerated': 'Periods generated',
  'accounting.close': 'Close',
  'accounting.reopen': 'Reopen',
  'accounting.lock': 'Lock',
  'accounting.parentAccount': 'Parent account',
  'accounting.rootAccount': 'Root account',
  'accounting.createAccount': 'Create account',
  'accounting.searchAccounts': 'Search accounts',
  'accounting.typeFilter': 'Account type',
  'accounting.allTypes': 'All types',
  'accounting.allStatuses': 'All statuses',
  'accounting.accountError': 'Unable to save the account. Check the code, parent and company access.',
  'accounting.activate': 'Activate',
  'accounting.deactivate': 'Deactivate',
  'accounting.asset': 'Assets',
  'accounting.liability': 'Liabilities',
  'accounting.equity': 'Equity',
  'accounting.revenue': 'Revenue',
  'accounting.expense': 'Expenses',
  'nav.journals': 'Journal entries',
  'nav.financialReports': 'Financial reports',
  'nav.accountingIntegration': 'Accounting integration',
} as const;

const optionalBurmese = {
  'optional.status.available': 'အသုံးပြုနိုင်သည်',
  'optional.status.disabled': 'ပိတ်ထားသည်',
  'optional.status.setup_required': 'စတင်သတ်မှတ်ရန် လိုအပ်သည်',
  'optional.status.unavailable': 'မရရှိနိုင်',
  'optional.apiFailure': 'API ချိတ်ဆက်မှု မအောင်မြင်ပါ။ ထပ်မံကြိုးစားပါ။',
  'optional.setupTitle': 'စတင်သတ်မှတ်ရန် လိုအပ်သည်',
  'optional.setupDetail': 'Module အတွက် လိုအပ်သော ဆက်တင် မပြည့်စုံသေးပါ။',
  'optional.unavailableTitle': 'Module မရရှိနိုင်ပါ',
  'optional.accessDetail': 'သင့်အသုံးပြုခွင့်အတိုင်းအတာအတွက် ဤ module မရရှိနိုင်သေးပါ။',
  'optional.posTitle': 'အရောင်းကောင်တာ',
  'optional.posDescription': 'Inventory နှင့် ငွေပေးချေမှုဝန်ဆောင်မှုများနှင့် ချိတ်ဆက်ထားသော register နှင့် cashier လုပ်ငန်းများ။',
  'optional.projectsTitle': 'Project စီမံခန့်ခွဲမှု',
  'optional.projectsDescription': 'ရှိပြီးသား organization နှင့် employee မှတ်တမ်းများကို အသုံးပြုသည့် project လုပ်ငန်းများ။',
  'optional.assetsTitle': 'Fixed Assets',
  'optional.assetsDescription': 'Accounting ထိန်းချုပ်ထားသော ဝယ်ယူမှုနှင့် တန်ဖိုးလျော့မှတ်တမ်းများ။',
  'accounting.fiscalYears': 'ဘဏ္ဍာရေးနှစ်များ',
  'accounting.fiscalYearDetail': 'လစဉ်ကာလများ မဖန်တီးမီ ကုမ္ပဏီ၏ ဘဏ္ဍာရေးနှစ်ကို သတ်မှတ်ပါ။',
  'accounting.fiscalYearName': 'ဘဏ္ဍာရေးနှစ် အမည်',
  'accounting.startsOn': 'စတင်ရက်',
  'accounting.endsOn': 'ပြီးဆုံးရက်',
  'accounting.createFiscalYear': 'ဘဏ္ဍာရေးနှစ် ဖန်တီးရန်',
  'accounting.saving': 'သိမ်းဆည်းနေသည်…',
  'accounting.fiscalYearError': 'ဘဏ္ဍာရေးနှစ် ဖန်တီး၍ မရပါ။ ရက်စွဲများနှင့် ကုမ္ပဏီအသုံးပြုခွင့်ကို စစ်ဆေးပါ။',
  'accounting.noFiscalYears': 'ဘဏ္ဍာရေးနှစ် မသတ်မှတ်ရသေး',
  'accounting.createYearDetail': 'နှစ်တစ်နှစ်ဖန်တီးပြီးနောက် ကာလများကို ဖန်တီးပါ။',
  'accounting.generatePeriods': 'ကာလများ ဖန်တီးရန်',
  'accounting.generating': 'ဖန်တီးနေသည်…',
  'accounting.periodsGenerated': 'ကာလများ ဖန်တီးပြီး',
  'accounting.close': 'ပိတ်ရန်',
  'accounting.reopen': 'ပြန်ဖွင့်ရန်',
  'accounting.lock': 'လော့ခ်ချရန်',
  'accounting.parentAccount': 'မိခင်စာရင်း',
  'accounting.rootAccount': 'အမြစ်စာရင်း',
  'accounting.createAccount': 'စာရင်း ဖန်တီးရန်',
  'accounting.searchAccounts': 'စာရင်းများ ရှာရန်',
  'accounting.typeFilter': 'စာရင်းအမျိုးအစား',
  'accounting.allTypes': 'အမျိုးအစားအားလုံး',
  'accounting.allStatuses': 'အခြေအနေအားလုံး',
  'accounting.accountError': 'စာရင်း သိမ်း၍ မရပါ။ ကုဒ်၊ မိခင်စာရင်းနှင့် ကုမ္ပဏီအသုံးပြုခွင့်ကို စစ်ဆေးပါ။',
  'accounting.activate': 'အသက်သွင်းရန်',
  'accounting.deactivate': 'ပိတ်ရန်',
  'accounting.asset': 'ပိုင်ဆိုင်မှုများ',
  'accounting.liability': 'ပေးရန်တာဝန်များ',
  'accounting.equity': 'ပိုင်ရှင်အစုရှယ်ယာတန်ဖိုး',
  'accounting.revenue': 'ဝင်ငွေ',
  'accounting.expense': 'အသုံးစရိတ်များ',
  'nav.journals': 'စာရင်းသွင်းမှတ်တမ်းများ',
  'nav.financialReports': 'ဘဏ္ဍာရေးအစီရင်ခံစာများ',
  'nav.accountingIntegration': 'Accounting ချိတ်ဆက်မှု',
} as const;

const messages = {
  en: {
    ...optionalEnglish,
    'app.workspace': 'Workspace',
    'app.platform': 'Platform operations',
    'app.apps': 'Apps',
    'app.search': 'Search anything…',
    'app.account': 'Account',
    'app.signOut': 'Sign out',
    'app.openNavigation': 'Open navigation',
    'app.closeNavigation': 'Close navigation',
    'app.expandNavigation': 'Expand navigation',
    'app.collapseNavigation': 'Collapse navigation',
    'app.selectCompany': 'Select company',
    'app.selectBranch': 'Select branch',
    'app.allCompanies': 'All companies',
    'app.allBranches': 'All branches',
    'app.notifications': 'Notifications',
    'app.changeLanguage': 'Change language',
    'app.toggleTheme': 'Toggle dark mode',
    'nav.workspace': 'Workspace', 'nav.crmSales': 'CRM & Sales', 'nav.purchasing': 'Purchasing', 'nav.products': 'Products', 'nav.inventory': 'Inventory', 'nav.accounting': 'Accounting', 'nav.hrPayroll': 'HR & Payroll', 'nav.optional': 'POS · Projects · Assets', 'nav.approvalsReports': 'Approvals & Reports', 'nav.settings': 'Settings',
    'nav.dashboard': 'Dashboard', 'nav.organization': 'Organization', 'nav.companyBranches': 'Company & branches', 'nav.salesWorkspace': 'Sales workspace', 'nav.purchaseOrders': 'Purchase orders', 'nav.suppliers': 'Suppliers', 'nav.productCatalog': 'Product catalog', 'nav.productCategories': 'Product categories', 'nav.inventoryControl': 'Inventory control', 'nav.hr': 'HR & payroll', 'nav.optionalModules': 'Optional modules', 'nav.approvals': 'Approvals', 'nav.reports': 'Reports', 'nav.accessSettings': 'Access & settings',
    'nav.platform': 'Platform', 'nav.overview': 'Overview', 'nav.tenantManagement': 'Tenant Management', 'nav.monitoring': 'Monitoring · System Health', 'nav.subscriptions': 'Subscriptions & Billing',
    'auth.secureAccess': 'Secure workspace access', 'auth.footer': 'Secure enterprise operations', 'auth.welcome': 'Welcome back', 'auth.signInTitle': 'Sign in to ERP Cloud', 'auth.signInDescription': 'Manage your business operations from one secure workspace.', 'auth.email': 'Email address', 'auth.password': 'Password', 'auth.emailPlaceholder': 'you@company.com', 'auth.passwordPlaceholder': 'Enter your password', 'auth.remember': 'Remember me', 'auth.forgot': 'Forgot password?', 'auth.signIn': 'Sign in', 'auth.signingIn': 'Signing in…', 'auth.newTo': 'New to ERP Cloud?', 'auth.createAccount': 'Create an account', 'auth.createAccountEyebrow': 'Create account', 'auth.register': 'Register', 'auth.name': 'Name', 'auth.confirmPassword': 'Confirm password', 'auth.creatingAccount': 'Creating account…', 'auth.showPassword': 'Show password', 'auth.hidePassword': 'Hide password', 'auth.invalidCredentials': 'The email or password is incorrect.', 'auth.unableSignIn': 'Unable to sign in.', 'auth.unableRegister': 'Unable to register.', 'auth.enterEmail': 'Enter your email address.', 'auth.enterPassword': 'Enter your password.', 'auth.enterName': 'Enter your name.', 'auth.validEmail': 'Enter a valid email address.', 'auth.passwordLength': 'Password must be at least 8 characters.', 'auth.confirmPasswordError': 'Confirm your password.', 'auth.passwordMismatch': 'Passwords do not match.',
    'common.english': 'English', 'common.burmese': 'မြန်မာ', 'common.refresh': 'Refresh', 'common.retry': 'Retry', 'common.loading': 'Loading…', 'common.selectCompany': 'Select an authorized company', 'common.noData': 'No data available', 'common.active': 'Active', 'common.inactive': 'Inactive', 'common.ready': 'Ready', 'common.blocked': 'Blocked', 'common.notConfigured': 'Not configured', 'common.previous': 'Previous', 'common.next': 'Next', 'common.page': 'Page', 'common.of': 'of', 'common.rows': 'Rows', 'common.required': 'Required', 'common.cancel': 'Cancel', 'common.save': 'Save', 'common.edit': 'Edit', 'common.delete': 'Delete', 'common.search': 'Search',
    'dashboard.goodMorning': 'Good morning', 'dashboard.goodAfternoon': 'Good afternoon', 'dashboard.goodEvening': 'Good evening', 'dashboard.live': 'Live', 'dashboard.accessChecking': 'Checking your workspace access…', 'dashboard.accessError': 'Unable to verify your workspace access.', 'dashboard.loadCompaniesError': 'Unable to load companies for this workspace.', 'dashboard.loadDataError': 'Unable to load dashboard data.', 'dashboard.retry': 'Retry', 'dashboard.workspaceSetup': 'Workspace setup', 'dashboard.createFirstCompany': 'Create your first company', 'dashboard.noActiveCompany': 'No active company is available', 'dashboard.openCompanySetup': 'Open Company Setup', 'dashboard.selectDates': 'Select both start and end dates to load dashboard data.', 'dashboard.dataLive': 'Dashboard data is loaded from authorized live records', 'dashboard.filters': 'Dashboard filters', 'dashboard.workspace': 'Workspace', 'dashboard.company': 'Company', 'dashboard.branch': 'Branch', 'dashboard.dateRange': 'Date range', 'dashboard.today': 'Today', 'dashboard.last7': 'Last 7 days', 'dashboard.last30': 'Last 30 days', 'dashboard.thisMonth': 'This month', 'dashboard.thisYear': 'This year', 'dashboard.customRange': 'Custom range', 'dashboard.startDate': 'Start date', 'dashboard.endDate': 'End date', 'dashboard.refresh': 'Refresh dashboard', 'dashboard.refreshing': 'Refreshing dashboard', 'dashboard.noBranches': 'No branches configured', 'dashboard.businessSnapshot': 'Business snapshot', 'dashboard.analyticsOverview': 'Analytics Overview', 'dashboard.businessPerformance': 'Business performance', 'dashboard.operationsMonitoring': 'Operations monitoring', 'dashboard.currencyRequired': 'Base accounting currency is required for financial totals.', 'dashboard.revenue': 'Revenue', 'dashboard.orders': 'Orders', 'dashboard.invoices': 'Invoices', 'dashboard.deliveries': 'Deliveries', 'dashboard.inventoryValue': 'Inventory value', 'dashboard.lowStock': 'Low stock',
    'inventory.title': 'Inventory', 'inventory.description': 'Stock balances across authorized warehouses.', 'inventory.search': 'Search SKU or product', 'inventory.company': 'Company', 'inventory.branch': 'Branch', 'inventory.warehouse': 'Warehouse', 'inventory.status': 'Status', 'inventory.sort': 'Sort', 'inventory.allStock': 'All stock', 'inventory.available': 'Available', 'inventory.lowStock': 'Low stock', 'inventory.outOfStock': 'Out of stock', 'inventory.recentlyUpdated': 'Recently updated', 'inventory.lowestOnHand': 'Lowest on hand', 'inventory.highestOnHand': 'Highest on hand', 'inventory.highestReserved': 'Highest reserved', 'inventory.clear': 'Clear', 'inventory.error': 'Unable to load inventory data.', 'inventory.noCompany': 'Inventory balances are scoped to your company access.', 'inventory.activeSkus': 'Active SKUs', 'inventory.availableStock': 'Available stock', 'inventory.lowStockAlerts': 'Low stock alerts', 'inventory.reservedStock': 'Reserved stock', 'inventory.inventoryValue': 'Inventory value', 'inventory.costingUnavailable': 'Costing unavailable', 'inventory.stockBalances': 'Stock balances', 'inventory.balanceRows': 'balance rows', 'inventory.noBalances': 'No stock balances found', 'inventory.postOperation': 'Post a goods receipt or authorized stock operation to create balances.', 'inventory.product': 'Product', 'inventory.sku': 'SKU', 'inventory.warehouseLocation': 'Warehouse / location', 'inventory.onHand': 'On hand', 'inventory.reserved': 'Reserved', 'inventory.reorderLevel': 'Reorder level', 'inventory.unknownProduct': 'Unknown product',
    'accounting.title': 'Accounting', 'accounting.description': 'Financial setup and posted-ledger readiness.', 'accounting.selectCompany': 'Select company', 'accounting.error': 'Unable to load accounting data.', 'accounting.currencyRequired': 'Base accounting currency is required before financial totals can be displayed.', 'accounting.configureCurrency': 'Configure currency', 'accounting.baseCurrency': 'Base currency', 'accounting.chartOfAccounts': 'Chart of accounts', 'accounting.fiscalPeriods': 'Fiscal periods', 'accounting.postingReadiness': 'Posting readiness', 'accounting.readyTotals': 'Ready for financial totals', 'accounting.requiredProfile': 'Required in company profile', 'accounting.accountsAvailable': 'Accounts available', 'accounting.noAccounts': 'No accounts configured', 'accounting.openPeriods': 'open', 'accounting.coreSetup': 'Core setup is present', 'accounting.completeSetup': 'Complete required setup', 'accounting.accounts': 'accounts', 'accounting.openSetup': 'Open setup', 'accounting.loadingSetup': 'Loading accounting setup…', 'accounting.noAccountsDetail': 'Posting remains blocked until an authorized administrator configures the chart of accounts.', 'accounting.periodsConfigured': 'configured', 'accounting.noPeriods': 'No fiscal periods configured', 'accounting.periodDetail': 'Posting requires an open period covering the entry date.', 'accounting.code': 'Code', 'accounting.name': 'Name', 'accounting.type': 'Type', 'accounting.normalBalance': 'Normal balance', 'accounting.status': 'Status', 'accounting.starts': 'starts', 'accounting.ends': 'ends',
    'org.overview': 'Overview', 'org.profile': 'Company Profile', 'org.branches': 'Branches', 'org.departments': 'Departments', 'org.structure': 'Organization Structure', 'org.members': 'Staff & Memberships', 'org.roles': 'Roles & Permissions', 'org.back': 'Back', 'org.selectedCompany': 'Selected company', 'purchase.overview': 'Overview', 'purchase.onboarding': 'Supplier Onboarding', 'purchase.financials': 'Supplier Financials', 'purchase.catalogs': 'Supplier Catalogs', 'purchase.performance': 'Supplier Performance', 'purchase.intelligence': 'Supplier Intelligence', 'purchase.reports': 'Supplier Reports', 'purchase.automations': 'Supplier Automations', 'purchase.notifications': 'Supplier Notifications', 'purchase.portalActivity': 'Portal Activity', 'purchase.accessReviews': 'Access Reviews', 'purchase.requests': 'Purchase Requests', 'purchase.orders': 'Purchase Orders', 'purchase.approvals': 'Purchase Approvals', 'purchase.receipts': 'Goods Receipts', 'purchase.bills': 'Supplier Bills', 'purchase.payables': 'Accounts Payable', 'purchase.payments': 'Payments', 'purchase.credits': 'Supplier Credits', 'purchase.returns': 'Purchase Returns', 'purchase.settings': 'Settings', 'sales.overview': 'Overview', 'sales.customers': 'Customers', 'sales.leads': 'Leads', 'sales.opportunities': 'Opportunities', 'sales.pipeline': 'Pipeline', 'sales.activities': 'Activities', 'sales.quotations': 'Quotations', 'sales.orders': 'Sales Orders', 'sales.deliveries': 'Deliveries', 'sales.invoices': 'Invoices', 'sales.payments': 'Payments', 'sales.returns': 'Returns & Refunds', 'sales.reports': 'Reports', 'sales.settings': 'Sales Settings',
    'optional.title': 'POS, Projects & Fixed Assets', 'optional.subtitle': 'Independent workspaces using authorized ERP records.', 'optional.error': 'Unable to load optional module data.', 'optional.unavailable': 'Unavailable', 'optional.enableToOpen': 'Enable this module to open its workspace.', 'optional.open': 'Open module', 'optional.registers': 'registers', 'optional.projects': 'projects', 'optional.assets': 'assets', 'optional.activeRegisters': 'active registers', 'optional.activeProjects': 'active projects', 'optional.capitalizedAssets': 'capitalized assets', 'optional.noConfiguration': 'No optional module configuration is available.', 'optional.back': 'Back to modules', 'optional.overview': 'Overview', 'optional.tasks': 'Tasks', 'optional.depreciation': 'Depreciation', 'optional.salesHistory': 'Sales history', 'optional.reports': 'Reports', 'optional.disabledTitle': 'Module is not enabled', 'optional.disabledDetail': 'Ask an authorized administrator to enable this module for the tenant.', 'optional.companyScope': 'Selected company scope', 'optional.authorizedScope': 'All authorized companies', 'optional.records': 'Records', 'optional.status': 'Active records', 'optional.apiStatus': 'Data connection', 'optional.connected': 'Connected', 'optional.realRecords': 'Live API records', 'optional.registeredRecords': 'Registered records', 'optional.noRecords': 'No records found', 'optional.noRecordsDetail': 'There are no records for the selected company scope.', 'optional.scopeNote': 'Only tenant and company-authorized records are shown. Financial posting remains controlled by the existing accounting workflows.', 'optional.code': 'Code', 'optional.name': 'Name', 'optional.paymentMethods': 'Payment methods', 'optional.budget': 'Budget', 'optional.dates': 'Dates', 'optional.assetNumber': 'Asset number', 'optional.acquisitionCost': 'Acquisition cost', 'optional.acquiredOn': 'Acquired on',
    'pos.title': 'Point of Sale', 'pos.subtitle': 'Checkout and cashier operations using live inventory records.', 'pos.overview': 'Overview', 'pos.checkout': 'Checkout', 'pos.registers': 'Registers', 'pos.shifts': 'Cashier shifts', 'pos.history': 'Sales history', 'pos.company': 'Company', 'pos.branch': 'Branch', 'pos.allCompanies': 'All authorized companies', 'pos.allBranches': 'All authorized branches', 'pos.refresh': 'Refresh POS', 'pos.todaySales': 'Today sales', 'pos.todayReceipts': 'Today receipts', 'pos.openShifts': 'Open shifts', 'pos.currencyUnavailable': 'Currency display is unavailable until the company base currency is configured.', 'pos.selectRegister': 'Select register', 'pos.selectShift': 'Select open shift', 'pos.searchProducts': 'Search product, SKU or barcode', 'pos.noProducts': 'No eligible products found', 'pos.available': 'Available', 'pos.add': 'Add', 'pos.cart': 'Cart', 'pos.emptyCart': 'Add products to start a sale.', 'pos.subtotal': 'Subtotal', 'pos.tax': 'Tax', 'pos.total': 'Total', 'pos.cashOnly': 'Cash payment', 'pos.cashExact': 'Cash amount must equal the total', 'pos.completeSale': 'Complete sale', 'pos.salePosted': 'Sale posted', 'pos.openShift': 'Open shift', 'pos.openingCash': 'Opening cash', 'pos.closeShift': 'Close shift', 'pos.closingCash': 'Closing cash', 'pos.noRegisters': 'No POS registers are configured for this company scope.', 'pos.noShifts': 'No cashier shifts found.', 'pos.noReceipts': 'No posted receipts found.', 'pos.register': 'Register', 'pos.state': 'State', 'pos.receipt': 'Receipt', 'pos.date': 'Date', 'pos.amount': 'Amount', 'pos.payment': 'Payment', 'pos.stockRequired': 'A register with a stock location and an open shift is required before checkout.', 'pos.setupTitle': 'POS setup required', 'pos.setupDetail': 'Create an active register with a stock location before processing sales.', 'pos.cashier': 'Cashier', 'pos.registerCode': 'Code', 'pos.paymentMethod': 'Payment method', 'pos.noCurrency': 'No base currency',
    'language.label': 'Language', 'language.englishShort': 'EN', 'language.burmeseShort': 'မြန်မာ',
  },
  'my-MM': {
    ...optionalBurmese,
    'app.workspace': 'လုပ်ငန်းခွင်', 'app.platform': 'ပလက်ဖောင်း လုပ်ငန်းများ', 'app.apps': 'အက်ပ်များ', 'app.search': 'ရှာဖွေရန်…', 'app.account': 'အကောင့်', 'app.signOut': 'အကောင့်မှ ထွက်ရန်', 'app.openNavigation': 'လမ်းညွှန်မီနူး ဖွင့်ရန်', 'app.closeNavigation': 'လမ်းညွှန်မီနူး ပိတ်ရန်', 'app.expandNavigation': 'လမ်းညွှန်မီနူး ချဲ့ရန်', 'app.collapseNavigation': 'လမ်းညွှန်မီနူး ခေါက်ရန်', 'app.selectCompany': 'ကုမ္ပဏီ ရွေးချယ်ရန်', 'app.selectBranch': 'ဌာနခွဲ ရွေးချယ်ရန်', 'app.allCompanies': 'ကုမ္ပဏီအားလုံး', 'app.allBranches': 'ဌာနခွဲအားလုံး', 'app.notifications': 'အသိပေးချက်များ', 'app.changeLanguage': 'ဘာသာစကား ပြောင်းရန်', 'app.toggleTheme': 'အမှောင်ပုံစံ ပြောင်းရန်',
    'nav.workspace': 'လုပ်ငန်းခွင်', 'nav.crmSales': 'ဖောက်သည်နှင့် အရောင်း', 'nav.purchasing': 'ဝယ်ယူရေး', 'nav.products': 'ကုန်ပစ္စည်းများ', 'nav.inventory': 'ကုန်လက်ကျန်', 'nav.accounting': 'စာရင်းကိုင်', 'nav.hrPayroll': 'ဝန်ထမ်းနှင့် လစာ', 'nav.optional': 'POS · စီမံကိန်း · ပိုင်ဆိုင်မှု', 'nav.approvalsReports': 'အတည်ပြုချက်နှင့် အစီရင်ခံစာ', 'nav.settings': 'ဆက်တင်များ', 'nav.dashboard': 'ပင်မစာမျက်နှာ', 'nav.organization': 'အဖွဲ့အစည်း', 'nav.companyBranches': 'ကုမ္ပဏီနှင့် ဌာနခွဲများ', 'nav.salesWorkspace': 'အရောင်းလုပ်ငန်းခွင်', 'nav.purchaseOrders': 'ဝယ်ယူမှုအော်ဒါများ', 'nav.suppliers': 'ကုန်ပစ္စည်းပေးသွင်းသူများ', 'nav.productCatalog': 'ကုန်ပစ္စည်းစာရင်း', 'nav.productCategories': 'ကုန်ပစ္စည်းအမျိုးအစားများ', 'nav.inventoryControl': 'ကုန်လက်ကျန် ထိန်းချုပ်မှု', 'nav.hr': 'ဝန်ထမ်းနှင့် လစာ', 'nav.optionalModules': 'ရွေးချယ်နိုင်သော မော်ဂျူးများ', 'nav.approvals': 'အတည်ပြုချက်များ', 'nav.reports': 'အစီရင်ခံစာများ', 'nav.accessSettings': 'အသုံးပြုခွင့်နှင့် ဆက်တင်များ', 'nav.platform': 'ပလက်ဖောင်း', 'nav.overview': 'အနှစ်ချုပ်', 'nav.tenantManagement': 'လုပ်ငန်းခွင် စီမံခန့်ခွဲမှု', 'nav.monitoring': 'စောင့်ကြည့်မှု · စနစ်အခြေအနေ', 'nav.subscriptions': 'စာရင်းသွင်းမှုနှင့် ငွေပေးချေမှု',
    'auth.secureAccess': 'လုံခြုံသော လုပ်ငန်းခွင်ဝင်ရောက်မှု', 'auth.footer': 'လုံခြုံသော လုပ်ငန်းလုပ်ဆောင်မှု', 'auth.welcome': 'ပြန်လည်ကြိုဆိုပါတယ်', 'auth.signInTitle': 'ERP Cloud သို့ ဝင်ရောက်ရန်', 'auth.signInDescription': 'လုံခြုံသော လုပ်ငန်းခွင်တစ်ခုမှ သင့်လုပ်ငန်းကို စီမံပါ။', 'auth.email': 'အီးမေးလ်လိပ်စာ', 'auth.password': 'စကားဝှက်', 'auth.emailPlaceholder': 'you@company.com', 'auth.passwordPlaceholder': 'စကားဝှက် ရိုက်ထည့်ပါ', 'auth.remember': 'မှတ်ထားရန်', 'auth.forgot': 'စကားဝှက် မေ့နေပါသလား?', 'auth.signIn': 'ဝင်ရောက်ရန်', 'auth.signingIn': 'ဝင်ရောက်နေသည်…', 'auth.newTo': 'ERP Cloud အသစ်အသုံးပြုသူလား?', 'auth.createAccount': 'အကောင့်ဖန်တီးရန်', 'auth.createAccountEyebrow': 'အကောင့်ဖန်တီးရန်', 'auth.register': 'စာရင်းသွင်းရန်', 'auth.name': 'အမည်', 'auth.confirmPassword': 'စကားဝှက် အတည်ပြုရန်', 'auth.creatingAccount': 'အကောင့်ဖန်တီးနေသည်…', 'auth.showPassword': 'စကားဝှက် ပြရန်', 'auth.hidePassword': 'စကားဝှက် ဖျောက်ရန်', 'auth.invalidCredentials': 'အီးမေးလ် သို့မဟုတ် စကားဝှက် မှားယွင်းနေပါသည်။', 'auth.unableSignIn': 'ဝင်ရောက်၍ မရပါ။', 'auth.unableRegister': 'စာရင်းသွင်း၍ မရပါ။', 'auth.enterEmail': 'အီးမေးလ်လိပ်စာ ထည့်ပါ။', 'auth.enterPassword': 'စကားဝှက် ထည့်ပါ။', 'auth.enterName': 'အမည် ထည့်ပါ။', 'auth.validEmail': 'မှန်ကန်သော အီးမေးလ်လိပ်စာ ထည့်ပါ။', 'auth.passwordLength': 'စကားဝှက်သည် အနည်းဆုံး အက္ခရာ ၈ လုံး ရှိရမည်။', 'auth.confirmPasswordError': 'စကားဝှက်ကို အတည်ပြုပါ။', 'auth.passwordMismatch': 'စကားဝှက်များ မကိုက်ညီပါ။',
    'common.english': 'English', 'common.burmese': 'မြန်မာ', 'common.refresh': 'ပြန်လည်ရယူရန်', 'common.retry': 'ထပ်မံကြိုးစားရန်', 'common.loading': 'ဖွင့်နေသည်…', 'common.selectCompany': 'ခွင့်ပြုထားသော ကုမ္ပဏီကို ရွေးချယ်ပါ', 'common.noData': 'ဒေတာ မရှိပါ', 'common.active': 'အသုံးပြုနေ', 'common.inactive': 'အသုံးမပြု', 'common.ready': 'အသင့်', 'common.blocked': 'ပိတ်ထားသည်', 'common.notConfigured': 'မသတ်မှတ်ရသေး', 'common.previous': 'ယခင်', 'common.next': 'နောက်', 'common.page': 'စာမျက်နှာ', 'common.of': '/', 'common.rows': 'တန်းများ', 'common.required': 'လိုအပ်သည်', 'common.cancel': 'ပယ်ဖျက်ရန်', 'common.save': 'သိမ်းဆည်းရန်', 'common.edit': 'ပြင်ဆင်ရန်', 'common.delete': 'ဖျက်ရန်', 'common.search': 'ရှာဖွေရန်',
    'dashboard.goodMorning': 'မင်္ဂလာနံနက်ခင်းပါ', 'dashboard.goodAfternoon': 'မင်္ဂလာနေ့လယ်ခင်းပါ', 'dashboard.goodEvening': 'မင်္ဂလာညနေခင်းပါ', 'dashboard.live': 'လက်ရှိ', 'dashboard.accessChecking': 'လုပ်ငန်းခွင် အသုံးပြုခွင့် စစ်ဆေးနေသည်…', 'dashboard.accessError': 'လုပ်ငန်းခွင် အသုံးပြုခွင့် စစ်ဆေး၍ မရပါ။', 'dashboard.loadCompaniesError': 'ဤလုပ်ငန်းခွင်အတွက် ကုမ္ပဏီများ ဖွင့်၍ မရပါ။', 'dashboard.loadDataError': 'ပင်မစာမျက်နှာ ဒေတာ ဖွင့်၍ မရပါ။', 'dashboard.retry': 'ထပ်မံကြိုးစားရန်', 'dashboard.workspaceSetup': 'လုပ်ငန်းခွင် စတင်ပြင်ဆင်မှု', 'dashboard.createFirstCompany': 'ပထမဆုံး ကုမ္ပဏီ ဖန်တီးပါ', 'dashboard.noActiveCompany': 'အသုံးပြုနိုင်သော ကုမ္ပဏီ မရှိပါ', 'dashboard.openCompanySetup': 'ကုမ္ပဏီ ဆက်တင် ဖွင့်ရန်', 'dashboard.selectDates': 'ဒေတာဖွင့်ရန် စတင်ရက်နှင့် ပြီးဆုံးရက် နှစ်ခုလုံး ရွေးချယ်ပါ။', 'dashboard.dataLive': 'ခွင့်ပြုထားသော လက်ရှိမှတ်တမ်းများမှ ပင်မစာမျက်နှာဒေတာကို ရယူထားသည်', 'dashboard.filters': 'ပင်မစာမျက်နှာ စစ်ထုတ်မှုများ', 'dashboard.workspace': 'လုပ်ငန်းခွင်', 'dashboard.company': 'ကုမ္ပဏီ', 'dashboard.branch': 'ဌာနခွဲ', 'dashboard.dateRange': 'ရက်စွဲအပိုင်းအခြား', 'dashboard.today': 'ယနေ့', 'dashboard.last7': 'နောက်ဆုံး ၇ ရက်', 'dashboard.last30': 'နောက်ဆုံး ရက် ၃၀', 'dashboard.thisMonth': 'ဤလ', 'dashboard.thisYear': 'ဤနှစ်', 'dashboard.customRange': 'စိတ်ကြိုက်အပိုင်းအခြား', 'dashboard.startDate': 'စတင်ရက်', 'dashboard.endDate': 'ပြီးဆုံးရက်', 'dashboard.refresh': 'ပင်မစာမျက်နှာ ပြန်လည်ရယူရန်', 'dashboard.refreshing': 'ပင်မစာမျက်နှာ ပြန်လည်ရယူနေသည်', 'dashboard.noBranches': 'ဌာနခွဲ မသတ်မှတ်ရသေး', 'dashboard.businessSnapshot': 'လုပ်ငန်းအနှစ်ချုပ်', 'dashboard.analyticsOverview': 'ခွဲခြမ်းစိတ်ဖြာမှု အနှစ်ချုပ်', 'dashboard.businessPerformance': 'လုပ်ငန်းဆောင်ရွက်မှု', 'dashboard.operationsMonitoring': 'လုပ်ငန်းလည်ပတ်မှု စောင့်ကြည့်ခြင်း', 'dashboard.currencyRequired': 'ဘဏ္ဍာရေးစုစုပေါင်းများအတွက် အခြေခံစာရင်းကိုင်ငွေကြေး လိုအပ်သည်။', 'dashboard.revenue': 'ဝင်ငွေ', 'dashboard.orders': 'အော်ဒါများ', 'dashboard.invoices': 'ပြေစာများ', 'dashboard.deliveries': 'ပို့ဆောင်မှုများ', 'dashboard.inventoryValue': 'ကုန်လက်ကျန်တန်ဖိုး', 'dashboard.lowStock': 'ကုန်လက်ကျန်နည်း',
    'inventory.title': 'ကုန်လက်ကျန်', 'inventory.description': 'ခွင့်ပြုထားသော ဂိုဒေါင်များအတွင်း ကုန်လက်ကျန်များ။', 'inventory.search': 'SKU သို့မဟုတ် ကုန်ပစ္စည်း ရှာရန်', 'inventory.company': 'ကုမ္ပဏီ', 'inventory.branch': 'ဌာနခွဲ', 'inventory.warehouse': 'ဂိုဒေါင်', 'inventory.status': 'အခြေအနေ', 'inventory.sort': 'စီရန်', 'inventory.allStock': 'ကုန်လက်ကျန်အားလုံး', 'inventory.available': 'ရရှိနိုင်', 'inventory.lowStock': 'ကုန်လက်ကျန်နည်း', 'inventory.outOfStock': 'ကုန်ပြတ်', 'inventory.recentlyUpdated': 'မကြာသေးမီက ပြင်ဆင်ထား', 'inventory.lowestOnHand': 'လက်ကျန် အနည်းဆုံး', 'inventory.highestOnHand': 'လက်ကျန် အများဆုံး', 'inventory.highestReserved': 'ကြိုတင်ဖယ်ထားမှု အများဆုံး', 'inventory.clear': 'ရှင်းလင်းရန်', 'inventory.error': 'ကုန်လက်ကျန်ဒေတာ ဖွင့်၍ မရပါ။', 'inventory.noCompany': 'ကုန်လက်ကျန်များကို သင့်ကုမ္ပဏီအသုံးပြုခွင့်အတိုင်း ပြသထားသည်။', 'inventory.activeSkus': 'အသုံးပြုနေသော SKU များ', 'inventory.availableStock': 'ရရှိနိုင်သော ကုန်လက်ကျန်', 'inventory.lowStockAlerts': 'ကုန်လက်ကျန်နည်း သတိပေးချက်များ', 'inventory.reservedStock': 'ကြိုတင်ဖယ်ထားသော ကုန်လက်ကျန်', 'inventory.inventoryValue': 'ကုန်လက်ကျန်တန်ဖိုး', 'inventory.costingUnavailable': 'ကုန်ကျစရိတ် မရရှိနိုင်', 'inventory.stockBalances': 'ကုန်လက်ကျန်စာရင်း', 'inventory.balanceRows': 'စာရင်းတန်းများ', 'inventory.noBalances': 'ကုန်လက်ကျန် မတွေ့ပါ', 'inventory.postOperation': 'စာရင်းဖန်တီးရန် ကုန်လက်ခံစာ သို့မဟုတ် ခွင့်ပြုထားသော ကုန်လက်ကျန်လုပ်ဆောင်ချက်ကို မှတ်တမ်းတင်ပါ။', 'inventory.product': 'ကုန်ပစ္စည်း', 'inventory.sku': 'SKU', 'inventory.warehouseLocation': 'ဂိုဒေါင် / တည်နေရာ', 'inventory.onHand': 'လက်ရှိလက်ကျန်', 'inventory.reserved': 'ကြိုတင်ဖယ်ထား', 'inventory.reorderLevel': 'ပြန်မှာယူအဆင့်', 'inventory.unknownProduct': 'မသိသော ကုန်ပစ္စည်း',
    'accounting.title': 'စာရင်းကိုင်', 'accounting.description': 'ဘဏ္ဍာရေးဆက်တင်နှင့် စာရင်းမှတ်တမ်း အသင့်ဖြစ်မှု။', 'accounting.selectCompany': 'ကုမ္ပဏီ ရွေးချယ်ရန်', 'accounting.error': 'စာရင်းကိုင်ဒေတာ ဖွင့်၍ မရပါ။', 'accounting.currencyRequired': 'ဘဏ္ဍာရေးစုစုပေါင်းများ ပြသရန် အခြေခံစာရင်းကိုင်ငွေကြေး လိုအပ်သည်။', 'accounting.configureCurrency': 'ငွေကြေး သတ်မှတ်ရန်', 'accounting.baseCurrency': 'အခြေခံငွေကြေး', 'accounting.chartOfAccounts': 'စာရင်းခေါင်းစဉ်များ', 'accounting.fiscalPeriods': 'ဘဏ္ဍာရေးကာလများ', 'accounting.postingReadiness': 'စာရင်းသွင်းရန် အသင့်ဖြစ်မှု', 'accounting.readyTotals': 'ဘဏ္ဍာရေးစုစုပေါင်းများအတွက် အသင့်', 'accounting.requiredProfile': 'ကုမ္ပဏီပရိုဖိုင်တွင် သတ်မှတ်ရန်လို', 'accounting.accountsAvailable': 'စာရင်းများ ရရှိနိုင်', 'accounting.noAccounts': 'စာရင်းခေါင်းစဉ် မသတ်မှတ်ရသေး', 'accounting.openPeriods': 'ဖွင့်ထား', 'accounting.coreSetup': 'အဓိကဆက်တင် ပြည့်စုံ', 'accounting.completeSetup': 'လိုအပ်သော ဆက်တင်များ ဖြည့်စွက်ပါ', 'accounting.accounts': 'စာရင်းများ', 'accounting.openSetup': 'ဆက်တင်ဖွင့်ရန်', 'accounting.loadingSetup': 'စာရင်းကိုင်ဆက်တင် ဖွင့်နေသည်…', 'accounting.noAccountsDetail': 'ခွင့်ပြုထားသော စီမံခန့်ခွဲသူက စာရင်းခေါင်းစဉ်များ မသတ်မှတ်မချင်း စာရင်းသွင်းခြင်းကို ပိတ်ထားမည်။', 'accounting.periodsConfigured': 'သတ်မှတ်ထား', 'accounting.noPeriods': 'ဘဏ္ဍာရေးကာလ မသတ်မှတ်ရသေး', 'accounting.periodDetail': 'စာရင်းသွင်းရန် ရက်စွဲနှင့် ကိုက်ညီသော ဖွင့်ထားသည့်ကာလ လိုအပ်သည်။', 'accounting.code': 'ကုဒ်', 'accounting.name': 'အမည်', 'accounting.type': 'အမျိုးအစား', 'accounting.normalBalance': 'ပုံမှန်လက်ကျန်', 'accounting.status': 'အခြေအနေ', 'accounting.starts': 'စတင်', 'accounting.ends': 'ပြီးဆုံး',
    'org.overview': 'အနှစ်ချုပ်', 'org.profile': 'ကုမ္ပဏီပရိုဖိုင်', 'org.branches': 'ဌာနခွဲများ', 'org.departments': 'ဌာနများ', 'org.structure': 'အဖွဲ့အစည်း ဖွဲ့စည်းပုံ', 'org.members': 'ဝန်ထမ်းနှင့် အဖွဲ့ဝင်များ', 'org.roles': 'တာဝန်နှင့် အသုံးပြုခွင့်များ', 'org.back': 'နောက်သို့', 'org.selectedCompany': 'ရွေးချယ်ထားသော ကုမ္ပဏီ', 'purchase.overview': 'အနှစ်ချုပ်', 'purchase.onboarding': 'ပေးသွင်းသူ စတင်မှတ်ပုံတင်မှု', 'purchase.financials': 'ပေးသွင်းသူ ဘဏ္ဍာရေး', 'purchase.catalogs': 'ပေးသွင်းသူ ကတ်တလောက်များ', 'purchase.performance': 'ပေးသွင်းသူ ဆောင်ရွက်မှု', 'purchase.intelligence': 'ပေးသွင်းသူ ခွဲခြမ်းစိတ်ဖြာမှု', 'purchase.reports': 'ပေးသွင်းသူ အစီရင်ခံစာများ', 'purchase.automations': 'ပေးသွင်းသူ အလိုအလျောက်လုပ်ဆောင်မှု', 'purchase.notifications': 'ပေးသွင်းသူ အသိပေးချက်များ', 'purchase.portalActivity': 'ပေါ်တယ် လုပ်ဆောင်ချက်', 'purchase.accessReviews': 'အသုံးပြုခွင့် စစ်ဆေးမှုများ', 'purchase.requests': 'ဝယ်ယူမှု တောင်းဆိုချက်များ', 'purchase.orders': 'ဝယ်ယူမှုအော်ဒါများ', 'purchase.approvals': 'ဝယ်ယူမှု အတည်ပြုချက်များ', 'purchase.receipts': 'ကုန်လက်ခံစာများ', 'purchase.bills': 'ပေးသွင်းသူ ပြေစာများ', 'purchase.payables': 'ပေးရန်ရှိသောစာရင်း', 'purchase.payments': 'ငွေပေးချေမှုများ', 'purchase.credits': 'ပေးသွင်းသူ ခရက်ဒစ်များ', 'purchase.returns': 'ဝယ်ယူမှု ပြန်လည်အပ်နှံမှုများ', 'purchase.settings': 'ဆက်တင်များ', 'sales.overview': 'အနှစ်ချုပ်', 'sales.customers': 'ဖောက်သည်များ', 'sales.leads': 'အလားအလာဖောက်သည်များ', 'sales.opportunities': 'အရောင်းအခွင့်အလမ်းများ', 'sales.pipeline': 'အရောင်းလမ်းကြောင်း', 'sales.activities': 'အရောင်းလုပ်ဆောင်ချက်များ', 'sales.quotations': 'ဈေးနှုန်းကမ်းလှမ်းချက်များ', 'sales.orders': 'အရောင်းအော်ဒါများ', 'sales.deliveries': 'ပို့ဆောင်မှုများ', 'sales.invoices': 'အရောင်းပြေစာများ', 'sales.payments': 'ငွေပေးချေမှုများ', 'sales.returns': 'ပြန်အပ်နှံမှုနှင့် ပြန်အမ်းငွေ', 'sales.reports': 'အစီရင်ခံစာများ', 'sales.settings': 'အရောင်း ဆက်တင်များ',
    'optional.title': 'POS၊ Project နှင့် Fixed Assets', 'optional.subtitle': 'ERP မှ ခွင့်ပြုထားသော မှတ်တမ်းများကို အသုံးပြုသည့် သီးခြားလုပ်ငန်းခွင်များ။', 'optional.error': 'Optional module ဒေတာ ဖွင့်၍ မရပါ။', 'optional.unavailable': 'မရရှိနိုင်', 'optional.enableToOpen': 'ဤ module ကို ဖွင့်ပြီးမှ လုပ်ငန်းခွင်ကို ဖွင့်နိုင်ပါမည်။', 'optional.open': 'Module ဖွင့်ရန်', 'optional.registers': 'register များ', 'optional.projects': 'project များ', 'optional.assets': 'asset များ', 'optional.activeRegisters': 'ဖွင့်ထားသော register များ', 'optional.activeProjects': 'ဖွင့်ထားသော project များ', 'optional.capitalizedAssets': 'စာရင်းတင်ထားသော asset များ', 'optional.noConfiguration': 'Optional module ဆက်တင် မရရှိသေးပါ။', 'optional.back': 'Module များသို့ ပြန်ရန်', 'optional.overview': 'အနှစ်ချုပ်', 'optional.tasks': 'လုပ်ငန်းတာဝန်များ', 'optional.depreciation': 'တန်ဖိုးလျော့', 'optional.salesHistory': 'အရောင်းမှတ်တမ်း', 'optional.reports': 'အစီရင်ခံစာများ', 'optional.disabledTitle': 'Module ကို ဖွင့်မထားပါ', 'optional.disabledDetail': 'Tenant အတွက် ဤ module ကို ဖွင့်ပေးရန် ခွင့်ပြုထားသော စီမံခန့်ခွဲသူထံ တောင်းဆိုပါ။', 'optional.companyScope': 'ရွေးချယ်ထားသော ကုမ္ပဏီအတိုင်းအတာ', 'optional.authorizedScope': 'ခွင့်ပြုထားသော ကုမ္ပဏီအားလုံး', 'optional.records': 'မှတ်တမ်းများ', 'optional.status': 'Active မှတ်တမ်းများ', 'optional.apiStatus': 'ဒေတာချိတ်ဆက်မှု', 'optional.connected': 'ချိတ်ဆက်ထားသည်', 'optional.realRecords': 'Live API မှတ်တမ်းများ', 'optional.registeredRecords': 'မှတ်ပုံတင်ထားသော မှတ်တမ်းများ', 'optional.noRecords': 'မှတ်တမ်း မတွေ့ပါ', 'optional.noRecordsDetail': 'ရွေးချယ်ထားသော ကုမ္ပဏီအတိုင်းအတာအတွက် မှတ်တမ်းမရှိပါ။', 'optional.scopeNote': 'Tenant နှင့် ကုမ္ပဏီခွင့်ပြုချက်ရှိသော မှတ်တမ်းများကိုသာ ပြသထားပါသည်။ ဘဏ္ဍာရေးစာရင်းသွင်းမှုများကို လက်ရှိ Accounting workflow ကသာ ထိန်းချုပ်ပါသည်။', 'optional.code': 'ကုဒ်', 'optional.name': 'အမည်', 'optional.paymentMethods': 'ငွေပေးချေမှုနည်းလမ်းများ', 'optional.budget': 'ဘတ်ဂျက်', 'optional.dates': 'ရက်စွဲများ', 'optional.assetNumber': 'Asset နံပါတ်', 'optional.acquisitionCost': 'ဝယ်ယူကုန်ကျစရိတ်', 'optional.acquiredOn': 'ဝယ်ယူသည့်ရက်',
    'pos.title': 'အရောင်းကောင်တာ', 'pos.subtitle': 'လက်ရှိ inventory မှတ်တမ်းများနှင့် ချိတ်ဆက်ထားသော checkout နှင့် cashier လုပ်ငန်းများ။', 'pos.overview': 'အနှစ်ချုပ်', 'pos.checkout': 'Checkout', 'pos.registers': 'Register များ', 'pos.shifts': 'Cashier shifts', 'pos.history': 'အရောင်းမှတ်တမ်း', 'pos.company': 'ကုမ္ပဏီ', 'pos.branch': 'ဌာနခွဲ', 'pos.allCompanies': 'ခွင့်ပြုထားသော ကုမ္ပဏီအားလုံး', 'pos.allBranches': 'ခွင့်ပြုထားသော ဌာနခွဲအားလုံး', 'pos.refresh': 'POS ပြန်လည်ရယူရန်', 'pos.todaySales': 'ယနေ့အရောင်း', 'pos.todayReceipts': 'ယနေ့ပြေစာများ', 'pos.openShifts': 'ဖွင့်ထားသော shift များ', 'pos.currencyUnavailable': 'ကုမ္ပဏီ base currency မသတ်မှတ်မချင်း ငွေကြေးပြသ၍ မရပါ။', 'pos.selectRegister': 'Register ရွေးရန်', 'pos.selectShift': 'ဖွင့်ထားသော shift ရွေးရန်', 'pos.searchProducts': 'ကုန်ပစ္စည်း၊ SKU သို့မဟုတ် barcode ရှာရန်', 'pos.noProducts': 'ရောင်းချနိုင်သော ကုန်ပစ္စည်း မတွေ့ပါ', 'pos.available': 'ရရှိနိုင်', 'pos.add': 'ထည့်ရန်', 'pos.cart': 'Cart', 'pos.emptyCart': 'အရောင်းစတင်ရန် ကုန်ပစ္စည်းများ ထည့်ပါ။', 'pos.subtotal': 'အရင်းစုစုပေါင်း', 'pos.tax': 'အခွန်', 'pos.total': 'စုစုပေါင်း', 'pos.cashOnly': 'ငွေသားပေးချေမှု', 'pos.cashExact': 'ငွေသားပမာဏသည် စုစုပေါင်းနှင့် တူရမည်', 'pos.completeSale': 'အရောင်းပြီးစီးရန်', 'pos.salePosted': 'အရောင်းမှတ်တမ်း တင်ပြီးပါပြီ', 'pos.openShift': 'Shift ဖွင့်ရန်', 'pos.openingCash': 'စတင်ငွေသား', 'pos.closeShift': 'Shift ပိတ်ရန်', 'pos.closingCash': 'ပိတ်သိမ်းငွေသား', 'pos.noRegisters': 'ဤကုမ္ပဏီအတိုင်းအတာအတွက် POS register မသတ်မှတ်ရသေးပါ။', 'pos.noShifts': 'Cashier shift မတွေ့ပါ။', 'pos.noReceipts': 'တင်ပြီးသော ပြေစာမတွေ့ပါ။', 'pos.register': 'Register', 'pos.state': 'အခြေအနေ', 'pos.receipt': 'ပြေစာ', 'pos.date': 'ရက်စွဲ', 'pos.amount': 'ပမာဏ', 'pos.payment': 'ငွေပေးချေမှု', 'pos.stockRequired': 'Checkout မလုပ်မီ stock location ပါသော register နှင့် ဖွင့်ထားသော shift လိုအပ်သည်။', 'pos.setupTitle': 'POS စတင်သတ်မှတ်ရန် လိုအပ်သည်', 'pos.setupDetail': 'အရောင်းမလုပ်မီ active register နှင့် stock location တစ်ခု ဖန်တီးပါ။', 'pos.cashier': 'Cashier', 'pos.registerCode': 'ကုဒ်', 'pos.paymentMethod': 'ငွေပေးချေနည်း', 'pos.noCurrency': 'Base currency မရှိပါ',
    'language.label': 'ဘာသာစကား', 'language.englishShort': 'EN', 'language.burmeseShort': 'မြန်မာ',
  },
} as const;

export type TranslationKey = keyof typeof messages.en;

const readStoredLocale = (): Locale => {
  if (typeof window === 'undefined') return 'en';
  const stored = window.localStorage.getItem(STORAGE_KEY);
  return stored === 'my-MM' ? 'my-MM' : 'en';
};

export const getLocale = (): Locale => readStoredLocale();

export const setLocale = (locale: Locale): void => {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(STORAGE_KEY, locale);
  document.documentElement.lang = locale;
  window.dispatchEvent(new CustomEvent(localeEvent, { detail: locale }));
};

export function translate(key: TranslationKey | string, locale: Locale = getLocale(), fallback?: string): string {
  const value = messages[locale][key as TranslationKey] ?? messages.en[key as TranslationKey];
  if (!value) {
    if (import.meta.env.DEV) console.warn(`[i18n] Missing translation: ${key}`);
    return fallback ?? key;
  }
  return value;
}

export const formatNumber = (value: number | string | null | undefined, locale: Locale = getLocale(), options?: Intl.NumberFormatOptions): string => {
  if (value === null || value === undefined || value === '') return '—';
  return new Intl.NumberFormat(locale === 'my-MM' ? 'my-MM' : 'en-US', options).format(Number(value));
};

export const formatCurrency = (value: number | string | null | undefined, currency: string | null | undefined, locale: Locale = getLocale(), fractionDigits = 2): string => {
  if (value === null || value === undefined || value === '') return '—';
  if (!currency) return formatNumber(value, locale, { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits });
  return new Intl.NumberFormat(locale === 'my-MM' ? 'my-MM' : 'en-US', { style: 'currency', currency, minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits }).format(Number(value));
};

export const formatDate = (value: string | Date | null | undefined, locale: Locale = getLocale(), options?: Intl.DateTimeFormatOptions): string => {
  if (!value) return '—';
  return new Intl.DateTimeFormat(locale === 'my-MM' ? 'my-MM' : 'en-US', options).format(new Date(value));
};

type I18nContextValue = { locale: Locale; setLocale: (locale: Locale) => void; t: (key: TranslationKey | string, fallback?: string) => string; formatNumber: (value: number | string | null | undefined, options?: Intl.NumberFormatOptions) => string; formatCurrency: (value: number | string | null | undefined, currency: string | null | undefined, fractionDigits?: number) => string; formatDate: (value: string | Date | null | undefined, options?: Intl.DateTimeFormatOptions) => string };
const I18nContext = createContext<I18nContextValue | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(readStoredLocale);
  useEffect(() => {
    document.documentElement.lang = locale;
    const handleChange = (event: Event) => setLocaleState((event as CustomEvent<Locale>).detail);
    window.addEventListener(localeEvent, handleChange);
    return () => window.removeEventListener(localeEvent, handleChange);
  }, [locale]);
  const value = useMemo<I18nContextValue>(() => ({ locale, setLocale: (next) => { setLocale(next); setLocaleState(next); }, t: (key, fallback) => translate(key, locale, fallback), formatNumber: (value, options) => formatNumber(value, locale, options), formatCurrency: (value, currency, fractionDigits) => formatCurrency(value, currency, locale, fractionDigits), formatDate: (value, options) => formatDate(value, locale, options) }), [locale]);
  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nContextValue {
  const value = useContext(I18nContext);
  if (!value) throw new Error('useI18n must be used inside I18nProvider');
  return value;
}
