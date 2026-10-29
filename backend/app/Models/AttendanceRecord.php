<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AttendanceRecord extends Model { use HasUuids; protected $fillable=['tenant_id','employee_id','work_date','clock_in','clock_out','worked_hours','state','notes']; protected function casts():array{return ['work_date'=>'date','clock_in'=>'datetime','clock_out'=>'datetime','worked_hours'=>'decimal:2'];} }
