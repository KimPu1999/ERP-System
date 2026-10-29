<?php

namespace App\Services;

use App\Models\ProcurementDocumentHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProcurementStateService
{
    private const TRANSITIONS = [
        'PurchaseRequest' => ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected', 'cancelled'], 'approved' => ['cancelled']],
        'Rfq' => ['draft' => ['sent', 'cancelled'], 'sent' => ['closed', 'cancelled']],
        'SupplierQuotation' => ['draft' => ['submitted', 'rejected'], 'submitted' => ['accepted', 'rejected']],
        'PurchaseOrder' => ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected', 'revision_required', 'cancelled'], 'revision_required' => ['draft', 'cancelled'], 'rejected' => [], 'approved' => ['partially_received', 'received', 'cancelled'], 'partially_received' => ['partially_received', 'received', 'closed'], 'received' => ['closed']],
        'GoodsReceipt' => ['draft' => ['posted', 'cancelled']],
        'PurchaseReturn' => ['draft' => ['approved', 'cancelled'], 'approved' => ['posted', 'cancelled']],
        'SupplierBill' => ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected'], 'approved' => ['paid', 'void']],
    ];

    public function transition(Model $document, string $to, Request $request, string $tenantId, ?string $comment = null): Model
    {
        $type = class_basename($document); $from = (string) $document->state; if (! in_array($to, self::TRANSITIONS[$type][$from] ?? [], true)) throw ValidationException::withMessages(['state' => ["Cannot transition {$type} from {$from} to {$to}."]]); $document->update(['state' => $to]); ProcurementDocumentHistory::create(['tenant_id' => $tenantId, 'document_type' => $type, 'document_id' => $document->id, 'actor_id' => $request->user()?->id, 'from_state' => $from, 'to_state' => $to, 'comment' => $comment]); return $document->fresh();
    }
}
