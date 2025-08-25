<?php
namespace Addons\KeywordRouting\Models;
use Illuminate\Database\Eloquent\Model;

class KeywordRoutingRule extends Model
{
    protected $table = 'keyword_routing_rules';
    protected $fillable = [
        'user_id', 'keyword', 'tag_id', 'agent_id'
    ];
}
