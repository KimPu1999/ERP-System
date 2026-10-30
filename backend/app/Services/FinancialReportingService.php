<?php

namespace App\Services;

use App\Models\{ChartAccount, JournalLine};
use App\Support\Decimal;
use Illuminate\Support\Collection;

final class FinancialReportingService
{
    public function trialBalance(string $tenantId, string $companyId, string $from, string $to): array
    {
        $accounts = $this->accounts($tenantId, $companyId);
        $opening = $this->totals($tenantId, $companyId, null, $from);
        $activity = $this->totals($tenantId, $companyId, $from, $to);
        $rows = [];
        $debit = '0'; $credit = '0';
        foreach ($accounts as $account) {
            $open = $opening->get($account->id, ['debit' => '0', 'credit' => '0']); $move = $activity->get($account->id, ['debit' => '0', 'credit' => '0']);
            $closingDebit = Decimal::add($open['debit'], $move['debit']); $closingCredit = Decimal::add($open['credit'], $move['credit']);
            if (Decimal::compare($closingDebit, '0') === 0 && Decimal::compare($closingCredit, '0') === 0) continue;
            $debit = Decimal::add($debit, $closingDebit); $credit = Decimal::add($credit, $closingCredit);
            $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'subtype' => $account->subtype, 'opening_debit' => $open['debit'], 'opening_credit' => $open['credit'], 'period_debit' => $move['debit'], 'period_credit' => $move['credit'], 'closing_debit' => $closingDebit, 'closing_credit' => $closingCredit];
        }
        return ['rows' => $rows, 'totals' => ['closing_debit' => $debit, 'closing_credit' => $credit], 'balanced' => Decimal::compare($debit, $credit) === 0, 'from' => $from, 'to' => $to];
    }

    public function profitAndLoss(string $tenantId, string $companyId, string $from, string $to): array
    {
        $trial = $this->trialBalance($tenantId, $companyId, $from, $to); $sections = ['revenue' => [], 'expense' => []]; $totals = ['revenue' => '0', 'expense' => '0'];
        foreach ($trial['rows'] as $row) if (isset($sections[$row['type']])) { $amount = $row['type'] === 'revenue' ? Decimal::sub($row['closing_credit'], $row['closing_debit']) : Decimal::sub($row['closing_debit'], $row['closing_credit']); $sections[$row['type']][] = [...$row, 'amount' => $amount]; $totals[$row['type']] = Decimal::add($totals[$row['type']], $amount); }
        return ['sections' => $sections, 'revenue' => $totals['revenue'], 'expenses' => $totals['expense'], 'net_profit' => Decimal::sub($totals['revenue'], $totals['expense']), 'balanced_trial_balance' => $trial['balanced'], 'from' => $from, 'to' => $to];
    }

    public function balanceSheet(string $tenantId, string $companyId, string $asOf): array
    {
        $trial = $this->trialBalance($tenantId, $companyId, '0001-01-01', $asOf); $sections = ['asset' => [], 'liability' => [], 'equity' => []]; $totals = ['asset' => '0', 'liability' => '0', 'equity' => '0'];
        foreach ($trial['rows'] as $row) if (isset($sections[$row['type']])) { $amount = in_array($row['type'], ['asset'], true) ? Decimal::sub($row['closing_debit'], $row['closing_credit']) : Decimal::sub($row['closing_credit'], $row['closing_debit']); $sections[$row['type']][] = [...$row, 'amount' => $amount]; $totals[$row['type']] = Decimal::add($totals[$row['type']], $amount); }
        // Unclosed income and expense balances are presented as current-period earnings.
        // If a fiscal close journal has transferred them to retained earnings, this value is zero.
        $currentPeriodEarnings = $this->profitAndLoss($tenantId, $companyId, '0001-01-01', $asOf)['net_profit'];
        if (Decimal::compare($currentPeriodEarnings, '0') !== 0) {
            $sections['equity'][] = ['account_id' => 'current-period-earnings', 'code' => '', 'name' => 'Current period earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'amount' => $currentPeriodEarnings];
            $totals['equity'] = Decimal::add($totals['equity'], $currentPeriodEarnings);
        }
        $difference = Decimal::sub($totals['asset'], Decimal::add($totals['liability'], $totals['equity']));
        return ['sections' => $sections, 'total_assets' => $totals['asset'], 'total_liabilities' => $totals['liability'], 'total_equity' => $totals['equity'], 'current_period_earnings' => $currentPeriodEarnings, 'difference' => $difference, 'balanced' => Decimal::compare($difference, '0') === 0, 'as_of' => $asOf];
    }

    private function accounts(string $tenantId, string $companyId): Collection
    {
        // Historical reports must retain accounts that have since been deactivated.
        return ChartAccount::where('tenant_id', $tenantId)->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))->orderBy('code')->get();
    }

    private function totals(string $tenantId, string $companyId, ?string $from, string $to): Collection
    {
        return JournalLine::query()->select('account_id')->selectRaw('sum(base_debit) as debit, sum(base_credit) as credit')->where('tenant_id', $tenantId)->where('company_id', $companyId)->whereHas('entry', function ($query) use ($from, $to): void { $query->where('state', 'posted'); if ($from) $query->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to); else $query->whereDate('entry_date', '<', $to); })->groupBy('account_id')->get()->mapWithKeys(fn ($row) => [$row->account_id => ['debit' => Decimal::normalize((string) $row->debit), 'credit' => Decimal::normalize((string) $row->credit)] ]);
    }
}
