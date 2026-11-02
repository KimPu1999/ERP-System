import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, CheckCircle2, Plus, RefreshCw, RotateCcw, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApiError, approvePurchaseReturn, createPurchaseReturn, getDashboardCompanies, getGoodsReceipts, getPurchaseOrders, getPurchaseReturns, getSuppliers, getWarehouseLocations, submitPurchaseReturn, type PurchaseReturnPayload } from '../lib/api';

const errorText = (error: unknown) => error instanceof ApiError ? error.message : error instanceof Error ? error.message : 'The request could not be completed.';

export function PurchaseReturnsPage() {
  const [params, setParams] = useSearchParams(); const client = useQueryClient(); const companies = useQuery({ queryKey: ['dashboard-companies', 'returns'], queryFn: getDashboardCompanies }); const companyId = params.get('company_id') ?? companies.data?.data.data[0]?.id ?? '';
  const [search, setSearch] = useState(''); const [open, setOpen] = useState(false); const [success, setSuccess] = useState(''); const returns = useQuery({ queryKey: ['purchase-returns', companyId, search], queryFn: () => getPurchaseReturns({ company_id: companyId, search, per_page: 25 }), enabled: Boolean(companyId) }); const suppliers = useQuery({ queryKey: ['suppliers', 'returns'], queryFn: () => getSuppliers() });
  const submit = useMutation({ mutationFn: submitPurchaseReturn, onSuccess: () => client.invalidateQueries({ queryKey: ['purchase-returns'] }) }); const approve = useMutation({ mutationFn: approvePurchaseReturn, onSuccess: () => client.invalidateQueries({ queryKey: ['purchase-returns'] }) });
  const summary = returns.data?.summary;
  return <section className="purchasing-page page-section"><header className="purchasing-page-header"><div><div className="breadcrumb-line"><span>Purchasing</span><span>/</span><span>Purchase Returns</span></div><p className="eyebrow">Return to supplier</p><h1>Purchase returns</h1><p className="muted">Create controlled return cases, obtain approval, and dispatch through the inventory ledger.</p></div><div className="page-heading-actions"><button className="secondary" onClick={() => returns.refetch()}><RefreshCw size={15}/> Refresh</button><button className="primary" onClick={() => setOpen(true)}><Plus size={15}/> Create return</button></div></header>{success && <div className="inline-success" role="status"><span>{success}</span><button type="button" aria-label="Dismiss success message" onClick={() => setSuccess('')}><X size={15}/></button></div>}
    <div className="purchasing-filter-toolbar"><label><span>Company</span><select value={companyId} onChange={e => { const n = new URLSearchParams(params); n.set('company_id', e.target.value); setParams(n, { replace: true }); }}>{companies.data?.data.data.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select></label><label className="filter-grow"><span>Search return, PO or supplier</span><input value={search} onChange={e => setSearch(e.target.value)} placeholder="RET-, PO-, supplier name"/></label><label><span>Supplier</span><select onChange={e => { const n = new URLSearchParams(params); if (e.target.value) n.set('supplier_id', e.target.value); else n.delete('supplier_id'); setParams(n); }}><option value="">All suppliers</option>{suppliers.data?.data.data.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</select></label></div>
    <div className="metric-grid"><Metric label="Total returns" value={summary?.total_returns}/><Metric label="Pending approval" value={summary?.pending_approval}/><Metric label="Dispatched" value={summary?.dispatched}/><Metric label="Awaiting supplier credit" value={summary?.awaiting_supplier_credit}/><Metric label="Open cases" value={summary?.open_cases}/></div>
    {(returns.isError || submit.isError || approve.isError) && <div className="alert alert-danger">{errorText(returns.error ?? submit.error ?? approve.error)}</div>}
    <article className="purchasing-table-card table-card"><div className="table-card-heading"><div><p className="eyebrow">Case register</p><h2>Return cases</h2></div><span className="muted">{returns.data?.data.total ?? '—'} records</span></div><div className="table-scroll"><table><thead><tr><th>Return</th><th>Supplier</th><th>PO / GRN</th><th>Date</th><th>Reason</th><th>Approval</th><th>Shipment</th><th>Actions</th></tr></thead><tbody>{returns.isLoading ? <tr><td colSpan={8} className="muted">Loading return cases…</td></tr> : (returns.data?.data.data ?? []).map(item => <tr key={item.id}><td><Link className="text-link" to={`/purchasing/returns/${item.id}?company_id=${companyId}`}>{item.number}</Link></td><td>{item.purchase_order?.supplier?.name ?? '—'}</td><td>{item.purchase_order?.number ?? '—'}<small className="table-subtext">{item.goods_receipt?.number ?? '—'}</small></td><td>{item.return_date ?? '—'}</td><td>{item.reason ?? '—'}</td><td><span className={`status-pill ${item.approval_status === 'approved' ? 'success' : ''}`}>{item.approval_status}</span></td><td>{item.shipment_status}</td><td><div className="table-actions">{item.approval_status === 'draft' && <button className="icon-action" title="Submit for approval" onClick={() => submit.mutate(item.id)}><Send size={14}/></button>}{item.approval_status === 'pending' && <button className="icon-action" title="Approve" onClick={() => approve.mutate(item.id)}><CheckCircle2 size={14}/></button>}<ArrowRight size={14} className="muted"/></div></td></tr>)}{!returns.isLoading && !returns.data?.data.data.length && <tr><td colSpan={8} className="muted">No purchase returns in this company scope.</td></tr>}</tbody></table></div></article>{open && <ReturnForm companyId={companyId} onClose={() => setOpen(false)} onCreated={() => { setOpen(false); setSuccess('Purchase return draft created successfully.'); void client.invalidateQueries({ queryKey: ['purchase-returns'] }); }}/>}</section>;
}

function Metric({ label, value }: { label: string; value?: number }) { return <article className="metric"><span>{label}</span><strong>{value ?? '—'}</strong></article>; }

function ReturnForm({ companyId, onClose, onCreated }: { companyId: string; onClose: () => void; onCreated: () => void }) {
  const dialogRef = useRef<HTMLElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const orders = useQuery({ queryKey: ['purchase-orders', 'return-form', companyId], queryFn: () => getPurchaseOrders({ company_id: companyId, state: 'approved', per_page: 100 }) });
  const [poId, setPoId] = useState('');
  const receipts = useQuery({ queryKey: ['goods-receipts', 'return-form', companyId, poId], queryFn: () => getGoodsReceipts({ company_id: companyId, purchase_order_id: poId, state: 'posted', per_page: 100 }), enabled: Boolean(poId) });
  const [receiptId, setReceiptId] = useState('');
  const selectedReceipt = receipts.data?.data.data.find((receipt) => receipt.id === receiptId);
  const receiptItems = selectedReceipt?.items ?? [];
  const [receiptItemId, setReceiptItemId] = useState('');
  const selectedItem = receiptItems.find((item) => item.id === receiptItemId);
  const locations = useQuery({ queryKey: ['warehouse-locations', 'purchase-return', selectedReceipt?.warehouse_id], queryFn: () => getWarehouseLocations(selectedReceipt!.warehouse_id!), enabled: Boolean(selectedReceipt?.warehouse_id) });
  const [reason, setReason] = useState('damaged');
  const [resolution, setResolution] = useState('supplier_credit');
  const [quantity, setQuantity] = useState('');
  const [locationId, setLocationId] = useState('');
  const [formError, setFormError] = useState('');
  const create = useMutation({ mutationFn: (data: PurchaseReturnPayload) => createPurchaseReturn(data), onSuccess: onCreated });

  useEffect(() => {
    closeButtonRef.current?.focus();
    const onKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape' && !create.isPending) onClose(); };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [create.isPending, onClose]);

  const fieldError = (field: string) => create.error instanceof ApiError ? create.error.errors[field]?.[0] : undefined;
  const chooseReceipt = (id: string) => {
    const receipt = receipts.data?.data.data.find((item) => item.id === id);
    const line = receipt?.items?.[0];
    setReceiptId(id);
    setReceiptItemId(line?.id ?? '');
    setLocationId(line?.location_id ?? '');
    setQuantity('');
    setFormError('');
    create.reset();
  };
  const chooseItem = (id: string) => {
    const line = receiptItems.find((item) => item.id === id);
    setReceiptItemId(id);
    setLocationId(line?.location_id ?? '');
    setQuantity('');
    setFormError('');
    create.reset();
  };
  const save = () => {
    const numericQuantity = Number(quantity);
    const received = Number(selectedItem?.accepted_quantity ?? selectedItem?.quantity ?? 0);
    if (!selectedReceipt || !selectedItem || !locationId || !Number.isFinite(numericQuantity) || numericQuantity <= 0) {
      setFormError('Select a receipt item and location, then enter a quantity greater than zero.');
      return;
    }
    if (numericQuantity > received) {
      setFormError(`Return quantity cannot exceed the eligible quantity of ${received}.`);
      return;
    }
    setFormError('');
    create.mutate({ company_id: companyId, purchase_order_id: poId, goods_receipt_id: receiptId, return_date: new Date().toISOString().slice(0, 10), reason, requested_resolution: resolution, lines: [{ goods_receipt_item_id: selectedItem.id, location_id: locationId, quantity }] });
  };

  return <div className="purchase-return-modal-backdrop"><article className="purchase-return-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="purchase-return-title">
    <header className="purchase-return-modal-header"><div><p className="eyebrow">New case</p><h2 id="purchase-return-title">Create purchase return</h2><p className="muted">Create a draft without changing inventory. Approval is required before dispatch.</p></div><button ref={closeButtonRef} className="icon-action" type="button" aria-label="Close purchase return form" onClick={onClose}><X size={16}/></button></header>
    <div className="purchase-return-modal-body">
      <section className="purchase-return-form-section"><h3>Return information</h3><div className="purchase-return-form-grid">
        <label><span>Purchase order <b className="required-mark">*</b></span><select value={poId} onChange={(event) => { setPoId(event.target.value); setReceiptId(''); setReceiptItemId(''); setLocationId(''); setQuantity(''); setFormError(''); create.reset(); }}><option value="">Select approved purchase order</option>{orders.data?.data.data.map((po) => <option value={po.id} key={po.id}>{po.number} · {po.supplier?.name ?? 'Supplier'}</option>)}</select>{orders.isLoading && <small className="field-hint">Loading approved purchase orders…</small>}{orders.isError && <small className="field-error">Unable to load approved purchase orders.</small>}</label>
        <label><span>Posted goods receipt <b className="required-mark">*</b></span><select value={receiptId} disabled={!poId || receipts.isLoading} onChange={(event) => chooseReceipt(event.target.value)}><option value="">{receipts.isLoading ? 'Loading posted receipts…' : 'Select posted goods receipt'}</option>{receipts.data?.data.data.map((receipt) => <option value={receipt.id} key={receipt.id}>{receipt.number} · {receipt.receipt_date ?? 'No date'}</option>)}</select>{receipts.isError && <small className="field-error">Unable to load posted goods receipts.</small>}</label>
        <label><span>Receipt item <b className="required-mark">*</b></span><select value={receiptItemId} disabled={!receiptId || !receiptItems.length} onChange={(event) => chooseItem(event.target.value)}><option value="">Select receipt item</option>{receiptItems.map((item) => <option value={item.id} key={item.id}>{item.purchase_order_item?.variant?.product?.name ?? item.purchase_order_item?.description ?? 'Receipt line'} · received {item.accepted_quantity ?? item.quantity}</option>)}</select>{receiptId && !receiptItems.length && <small className="field-error">This receipt has no returnable lines.</small>}</label>
        <label><span>Return reason <b className="required-mark">*</b></span><select value={reason} onChange={(event) => setReason(event.target.value)}><option value="damaged">Damaged goods</option><option value="defective">Defective product</option><option value="wrong_product">Wrong product delivered</option><option value="quality_rejection">Quality rejection</option><option value="expired">Expired / near expiry</option><option value="other">Other</option></select></label>
        <label><span>Requested resolution <b className="required-mark">*</b></span><select value={resolution} onChange={(event) => setResolution(event.target.value)}><option value="supplier_credit">Supplier credit</option><option value="refund">Refund</option><option value="replacement">Replacement</option><option value="other">Other</option></select></label>
        <label><span>Return quantity <b className="required-mark">*</b></span><input inputMode="decimal" min="0.000001" step="any" value={quantity} disabled={!selectedItem} aria-invalid={Boolean(fieldError('lines') || formError)} onChange={(event) => { setQuantity(event.target.value); setFormError(''); create.reset(); }} placeholder={selectedItem ? `Eligible: ${selectedItem.accepted_quantity ?? selectedItem.quantity}` : 'Select receipt item first'} />{fieldError('lines') && <small className="field-error">{fieldError('lines')}</small>}</label>
        <label><span>Warehouse location <b className="required-mark">*</b></span><select value={locationId} disabled={!selectedReceipt || locations.isLoading} onChange={(event) => setLocationId(event.target.value)}><option value="">{locations.isLoading ? 'Loading locations…' : 'Select warehouse location'}</option>{(locations.data?.data ?? []).map((location) => <option value={location.id} key={location.id}>{location.code} · {location.name}</option>)}</select>{locations.isError && <small className="field-error">Unable to load warehouse locations.</small>}</label>
      </div></section>
      {selectedItem && <div className="purchase-return-line-summary"><RotateCcw size={15}/><span>{selectedItem.purchase_order_item?.variant?.product?.name ?? selectedItem.purchase_order_item?.description ?? 'Receipt line'} · accepted {selectedItem.accepted_quantity ?? selectedItem.quantity} · {selectedItem.batch_number ? `batch ${selectedItem.batch_number}` : 'no batch reference'}</span></div>}
      {(formError || (create.isError && !fieldError('lines'))) && <div className="alert alert-danger" role="alert">{formError || errorText(create.error)}</div>}
    </div>
    <footer className="purchase-return-modal-footer"><button className="secondary" type="button" onClick={onClose}>Cancel</button><button className="primary" type="button" disabled={create.isPending || !poId || !receiptId || !receiptItemId || !quantity || !locationId} onClick={save}>{create.isPending ? 'Creating…' : 'Create draft'}</button></footer>
  </article></div>;
}
