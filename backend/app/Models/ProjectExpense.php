<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProjectExpense extends Model { use HasUuids; protected $fillable=['tenant_id','project_id','employee_id','amount','currency_code','expense_date','state','description']; protected function casts():array{return ['amount'=>'decimal:6','expense_date'=>'date'];} public function project(){return $this->belongsTo(Project::class);} public function employee(){return $this->belongsTo(Employee::class);} }
