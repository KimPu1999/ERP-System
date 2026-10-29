<?php
namespace App\Services;
use App\Support\Decimal;
final class SalesCalculationService {
    public function line(array $item): array { $qty=Decimal::normalize((string)$item['quantity']); $price=Decimal::normalize((string)$item['unit_price']); $discountRate=Decimal::normalize((string)($item['discount_rate']??'0')); $taxRate=Decimal::normalize((string)($item['tax_rate']??'0')); $subtotal=Decimal::mul($qty,$price); $discount=Decimal::div(Decimal::mul($subtotal,$discountRate), '100'); $taxable=Decimal::sub($subtotal,$discount); $tax=Decimal::div(Decimal::mul($taxable,$taxRate),'100'); return ['quantity'=>$qty,'unit_price'=>$price,'discount_rate'=>$discountRate,'tax_rate'=>$taxRate,'line_subtotal'=>$subtotal,'discount_amount'=>$discount,'tax_amount'=>$tax,'line_total'=>Decimal::add($taxable,$tax)]; }
    public function totals(array $lines): array { $out=['subtotal'=>'0.000000','discount_total'=>'0.000000','tax_total'=>'0.000000','total'=>'0.000000']; foreach($lines as $line){$out['subtotal']=Decimal::add($out['subtotal'],$line['line_subtotal']);$out['discount_total']=Decimal::add($out['discount_total'],$line['discount_amount']);$out['tax_total']=Decimal::add($out['tax_total'],$line['tax_amount']);$out['total']=Decimal::add($out['total'],$line['line_total']);} return $out; }
}
