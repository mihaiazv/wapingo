@extends('layouts.app')
@section('content')

<h1>Keyword Routing Addon!</h1>
<p>Modul rutare.</p>

<h2>Reguli Keyword Routing</h2>
<form method="POST" action="{{ route('keyword.routing.store') }}">
    @csrf
    <input type="text" name="keyword" placeholder="Cuvânt cheie" required>
    <select name="tag_id">
        <option value="">Alege etichetă</option>
        @foreach($labels as $label)
            <option value="{{ $label->_id }}">{{ $label->title }}</option>
        @endforeach
    </select>
    <select name="agent_id">
        <option value="">Alege agent</option>
        @foreach($agents as $agent)
            <option value="{{ $agent->_id }}">
                {{ $agent->first_name }} {{ $agent->last_name }} ({{ $agent->email }})
            </option>
        @endforeach
    </select>
    <button type="submit">Adaugă Regulă</button>
</form>
<ul>
<ul>
@foreach($rules as $rule)
    @php
        $tag = $labels->firstWhere('_id', $rule->tag_id);
        $agent = $agents->firstWhere('_id', $rule->agent_id);
    @endphp
    <li>
        {{ $rule->keyword }}
        @if($tag) → Tag: {{ $tag->title }} @endif
        @if($agent) | Agent: {{ $agent->first_name }} {{ $agent->last_name }} ({{ $agent->email }}) @endif
        <form method="POST" action="{{ route('keyword.routing.delete', $rule->id) }}">
            @csrf @method('DELETE')
            <button type="submit">Șterge</button>
        </form>
    </li>
@endforeach
</ul>
</ul>
@endsection