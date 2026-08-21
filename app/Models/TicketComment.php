<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class TicketComment extends Model { use BelongsToTenant; protected $fillable=['tenant_id','support_ticket_id','user_id','customer_portal_account_id','partner_account_id','body','is_internal']; protected function casts():array{return ['is_internal'=>'boolean'];} public function ticket(){return $this->belongsTo(SupportTicket::class,'support_ticket_id');} public function user(){return $this->belongsTo(User::class);} public function portalAccount(){return $this->belongsTo(CustomerPortalAccount::class);} }
