import { useState } from 'react';
import { CheckCircle2, FileBarChart2, Printer, RefreshCw, XCircle } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { getDashboardCompanies, getFinancialStatements, type FinancialReportRow } from '../lib/api';

type ReportKind = 'trial_balance' | 'profit_loss' | 'balance_sheet';

const today = new Date().toISOString().slice(0, 10);
const yearStart = `${today.slice(0, 4)}-01-01`;
const money = (value?: string | null) => value == null ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const label = (kind: ReportKind) => kind === 'trial_balance' ? 'Trial balance' : kind === 'profit_loss' ? 'Profit & loss' : 'Balance sheet';
const titleCase = (value: string) => value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function Row({ row, amount = false }: { row: FinancialReportRow; amount?: boolean }) {
  return <tr><td><code>{row.code}</code></td><td>{row.name}<small>{row.subtype || titleCase(row.type)}</small></td>{amount ? <td className="report-number">{money(row.amount)}</td> : <><td className="report-number">{money(row.opening_debit)}</td><td className="report-number">{money(row.opening_credit)}</td><td className="report-number">{money(row.period_debit)}</td><td className="report-number">{money(row.period_credit)}</td><td className="report-number">{money(row.closing_debit)}</td><td className="report-number">{money(row.closing_credit)}</td></>}</tr>;
}

export function FinancialReportsPage() {
  const companies = useQuery({ queryKey: ['financial-report-companies'], queryFn: getDashboardCompanies });
  const companyRows = companies.data?.data.data ?? [];
  const [companyId, setCompanyId] = useState('');
  const [kind, setKind] = useState<ReportKind>('trial_balance');
  const [from, setFrom] = useState(yearStart);
  const [to, setTo] = useState(today);
  const selectedCompany = companyRows.find((company) => company.id === companyId);
  const report = useQuery({ queryKey: ['financial-statements', companyId, kind, from, to], queryFn: () => getFinancialStatements({ company_id: companyId, report: kind, from, to, as_of: to }), enabled: Boolean(companyId && from && to) });
  const result = report.data?.data;
  const reconciled = kind === 'profit_loss' ? result?.balanced_trial_balance : result?.balanced;

  return <section className="accounting-page financial-reports-page">
    <header className="accounting-page-header"><div><h1>Financial reports</h1><p>Posted base-currency ledger activity, reconciled by company and reporting period.</p></div><div className="accounting-actions"><select aria-label="Company" value={companyId} onChange={(event) => setCompanyId(event.target.value)}><option value="">Select company</option>{companyRows.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select><button className="accounting-row-action report-print" type="button" onClick={() => window.print()} disabled={!result}><Printer size={15} /> Print</button><button className="accounting-refresh" type="button" onClick={() => { void companies.refetch(); void report.refetch(); }} disabled={report.isFetching}><RefreshCw size={15} className={report.isFetching ? 'is-spinning' : ''} /> Refresh</button></div></header>
    <div className="report-toolbar"><label><span>Report</span><select value={kind} onChange={(event) => setKind(event.target.value as ReportKind)}><option value="trial_balance">Trial balance</option><option value="profit_loss">Profit & loss</option><option value="balance_sheet">Balance sheet</option></select></label>{kind === 'balance_sheet' ? <label><span>As of</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} /></label> : <><label><span>From</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} /></label><label><span>To</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} /></label></>}<span className="report-currency">{selectedCompany?.base_currency ? `Base currency: ${selectedCompany.base_currency}` : 'Base currency follows the posted ledger'}</span></div>
    {!companyId && <div className="accounting-state"><FileBarChart2 size={22} /><strong>Select an authorized company</strong><span>Choose a company to generate a financial statement.</span></div>}
    {companyId && report.isError && <div className="accounting-alert danger"><XCircle size={17} />Unable to load this report. Check report permission, dates and accounting setup.<button type="button" onClick={() => void report.refetch()}>Retry</button></div>}
    {companyId && report.isLoading && <div className="accounting-state"><RefreshCw size={20} className="is-spinning" /><strong>Generating {label(kind).toLowerCase()}…</strong></div>}
    {companyId && result && !report.isLoading && <>
      <div className="report-summary"><div><span>Report</span><strong>{label(kind)}</strong></div><div><span>Scope</span><strong>{kind === 'balance_sheet' ? `As of ${result.as_of}` : `${result.from} → ${result.to}`}</strong></div><div><span>Ledger basis</span><strong>Posted entries only</strong></div><div className={reconciled ? 'report-balanced' : 'report-unbalanced'}>{reconciled ? <CheckCircle2 size={16} /> : <XCircle size={16} />}<strong>{reconciled ? 'Reconciled' : 'Out of balance'}</strong></div></div>
      {kind === 'trial_balance' && <article className="accounting-panel"><div className="accounting-panel-heading"><div><h2>Trial balance</h2><span>Opening, period movement and closing debit/credit totals.</span></div></div><div className="accounting-table-scroll"><table className="accounting-table report-table"><thead><tr><th>Account</th><th>Name</th><th>Opening debit</th><th>Opening credit</th><th>Period debit</th><th>Period credit</th><th>Closing debit</th><th>Closing credit</th></tr></thead><tbody>{(result.rows ?? []).map((row) => <Row key={row.account_id} row={row} />)}</tbody><tfoot><tr><th colSpan={6}>Closing totals</th><th className="report-number">{money(result.totals?.closing_debit)}</th><th className="report-number">{money(result.totals?.closing_credit)}</th></tr></tfoot></table>{!(result.rows ?? []).length && <div className="accounting-state compact"><strong>No posted ledger activity for this period</strong></div>}</div></article>}
      {kind === 'profit_loss' && <div className="report-statement-grid"><StatementSection title="Revenue" rows={result.sections?.revenue ?? []} total={result.revenue} /><StatementSection title="Expenses" rows={result.sections?.expense ?? []} total={result.expenses} /><article className="accounting-panel report-net-profit"><span>Net profit / (loss)</span><strong>{money(result.net_profit)}</strong></article></div>}
      {kind === 'balance_sheet' && <div className="report-statement-grid"><StatementSection title="Assets" rows={result.sections?.asset ?? []} total={result.total_assets} /><StatementSection title="Liabilities" rows={result.sections?.liability ?? []} total={result.total_liabilities} /><StatementSection title="Equity" rows={result.sections?.equity ?? []} total={result.total_equity} /><article className="accounting-panel report-equation"><span>Accounting equation difference</span><strong>{money(result.difference)}</strong></article></div>}
    </>}
  </section>;
}

function StatementSection({ title, rows, total }: { title: string; rows: FinancialReportRow[]; total?: string }) {
  return <article className="accounting-panel report-section"><div className="accounting-panel-heading"><div><h2>{title}</h2><span>{rows.length} account{rows.length === 1 ? '' : 's'}</span></div></div><div className="accounting-table-scroll"><table className="accounting-table"><thead><tr><th>Account</th><th>Name</th><th>Amount</th></tr></thead><tbody>{rows.map((row) => <Row key={row.account_id} row={row} amount />)}</tbody><tfoot><tr><th colSpan={2}>Total {title}</th><th className="report-number">{money(total)}</th></tr></tfoot></table>{!rows.length && <div className="report-empty">No posted entries</div>}</div></article>;
}
