<?php
namespace Addons\KeywordRouting\Listeners;

use App\Events\VendorChannelBroadcast;
use Addons\KeywordRouting\Models\KeywordRoutingRule;
use App\Yantrana\Components\Contact\Models\ContactModel;
use App\Yantrana\Components\Contact\Models\ContactLabelModel;
use App\Yantrana\Components\WhatsAppService\Models\WhatsAppMessageLogModel;

class KeywordRoutingListener
{
    public function handle(VendorChannelBroadcast $event)
    {
        $payload = $event->data;

        // Extragem contactUid și verificăm că e mesaj incoming
        $contactUid           = $payload['contactUid']           ?? null;
        $isNewIncomingMessage = $payload['isNewIncomingMessage'] ?? false;

        if (!$contactUid || !$isNewIncomingMessage) {
            return;
        }

        // Găsim contactul după UID
        $contact = ContactModel::where('_uid', $contactUid)->first();
        if (!$contact) {
            return;
        }

        // Obținem ID-ul vendorului
        $vendorId = $contact->vendors__id;

        // Preluăm ultimul mesaj incoming salvat în whatsapp_message_logs
        $lastMessage = WhatsAppMessageLogModel::where('contacts__id', $contact->_id)
            ->where('is_incoming_message', 1)
            ->orderBy('messaged_at', 'desc')
            ->first();

        if (!$lastMessage) {
            return;
        }

        // Obținem corpul textului
        $messageBody = $lastMessage->message ?? null;
        if (!$messageBody) {
            return;
        }

        // Căutăm regulile cu vendorId (nu user_id)
        $rules = KeywordRoutingRule::where('user_id', $vendorId)->get();
        foreach ($rules as $rule) {
            if (stripos($messageBody, $rule->keyword) !== false) {
                // Dacă există tag_id, adaugăm eticheta
                if ($rule->tag_id) {
                    $exists = ContactLabelModel::where('contacts__id', $contact->_id)
                        ->where('labels__id', $rule->tag_id)
                        ->exists();

                    if (!$exists) {
                        $label = new ContactLabelModel();
                        $label->contacts__id = $contact->_id;
                        $label->labels__id   = $rule->tag_id;
                        $label->save();
                    }
                }

                // Dacă există agent_id, atribuim agentul
                if ($rule->agent_id) {
                    $contact->assigned_users__id = $rule->agent_id;
                    $contact->save();
                }

                // Dacă vrei să te oprești după primul match, de-comentează:
                // break;
            }
        }
    }
}