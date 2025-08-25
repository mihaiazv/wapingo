<?php
namespace Addons\KeywordRouting\Controllers;

use Illuminate\Http\Request;
use Addons\KeywordRouting\Models\KeywordRoutingRule;
use App\Http\Controllers\Controller;

class KeywordRoutingController extends Controller
{
    public function index(Request $request)
    {
        // 1. Aflăm vendorId din user-ul curent
        $vendorId = auth()->user()->vendors__id ?? null;

        // 2. Luăm regulile pentru acest vendor
        $rules = KeywordRoutingRule::where('user_id', $vendorId)->get();

        // 3. Preluăm etichetele (labels) doar pentru vendor
        $labelsQuery = \DB::table('labels');
        if ($vendorId) {
            $labelsQuery->where('vendors__id', $vendorId);
        }
        $labels = $labelsQuery->get();

        // 4. Preluăm agenții (users) doar pentru vendor
        $agentsQuery = \DB::table('users');
        if ($vendorId) {
            $agentsQuery->where('vendors__id', $vendorId);
        }
        $agents = $agentsQuery->get();

        return view('KeywordRouting::index', compact('rules', 'labels', 'agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'keyword'  => 'required|string',
            'tag_id'   => 'nullable|integer',
            'agent_id' => 'nullable|integer',
        ]);

        // Aflăm vendorId din user-ul curent
        $vendorId = auth()->user()->vendors__id ?? null;
        $data['user_id'] = $vendorId;

        KeywordRoutingRule::create($data);

        return redirect()->back()->with('success', 'Regulă adăugată!');
    }

    public function destroy($id)
    {
        // Aflăm vendorId din user-ul curent
        $vendorId = auth()->user()->vendors__id ?? null;

        KeywordRoutingRule::where('user_id', $vendorId)
                          ->where('id', $id)
                          ->delete();

        return redirect()->back()->with('success', 'Regulă ștearsă!');
    }
}