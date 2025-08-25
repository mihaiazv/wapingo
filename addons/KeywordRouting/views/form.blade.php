<form method="POST" action="{{ route('keyword.routing.store') }}">
    @csrf
    <input type="text" name="keyword" placeholder="Cuvânt cheie" required>
    <input type="text" name="tag_id" placeholder="ID Etichetă (opțional)">
    <input type="text" name="agent_id" placeholder="ID Agent (opțional)">
    <button type="submit">Adaugă Regulă</button>
</form>
